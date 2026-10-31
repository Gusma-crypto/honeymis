<?php

namespace App\Domains\Orders\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property int $amount
 * @property string $status
 * @property string $idempotency_key
 * @property string|null $proof_storage_key
 * @property string|null $proof_hash
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property Order $order
 */
#[Fillable(['order_id', 'provider', 'provider_reference', 'amount', 'status', 'idempotency_key', 'proof_storage_key', 'proof_hash', 'verified_by', 'verified_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'verified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
