<?php

namespace App\Domains\Commissions\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $version
 * @property string $strategy
 * @property int $level
 * @property int|null $product_id
 * @property int|null $category_id
 * @property int $rate_bps
 * @property int $fixed_amount
 * @property int $cap_amount
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property string $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 */
#[Fillable(['version', 'strategy', 'level', 'product_id', 'category_id', 'rate_bps', 'fixed_amount', 'cap_amount', 'valid_from', 'valid_until', 'status', 'approved_by', 'approved_at'])]
class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    protected static function newFactory(): CommissionRuleFactory
    {
        return CommissionRuleFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'level' => 'integer',
            'rate_bps' => 'integer',
            'fixed_amount' => 'integer',
            'cap_amount' => 'integer',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
