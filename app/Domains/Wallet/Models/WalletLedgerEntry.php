<?php

namespace App\Domains\Wallet\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WalletLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $partner_id
 * @property int|null $commission_entry_id
 * @property int|null $payout_id
 * @property int|null $refund_id
 * @property int|null $reversal_of_id
 * @property string $transaction_key
 * @property string $idempotency_key
 * @property string $bucket
 * @property string $entry_type
 * @property int $amount_signed
 * @property string $currency
 * @property CarbonImmutable $created_at
 */
#[Fillable(['partner_id', 'commission_entry_id', 'payout_id', 'refund_id', 'reversal_of_id', 'transaction_key', 'idempotency_key', 'bucket', 'entry_type', 'amount_signed', 'currency', 'created_at'])]
class WalletLedgerEntry extends Model
{
    /** @use HasFactory<WalletLedgerEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function newFactory(): WalletLedgerEntryFactory
    {
        return WalletLedgerEntryFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_signed' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
