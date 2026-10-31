<?php

namespace App\Domains\Commissions\Services;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\ValueObjects\Idr;
use App\Domains\Wallet\Services\PartnerLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CommissionEngine
{
    public function __construct(private DirectCommissionCalculator $calculator, private PartnerLedger $ledger, private AuditWriter $audit) {}

    public function accrue(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            if ($order->partner_referral_id === null || $order->fraud_hold || ! in_array($order->status, ['paid', 'processing', 'shipped', 'delivered', 'completed'], true) || (int) $order->payments()->where('status', 'verified')->sum('amount') !== $order->grand_total) {
                return;
            }
            $partner = Partner::whereKey($order->partner_referral_id)->lockForUpdate()->firstOrFail();
            if ($partner->status !== 'active' || ! $partner->user->isActive() || $partner->user_id === $order->customer->user_id) {
                return;
            }
            foreach ($order->items()->orderBy('id')->get() as $item) {
                if (! $item->commission_eligible_snapshot || $item->commission_snapshot === null || $item->commission_rule_id === null) {
                    continue;
                }
                $key = 'item:'.$item->id.':partner:'.$partner->id.':rule:'.$item->commission_rule_id.':level:1';
                if (CommissionEntry::where('unique_key', $key)->exists()) {
                    continue;
                }
                $base = (new Idr($item->unit_price_snapshot))->multiply($item->quantity)->subtract(new Idr($item->discount_snapshot));
                $amount = $this->amount($base->amount, $item->commission_snapshot, $order->placed_at);
                if ($amount < 1) {
                    continue;
                }
                $entry = CommissionEntry::create(['order_item_id' => $item->id, 'beneficiary_partner_id' => $partner->id, 'rule_id' => $item->commission_rule_id, 'level' => 1,
                    'base_amount' => $base->amount, 'amount' => $amount, 'status' => 'pending', 'reversed_amount' => 0, 'offset_amount' => 0, 'unique_key' => $key]);
                $this->ledger->post($partner->id, 'pending', $amount, 'commission', 'commission:'.$entry->id.':accrual', $entry->id);
                $pastRefund = (int) Refund::where('order_item_id', $item->id)->where('status', 'processed')->sum('amount');
                if ($pastRefund > 0) {
                    $reversal = $amount - (new Idr($amount))->proportion(max(0, $base->amount - $pastRefund), $base->amount)->amount;
                    $entry->update(['reversed_amount' => $reversal, 'status' => $reversal === $amount ? 'cancelled' : 'pending']);
                    $this->ledger->post($partner->id, 'pending', -$reversal, 'reversal', 'commission:'.$entry->id.':catchup-refund', $entry->id);
                }
                $this->audit->record(null, 'direct_commission_accrued', 'commission_entries', $entry->id, 'commission:'.$entry->id.':accrued', ['amount' => $amount, 'level' => 1]);
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
            $partner = Partner::whereKey($order->partner_referral_id)->lockForUpdate()->firstOrFail();
            if ($partner->status !== 'active' || ! $partner->user->isActive()) {
                return;
            }
            foreach (CommissionEntry::whereIn('order_item_id', $order->items()->select('id'))->where('status', 'pending')->orderBy('id')->lockForUpdate()->get() as $entry) {
                $days = $entry->item->commission_snapshot['return_days'] ?? $order->rule_snapshot['return_days'] ?? config('partners.return_window_days');
                $eligibleAt = $order->delivered_at->addDays($days);
                $entry->update(['eligible_at' => $eligibleAt]);
                if ($eligibleAt->isFuture()) {
                    continue;
                }
                $remaining = $entry->amount - $entry->reversed_amount;
                $this->ledger->move($partner->id, 'pending', 'available', $remaining, 'release', 'commission:'.$entry->id.':release', $entry->id);
                $entry->update(['status' => 'available', 'available_at' => now()]);
                $this->audit->record(null, 'commission_available', 'commission_entries', $entry->id, 'commission:'.$entry->id.':available', ['amount' => $remaining]);
            }
            $this->ledger->settleDebt($partner->id, 'release-order:'.$order->id.':offset');
        }, attempts: 3);
    }
}
