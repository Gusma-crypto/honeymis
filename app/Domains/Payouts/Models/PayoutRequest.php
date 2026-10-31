<?php

namespace App\Domains\Payouts\Models;

use App\Domains\Partners\Models\Partner;
use Carbon\CarbonImmutable;
use Database\Factories\PayoutRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_id
 * @property string $idempotency_key
 * @property string|null $request_fingerprint
 * @property int $amount
 * @property string $status
 * @property array{bank_name: string, account_name: string, account_number: string} $bank_account_encrypted
 * @property string|null $bank_last4
 * @property string|null $proof_storage_key
 * @property string|null $transfer_reference
 * @property CarbonImmutable $requested_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_at
 * @property string|null $rejection_reason
 * @property Partner $partner
 */
#[Fillable(['partner_id', 'idempotency_key', 'request_fingerprint', 'amount', 'status', 'bank_account_encrypted', 'bank_last4', 'proof_storage_key', 'transfer_reference', 'requested_at', 'approved_by', 'approved_at', 'paid_at', 'rejection_reason'])]
#[Hidden(['bank_account_encrypted', 'request_fingerprint', 'proof_storage_key'])]
class PayoutRequest extends Model
{
    /** @use HasFactory<PayoutRequestFactory> */
    use HasFactory;

    protected static function newFactory(): PayoutRequestFactory
    {
        return PayoutRequestFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'bank_account_encrypted' => 'encrypted:array',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
