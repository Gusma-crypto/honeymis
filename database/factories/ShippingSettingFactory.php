<?php

namespace Database\Factories;

use App\Domains\Orders\Models\ShippingSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ShippingSetting> */
class ShippingSettingFactory extends Factory
{
    protected $model = ShippingSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['id' => 1, 'mode' => 'manual', 'manual_amount' => 20000, 'manual_label' => 'Pengiriman reguler', 'origin_id' => null, 'couriers' => 'jne:jnt:pos', 'packaging_weight_g' => 100, 'revision' => (string) Str::uuid()];
    }
}
