<?php

namespace App\Domains\Partners\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReferralAttributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_id
 * @property int|null $customer_id
 * @property string $token_hash
 * @property string $source
 * @property CarbonImmutable $expires_at
 * @property Partner $partner
 */
#[Fillable(['partner_id', 'customer_id', 'token_hash', 'source', 'expires_at'])]
class ReferralAttribution extends Model
{
    /** @use HasFactory<ReferralAttributionFactory> */
    use HasFactory;

    protected static function newFactory(): ReferralAttributionFactory
    {
        return ReferralAttributionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
