<?php

namespace App\Domains\Commissions\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $version
 * @property int $referral_first_order_bps
 * @property list<int> $generation_rates
 * @property array<string, array{personal_sales: int, team_sales: int}> $rank_requirements
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['id', 'version', 'referral_first_order_bps', 'generation_rates', 'rank_requirements', 'updated_by'])]
class MlmProgramSetting extends Model
{
    protected $table = 'mlm_program_settings';

    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'referral_first_order_bps' => 'integer',
            'generation_rates' => 'array',
            'rank_requirements' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
