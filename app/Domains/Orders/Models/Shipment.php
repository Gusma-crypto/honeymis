<?php

namespace App\Domains\Orders\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property string $courier
 * @property string|null $tracking_number
 * @property string $status
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 */
#[Fillable(['order_id', 'courier', 'tracking_number', 'status', 'shipped_at', 'delivered_at'])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
