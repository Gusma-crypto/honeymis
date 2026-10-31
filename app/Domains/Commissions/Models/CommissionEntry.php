<?php

namespace App\Domains\Commissions\Models;

use App\Domains\Orders\Models\OrderItem;
use Carbon\CarbonImmutable;
use Database\Factories\CommissionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_item_id
 * @property int $beneficiary_partner_id
 * @property int $rule_id
 * @property int $level
 * @property int $base_amount
 * @property int $amount
 * @property int $reversed_amount
 * @property int $offset_amount
 * @property string $status
 * @property CarbonImmutable|null $eligible_at
 * @property CarbonImmutable|null $available_at
 * @property string $unique_key
 * @property OrderItem $item
 */
#[Fillable(['order_item_id', 'beneficiary_partner_id', 'rule_id', 'level', 'base_amount', 'amount', 'reversed_amount', 'offset_amount', 'status', 'eligible_at', 'available_at', 'unique_key'])]
class CommissionEntry extends Model
{
    /** @use HasFactory<CommissionEntryFactory> */
    use HasFactory;

    protected static function newFactory(): CommissionEntryFactory
    {
        return CommissionEntryFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'base_amount' => 'integer',
            'amount' => 'integer',
            'reversed_amount' => 'integer',
            'offset_amount' => 'integer',
            'eligible_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
