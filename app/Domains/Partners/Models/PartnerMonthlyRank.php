<?php

namespace App\Domains\Partners\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_id
 * @property string $period_start
 * @property string $rank
 * @property int $personal_sales
 * @property int $team_sales
 * @property CarbonImmutable $calculated_at
 * @property Partner $partner
 */
#[Fillable(['partner_id', 'period_start', 'rank', 'personal_sales', 'team_sales', 'calculated_at'])]
class PartnerMonthlyRank extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'personal_sales' => 'integer',
            'team_sales' => 'integer',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
