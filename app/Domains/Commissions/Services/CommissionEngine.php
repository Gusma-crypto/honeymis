<?php

namespace App\Domains\Commissions\Services;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\PartnerRankService;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\ValueObjects\Idr;
use App\Domains\Wallet\Services\PartnerLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionEngine
{
    public function __construct(private DirectCommissionCalculator $calculator, private PartnerLedger $ledger, private AuditWriter $audit, private PartnerRankService $ranks) {}

    public function accrue(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            if ($order->partner_referral_id === null || $order->fraud_hold || ! in_array($order->status, ['paid', 'processing', 'shipped', 'delivered', 'completed'], true) || (int) $order->payments()->where('status', 'verified')->sum('amount') !== $order->grand_total) {
                return;
            }
            $this->ranks->recalculateForOrder($order);
            DB::table('customers')->where('id', $order->customer_id)->lockForUpdate()->first();
            $payment = $order->payments()->where('status', 'verified')->orderBy('verified_at')->first();
            if ($payment === null || $payment->verified_at === null) {
                return;
            }
            $chain = $this->generationChain($order);
            $partnerIds = array_values(array_unique(array_column($chain, 'partner_id')));
            /** @var Collection<int, Partner> $partners */
            $partners = Partner::with('user')->whereIn('id', $partnerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $isFirstOrder = $order->referral_snapshot['first_order_eligible'] ?? ! DB::table('orders')
                ->join('payments', 'payments.order_id', '=', 'orders.id')
                ->where('orders.customer_id', $order->customer_id)
                ->where('orders.id', '!=', $order->id)
                ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])
                ->where('payments.status', 'verified')
                ->whereNotNull('payments.verified_at')
                ->where(function ($query) use ($payment, $order): void {
                    $query->where('payments.verified_at', '<', $payment->verified_at)
                        ->orWhere(function ($query) use ($payment, $order): void {
                            $query->where('payments.verified_at', $payment->verified_at)
                                ->where('orders.id', '<', $order->id);
                        });
                })
                ->exists();
            if (! array_key_exists('first_order_eligible', $order->referral_snapshot ?? [])) {
                $order->update(['referral_snapshot' => [...($order->referral_snapshot ?? []), 'first_order_eligible' => $isFirstOrder]]);
            }
            foreach ($order->items()->orderBy('id')->get() as $item) {
                if (! $item->commission_eligible_snapshot || $item->commission_snapshot === null || $item->commission_rule_id === null) {
                    continue;
                }
                $base = (new Idr($item->unit_price_snapshot))->multiply($item->quantity)->subtract(new Idr($item->discount_snapshot));
                $margin = max(0, $base->amount - ($item->cost_snapshot * $item->quantity));
                if ($base->amount < 1 || $margin < 1) {
                    continue;
                }

                $commissionAmounts = [];
                $directPartnerId = $order->partner_referral_id;
                $directPartner = $partners->get($directPartnerId);
                if ($this->isEligibleRecipient($directPartner, $order->customer->user_id)) {
                    $directAmount = $this->amount($base->amount, $item->commission_snapshot, $order->placed_at);
                    if ($directAmount > 0) {
                        $commissionAmounts[] = ['partner_id' => $directPartnerId, 'level' => 0, 'type' => 'direct', 'amount' => $directAmount];
                    }
                }

                $rates = $item->commission_snapshot['generation_rates'] ?? [];
                $firstOrderRate = (int) ($item->commission_snapshot['first_order_referral_bps'] ?? 0);
                foreach ($chain as $generation) {
                    $rate = $generation['level'] === 1 && $isFirstOrder
                        ? $firstOrderRate
                        : (int) ($rates[$generation['level'] - 1] ?? 0);
                    $recipient = $partners->get($generation['partner_id']);
                    if ($rate < 1 || ! $this->isEligibleRecipient($recipient, $order->customer->user_id)) {
                        continue;
                    }
                    $commissionAmounts[] = [
                        'partner_id' => $generation['partner_id'],
                        'level' => $generation['level'],
                        'type' => $generation['level'] === 1 && $isFirstOrder ? 'referral' : 'network',
                        'amount' => (new Idr($base->amount))->basisPoints($rate)->amount,
                    ];
                }

                $maximumCommission = min($margin, $base->basisPoints((int) config('mlm.maximum_total_commission_bps'))->amount);
                if (array_sum(array_column($commissionAmounts, 'amount')) > $maximumCommission) {
                    throw new RuntimeException('MLM commission snapshot exceeds the item margin for order '.$order->order_number.'.');
                }

                foreach ($commissionAmounts as $commission) {
                    $partnerId = $commission['partner_id'];
                    $key = 'item:'.$item->id.':partner:'.$partnerId.':rule:'.$item->commission_rule_id.':type:'.$commission['type'].':level:'.$commission['level'];
                    if (CommissionEntry::where('unique_key', $key)->exists()
                        || CommissionEntry::where('order_item_id', $item->id)
                            ->where('beneficiary_partner_id', $partnerId)
                            ->where('rule_id', $item->commission_rule_id)
                            ->where('level', $commission['level'])
                            ->where('commission_type', $commission['type'])
                            ->exists()) {
                        continue;
                    }
                    $amount = $commission['amount'];
                    $entry = CommissionEntry::create([
                        'order_item_id' => $item->id,
                        'beneficiary_partner_id' => $partnerId,
                        'rule_id' => $item->commission_rule_id,
                        'level' => $commission['level'],
                        'commission_type' => $commission['type'],
                        'base_amount' => $base->amount,
                        'amount' => $amount,
                        'status' => 'pending',
                        'reversed_amount' => 0,
                        'offset_amount' => 0,
                        'unique_key' => $key,
                    ]);
                    $this->ledger->post($partnerId, 'pending', $amount, 'commission', 'commission:'.$entry->id.':accrual', $entry->id);
                    $pastRefund = (int) Refund::where('order_item_id', $item->id)->where('status', 'processed')->sum('amount');
                    if ($pastRefund > 0) {
                        $reversal = $amount - (new Idr($amount))->proportion(max(0, $base->amount - $pastRefund), $base->amount)->amount;
                        $entry->update(['reversed_amount' => $reversal, 'status' => $reversal === $amount ? 'cancelled' : 'pending']);
                        $this->ledger->post($partnerId, 'pending', -$reversal, 'reversal', 'commission:'.$entry->id.':catchup-refund', $entry->id);
                    }
                    $this->audit->record(null, 'mlm_commission_accrued', 'commission_entries', $entry->id, 'commission:'.$entry->id.':accrued', [
                        'amount' => $amount, 'level' => $commission['level'], 'commission_type' => $commission['type'],
                    ]);
                }
            }
            $order->update(['commission_checked_at' => now()]);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $snapshot */
    public function amount(int $base, array $snapshot, CarbonImmutable $placedAt): int
    {
        $rule = new RuleVersion($snapshot['version'], $snapshot['rate_bps'], $snapshot['fixed_amount'], $snapshot['cap_amount'], CarbonImmutable::parse($snapshot['valid_from']), empty($snapshot['valid_until']) ? null : CarbonImmutable::parse($snapshot['valid_until']));
        $money = new Idr($base);

        return min($this->calculator->calculate($money, $rule, $placedAt)->amount, $money->basisPoints($snapshot['budget_bps'])->amount);
    }

    public function release(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            if ($order->partner_referral_id === null || $order->fraud_hold || $order->delivered_at === null || ! in_array($order->status, ['delivered', 'completed'], true)) {
                return;
            }
            $entries = CommissionEntry::with('item')
                ->whereIn('order_item_id', $order->items()->select('id'))
                ->where('status', 'pending')->orderBy('id')->lockForUpdate()->get();
            $partnerIds = $entries->pluck('beneficiary_partner_id')->unique()->all();
            $partners = Partner::with('user')->whereIn('id', $partnerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $releasedPartnerIds = [];
            foreach ($entries as $entry) {
                $partner = $partners->get($entry->beneficiary_partner_id);
                if (! $this->isEligibleRecipient($partner, null)) {
                    continue;
                }
                $days = $entry->item->commission_snapshot['return_days'] ?? $order->rule_snapshot['return_days'] ?? config('partners.return_window_days');
                $eligibleAt = $order->delivered_at->addDays($days);
                $entry->update(['eligible_at' => $eligibleAt]);
                if ($eligibleAt->isFuture()) {
                    continue;
                }
                $remaining = $entry->amount - $entry->reversed_amount;
                $this->ledger->move($partner->id, 'pending', 'available', $remaining, 'release', 'commission:'.$entry->id.':release', $entry->id);
                $entry->update(['status' => 'available', 'available_at' => now()]);
                $releasedPartnerIds[$partner->id] = true;
                $this->audit->record(null, 'commission_available', 'commission_entries', $entry->id, 'commission:'.$entry->id.':available', ['amount' => $remaining]);
            }
            foreach (array_keys($releasedPartnerIds) as $partnerId) {
                $this->ledger->settleDebt($partnerId, 'release-order:'.$order->id.':partner:'.$partnerId.':offset');
            }
        }, attempts: 3);
    }

    /**
     * @return list<array{partner_id: int, level: int}>
     */
    private function generationChain(Order $order): array
    {
        $chain = $order->referral_snapshot['generation_chain'] ?? null;
        if (is_array($chain)) {
            $normalized = [];
            foreach ($chain as $generation) {
                if (! is_array($generation) || ! isset($generation['partner_id'], $generation['level'])
                    || ! is_numeric($generation['partner_id']) || ! is_numeric($generation['level'])) {
                    continue;
                }
                $partnerId = (int) $generation['partner_id'];
                $level = (int) $generation['level'];
                if ($partnerId > 0 && $level >= 1 && $level <= 5) {
                    $normalized[] = ['partner_id' => $partnerId, 'level' => $level];
                }
            }

            return $normalized;
        }

        return [['partner_id' => $order->partner_referral_id, 'level' => 1]];
    }

    private function isEligibleRecipient(?Partner $partner, ?int $customerUserId): bool
    {
        return $partner !== null
            && $partner->status === 'active'
            && $partner->user->isActive()
            && $partner->user_id !== $customerUserId;
    }
}
