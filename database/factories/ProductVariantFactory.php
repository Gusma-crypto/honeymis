<?php

namespace Database\Factories;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'sku' => fake()->unique()->bothify('V-####??'), 'attributes' => ['label' => '500 gram'], 'price' => 150000, 'cost' => 80000, 'discount_amount' => 10000, 'status' => 'active'];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }

    public function withStock(int $quantity = 10): static
    {
        return $this->afterCreating(function (ProductVariant $variant) use ($quantity): void {
            InventoryBalance::firstOrCreate(['variant_id' => $variant->id], ['on_hand' => $quantity, 'reserved' => 0]);
        });
    }
}
