<?php

namespace App\Domains\Orders\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $commission_rule_id
 * @property array<string, mixed>|null $commission_snapshot
 * @property int $id
 * @property int $order_id
 * @property int $variant_id
 * @property string $sku_snapshot
 * @property string $name_snapshot
 * @property array<string, string> $attributes_snapshot
 * @property int $quantity
 * @property int $unit_price_snapshot
 * @property int $discount_snapshot
 * @property int $cost_snapshot
 * @property bool $commission_eligible_snapshot
 */
#[Fillable(['order_id', 'variant_id', 'commission_rule_id', 'commission_snapshot', 'sku_snapshot', 'name_snapshot', 'attributes_snapshot', 'quantity', 'unit_price_snapshot', 'discount_snapshot', 'cost_snapshot', 'commission_eligible_snapshot'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'commission_snapshot' => 'array',
            'attributes_snapshot' => 'array',
            'quantity' => 'integer',
            'unit_price_snapshot' => 'integer',
            'discount_snapshot' => 'integer',
            'cost_snapshot' => 'integer',
            'commission_eligible_snapshot' => 'boolean',
        ];
    }
}
