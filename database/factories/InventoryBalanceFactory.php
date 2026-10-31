<?php

namespace Database\Factories;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBalance> */
class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['variant_id' => ProductVariant::factory(), 'on_hand' => 10, 'reserved' => 0];
    }
}
