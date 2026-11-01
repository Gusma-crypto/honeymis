<?php

namespace App\Domains\Orders\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $commission_checked_at
 * @property int $return_window_days
 * @property bool $fraud_hold
 * @property int $id
 * @property int $customer_id
 * @property string $order_number
 * @property string $idempotency_key
 * @property string|null $request_fingerprint
 * @property int|null $cart_id
 * @property int|null $partner_referral_id
 * @property int|null $commission_rule_id
 * @property array<string, mixed>|null $referral_snapshot
 * @property array<string, mixed>|null $rule_snapshot
 * @property array<string, string> $shipping_address_snapshot
 * @property int $subtotal
 * @property int $discount_total
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $grand_total
 * @property string $currency
 * @property string $status
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $delivered_at
 * @property Customer $customer
 * @property Collection<int, OrderItem> $items
 * @property Collection<int, Payment> $payments
 * @property Collection<int, Shipment> $shipments
 */
#[Fillable(['commission_checked_at', 'return_window_days', 'fraud_hold', 'customer_id', 'order_number', 'idempotency_key', 'request_fingerprint', 'cart_id', 'partner_referral_id', 'commission_rule_id', 'referral_snapshot', 'rule_snapshot', 'shipping_address_snapshot', 'shipping_snapshot', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'currency', 'status', 'placed_at', 'expires_at', 'delivered_at', 'marketing_visit_id'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fraud_hold' => 'boolean',
            'return_window_days' => 'integer',
            'commission_checked_at' => 'immutable_datetime',
            'shipping_address_snapshot' => 'array',
            'shipping_snapshot' => 'array',
            'referral_snapshot' => 'array',
            'rule_snapshot' => 'array',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->orderBy('id');
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id');
    }
}
