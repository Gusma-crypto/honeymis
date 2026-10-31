<?php

namespace App\Domains\Orders\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $order_item_id
 * @property string $idempotency_key
 * @property string|null $request_fingerprint
 * @property int $amount
 * @property string $reason
 * @property string $status
 * @property int|null $processed_by
 * @property CarbonImmutable|null $processed_at
 */
#[Fillable(['order_id', 'order_item_id', 'idempotency_key', 'request_fingerprint', 'amount', 'reason', 'status', 'processed_by', 'processed_at'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
