<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\PartnerRankService;
use App\Domains\Payouts\Actions\ManagePayout;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\CommerceNotifier;
use App\Domains\Shared\ValueObjects\Idr;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProcessRefund
{
    public function __construct(private CommissionEngine $commissions, private PartnerLedger $ledger, private ManagePayout $payouts, private AuditWriter $audit, private PartnerRankService $ranks) {}

    public function request(User $user, Order $order, ?int $itemId, int $amount, string $reason, string $key): Refund
    {
        abort_unless($user->isActive() && $order->customer->user_id === $user->id, 403);

        return DB::transaction(function () use ($user, $order, $itemId, $amount, $reason, $key): Refund {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $fingerprint = $this->fingerprint($locked->id, $itemId, $amount, $reason);
            $existing = $this->existing($key, $fingerprint);
            if ($existing !== null) {
                return $existing;
            }
            $this->validateAmount($locked, $itemId, $amount, true);
            $refund = Refund::create(['order_id' => $locked->id, 'order_item_id' => $itemId, 'amount' => $amount, 'reason' => $reason,
                'idempotency_key' => $key, 'request_fingerprint' => $fingerprint, 'status' => 'requested']);
            $this->audit->record($user->id, 'refund_requested', 'refunds', $refund->id, 'refund:'.$refund->id.':requested', ['amount' => $amount]);

            return $refund;
        }, attempts: 3);
    }

    public function handle(User $actor, Order $order, ?int $itemId, int $amount, string $reason, string $key, bool $confirmed): Refund
    {
        Gate::forUser($actor)->authorize('refunds.manage');
        if (! $confirmed) {
            throw ValidationException::withMessages(['confirmed_refund' => 'Konfirmasi pengembalian dana manual wajib diisi.']);
        }

        return DB::transaction(function () use ($actor, $order, $itemId, $amount, $reason, $key): Refund {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $fingerprint = $this->fingerprint($order->id, $itemId, $amount, $reason);
            $refund = $this->existing($key, $fingerprint);
            if ($refund !== null && $refund->status === 'processed') {
                return $refund;
            }
            if ($refund !== null && $refund->status !== 'requested') {
                throw ValidationException::withMessages(['refund' => 'Permintaan sudah ditolak atau berubah.']);
            }
            $this->validateAmount($locked, $itemId, $amount, false);
            $this->commissions->accrue($locked->id);
            $refund ??= Refund::create(['order_id' => $locked->id, 'order_item_id' => $itemId, 'amount' => $amount, 'reason' => $reason, 'idempotency_key' => $key, 'request_fingerprint' => $fingerprint, 'status' => 'requested']);
            $refund->update(['status' => 'processed', 'processed_by' => $actor->id, 'processed_at' => now()]);
            if ($itemId !== null) {
                $entries = CommissionEntry::where('order_item_id', $itemId)->orderBy('id')->lockForUpdate()->get();
                $partnerIds = $entries->pluck('beneficiary_partner_id')->unique()->all();
                $partners = Partner::whereIn('id', $partnerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($entries as $entry) {
                    $partner = $partners->get($entry->beneficiary_partner_id);
                    if ($partner === null) {
                        continue;
                    }
                    $payoutIds = DB::table('payout_allocations')->where('commission_entry_id', $entry->id)->pluck('payout_id');
                    foreach (PayoutRequest::whereIn('id', $payoutIds)->whereIn('status', ['requested', 'approved'])->orderBy('id')->lockForUpdate()->get() as $openPayout) {
                        $this->payouts->releaseForRefund($openPayout, $refund->id);
                    }
                    $entry->refresh();
                    $cumulativeRefund = (int) Refund::where('order_item_id', $itemId)->where('status', 'processed')->sum('amount');
                    $newEntitlement = (new Idr($entry->amount))->proportion(max(0, $entry->base_amount - $cumulativeRefund), $entry->base_amount)->amount;
                    $delta = max(0, $entry->amount - $newEntitlement - $entry->reversed_amount);
                    if ($delta < 1) {
                        continue;
                    }
                    if ($entry->available_at === null) {
                        $this->ledger->post($partner->id, 'pending', -$delta, 'reversal', 'refund:'.$refund->id.':entry:'.$entry->id.':pending', $entry->id, refundId: $refund->id);
                    } else {
                        $take = min($delta, $this->ledger->availableFor($entry));
                        $this->ledger->post($partner->id, 'available', -$take, 'reversal', 'refund:'.$refund->id.':entry:'.$entry->id.':available', $entry->id, refundId: $refund->id);
                        $this->ledger->post($partner->id, 'receivable', $delta - $take, 'reversal', 'refund:'.$refund->id.':entry:'.$entry->id.':debt', $entry->id, refundId: $refund->id);
                    }
                    $entry->update(['reversed_amount' => $entry->reversed_amount + $delta]);
                    $this->ledger->refreshStatus($entry);
                }
                foreach ($partnerIds as $partnerId) {
                    $this->ledger->settleDebt((int) $partnerId, 'refund:'.$refund->id.':partner:'.$partnerId.':offset', $refund->id);
                }
            }
            if ((int) Refund::where('order_id', $locked->id)->where('status', 'processed')->sum('amount') >= $locked->grand_total) {
                if (in_array($locked->status, ['paid', 'processing'], true)) {
                    $this->releaseReservedStock($locked, $refund);
                }
                $locked->update(['status' => 'refunded']);
                $locked->payments()->where('status', 'verified')->update(['status' => 'refunded']);
                DB::table('outbox_messages')->insert(['event_key' => 'refund:'.$refund->id.':full', 'event_type' => 'orders.state_changed', 'aggregate_type' => 'orders', 'aggregate_id' => $locked->id,
                    'payload' => '{"to":"refunded"}', 'available_at' => now(), 'created_at' => now()]);
            }
            $this->ranks->recalculateForOrder($locked);
            if ($locked->customer->user_id !== null) {
                app(CommerceNotifier::class)->queue($locked->customer->user_id, 'refund:'.$refund->id.':processed', 'Refund diproses', 'Refund #'.$refund->id.' untuk order '.$locked->order_number.' telah dicatat.', route('orders.show', $locked->id, false));
            }
            $this->audit->record($actor->id, 'refund_processed', 'refunds', $refund->id, 'refund:'.$refund->id.':processed', ['amount' => $amount, 'order_item_id' => $itemId]);

            return $refund;
        }, attempts: 3);
    }

    private function validateAmount(Order $order, ?int $itemId, int $amount, bool $includePending): void
    {
        if (! in_array($order->status, ['paid', 'processing', 'shipped', 'delivered', 'completed'], true) || ! $order->payments()->where('status', 'verified')->exists()) {
            throw ValidationException::withMessages(['refund' => 'Refund hanya untuk order yang benar-benar dibayar.']);
        }
        $item = $itemId === null ? null : OrderItem::where('order_id', $order->id)->whereKey($itemId)->firstOrFail();
        $maximum = $item === null ? $order->shipping_total + $order->tax_total : $item->unit_price_snapshot * $item->quantity - $item->discount_snapshot;
        $used = (int) Refund::where('order_id', $order->id)->where('order_item_id', $itemId)->whereIn('status', $includePending ? ['requested', 'approved', 'processed'] : ['processed'])->sum('amount');
        if ($amount < 1 || $amount > $maximum - $used) {
            throw ValidationException::withMessages(['amount' => 'Refund melebihi sisa nilai item atau ongkir/pajak.']);
        }
    }

    private function fingerprint(int $orderId, ?int $itemId, int $amount, string $reason): string
    {
        return hash('sha256', json_encode([$orderId, $itemId, $amount, $reason], JSON_THROW_ON_ERROR));
    }

    private function existing(string $key, string $fingerprint): ?Refund
    {
        $existing = Refund::where('idempotency_key', $key)->first();
        if ($existing !== null && $existing->request_fingerprint !== $fingerprint) {
            throw ValidationException::withMessages(['idempotency_key' => 'Key refund telah digunakan untuk permintaan lain.']);
        }

        return $existing;
    }

    private function releaseReservedStock(Order $order, Refund $refund): void
    {
        foreach ($order->items()->orderBy('variant_id')->get() as $item) {
            $balance = InventoryBalance::where('variant_id', $item->variant_id)->lockForUpdate()->firstOrFail();
            if ($balance->reserved < $item->quantity) {
                throw ValidationException::withMessages(['refund' => 'Reservasi stok perlu direkonsiliasi.']);
            }
            $balance->update(['reserved' => $balance->reserved - $item->quantity]);
            DB::table('inventory_movements')->insert(['variant_id' => $item->variant_id, 'quantity_delta' => 0, 'reserved_delta' => -$item->quantity, 'reason' => 'order_refunded',
                'reference_type' => 'refunds', 'reference_id' => $refund->id, 'idempotency_key' => 'refund:'.$refund->id.':stock:'.$item->variant_id, 'created_at' => now()]);
        }
    }
}
