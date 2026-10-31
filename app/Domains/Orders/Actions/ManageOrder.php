<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Shipment;
use App\Domains\Shared\Services\TransitionRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageOrder
{
    public function __construct(private TransitionRecorder $transitions) {}

    /** @param array{courier?: string|null, tracking_number?: string|null} $shipping */
    public function handle(User $actor, Order $order, string $operation, string $key, array $shipping = []): void
    {
        DB::transaction(function () use ($actor, $order, $operation, $key, $shipping): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $ability = match ($operation) {
                'verify', 'reject' => 'verifyPayment', 'cancel' => 'cancel', default => 'manage'
            };
            Gate::forUser($actor)->authorize($ability, $locked);
            $auditKey = 'order-operation:'.$key;
            $fingerprint = ['operation' => $operation, 'shipping' => $shipping];
            $audit = DB::table('audit_logs')->where('idempotency_key', $auditKey)->first();
            if ($audit !== null) {
                if ((int) $audit->subject_id !== $locked->id || (int) $audit->actor_id !== $actor->id || json_decode($audit->after_json, true, flags: JSON_THROW_ON_ERROR) != $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key telah digunakan untuk operasi lain.']);
                }

                return;
            }
            if ($locked->fraud_hold && ! in_array($operation, ['cancel', 'reject'], true)) {
                throw ValidationException::withMessages(['operation' => 'Order sedang ditahan untuk pemeriksaan Finance.']);
            }
            $payment = $locked->payments()->reorder('id', 'desc')->lockForUpdate()->firstOrFail();
            if ($operation === 'verify' || $operation === 'reject') {
                if ($locked->status !== 'pending_payment' || $payment->status !== 'submitted' || $payment->proof_storage_key === null || $payment->amount !== $locked->grand_total) {
                    throw ValidationException::withMessages(['operation' => 'Pembayaran belum siap diverifikasi atau order sudah berubah.']);
                }
                if ($operation === 'verify') {
                    $payment->update(['verified_by' => $actor->id, 'verified_at' => now()]);
                    $this->transitions->record($payment->id, PaymentStatus::Verified, $actor->id, $auditKey.':payment');
                    $this->transitions->record($locked->id, OrderStatus::Paid, $actor->id, $auditKey.':paid');
                } else {
                    $this->transitions->record($payment->id, PaymentStatus::Rejected, $actor->id, $auditKey.':payment');
                }
            } elseif ($operation === 'cancel') {
                if ($locked->status !== 'pending_payment') {
                    throw ValidationException::withMessages(['operation' => 'Hanya order belum dibayar yang dapat dibatalkan.']);
                }
                $this->releaseStock($locked, false);
                if (in_array($payment->status, ['pending', 'submitted'], true)) {
                    $this->transitions->record($payment->id, PaymentStatus::Rejected, $actor->id, $auditKey.':cancel-payment');
                }
                $this->transitions->record($locked->id, OrderStatus::Cancelled, $actor->id, $auditKey.':cancel');
            } else {
                if ((int) $locked->payments()->where('status', 'verified')->sum('amount') !== $locked->grand_total) {
                    throw ValidationException::withMessages(['operation' => 'Order belum memiliki pembayaran terverifikasi.']);
                }
                $target = match ($operation) {
                    'process' => OrderStatus::Processing, 'ship' => OrderStatus::Shipped, 'deliver' => OrderStatus::Delivered,
                    default => throw ValidationException::withMessages(['operation' => 'Operasi tidak didukung.']),
                };
                if (! OrderStatus::from($locked->status)->canTransitionTo($target)) {
                    throw ValidationException::withMessages(['operation' => 'Transisi status order tidak valid.']);
                }
                if ($operation === 'ship') {
                    if (empty($shipping['courier']) || empty($shipping['tracking_number'])) {
                        throw ValidationException::withMessages(['tracking_number' => 'Kurir dan nomor resi wajib diisi.']);
                    }
                    $this->releaseStock($locked, true);
                    Shipment::create(['order_id' => $locked->id, 'courier' => $shipping['courier'], 'tracking_number' => $shipping['tracking_number'], 'status' => 'shipped', 'shipped_at' => now()]);
                }
                if ($operation === 'deliver') {
                    $shipment = $locked->shipments()->where('status', 'shipped')->firstOrFail();
                    $shipment->update(['status' => 'delivered', 'delivered_at' => now()]);
                    $locked->update(['delivered_at' => now()]);
                }
                $this->transitions->record($locked->id, $target, $actor->id, $auditKey.':'.$operation);
            }
            DB::table('audit_logs')->insert(['actor_id' => $actor->id, 'action' => 'order_operation', 'subject_type' => 'orders', 'subject_id' => $locked->id,
                'before_json' => null, 'after_json' => json_encode($fingerprint, JSON_THROW_ON_ERROR), 'idempotency_key' => $auditKey, 'created_at' => now()]);
        }, attempts: 3);
    }

    public function expire(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_payment' || $locked->expires_at === null || $locked->expires_at->isFuture() || $locked->payments()->where('status', 'submitted')->exists()) {
                return false;
            }
            $this->releaseStock($locked, false);
            $locked->update(['status' => 'cancelled']);
            $locked->payments()->where('status', 'pending')->update(['status' => 'rejected']);
            DB::table('outbox_messages')->insert(['event_key' => 'order:'.$locked->id.':expired', 'event_type' => 'orders.state_changed', 'aggregate_type' => 'orders', 'aggregate_id' => $locked->id, 'payload' => '{"from":"pending_payment","to":"cancelled"}', 'available_at' => now(), 'created_at' => now()]);
            DB::table('audit_logs')->insert(['actor_id' => null, 'action' => 'order_expired', 'subject_type' => 'orders', 'subject_id' => $locked->id,
                'before_json' => '{"status":"pending_payment"}', 'after_json' => '{"status":"cancelled"}', 'idempotency_key' => 'order:'.$locked->id.':expired', 'created_at' => now()]);

            return true;
        }, attempts: 3);
    }

    private function releaseStock(Order $order, bool $shipped): void
    {
        foreach ($order->items()->orderBy('variant_id')->get() as $item) {
            $balance = InventoryBalance::where('variant_id', $item->variant_id)->lockForUpdate()->firstOrFail();
            if ($balance->reserved < $item->quantity || ($shipped && $balance->on_hand < $item->quantity)) {
                throw ValidationException::withMessages(['operation' => 'Reservasi stok tidak konsisten; perlu pemeriksaan.']);
            }
            $balance->update(['reserved' => $balance->reserved - $item->quantity, 'on_hand' => $balance->on_hand - ($shipped ? $item->quantity : 0)]);
            DB::table('inventory_movements')->insert(['variant_id' => $item->variant_id, 'quantity_delta' => $shipped ? -$item->quantity : 0, 'reserved_delta' => -$item->quantity,
                'reason' => $shipped ? 'order_shipped' : 'order_released', 'reference_type' => 'orders', 'reference_id' => $order->id,
                'idempotency_key' => 'order:'.$order->id.':'.($shipped ? 'ship' : 'release').':'.$item->variant_id, 'created_at' => now()]);
        }
    }
}
