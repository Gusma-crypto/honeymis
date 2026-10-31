<?php

namespace App\Domains\Orders\Models;

use Database\Factories\ShippingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $mode
 * @property int $manual_amount
 * @property string $manual_label
 * @property string|null $api_key
 * @property int|null $origin_id
 * @property string $couriers
 * @property int $packaging_weight_g
 * @property string $revision
 */
#[Fillable(['mode', 'manual_amount', 'manual_label', 'api_key', 'origin_id', 'couriers', 'packaging_weight_g', 'revision', 'updated_by'])]
#[Hidden(['api_key'])]
class ShippingSetting extends Model
{
    /** @use HasFactory<ShippingSettingFactory> */
    use HasFactory;

    public $incrementing = false;

    protected static function newFactory(): ShippingSettingFactory
    {
        return ShippingSettingFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'manual_amount' => 'integer', 'origin_id' => 'integer', 'packaging_weight_g' => 'integer'];
    }
}
