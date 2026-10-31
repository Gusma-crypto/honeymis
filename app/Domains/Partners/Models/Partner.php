<?php

namespace App\Domains\Partners\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $referral_code
 * @property int|null $sponsor_partner_id
 * @property string $status
 * @property CarbonImmutable|null $approved_at
 * @property User $user
 */
#[Fillable(['user_id', 'referral_code', 'sponsor_partner_id', 'status', 'approved_at'])]
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    protected static function newFactory(): PartnerFactory
    {
        return PartnerFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
