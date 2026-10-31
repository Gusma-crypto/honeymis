<?php

namespace Database\Factories;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['category_id' => Category::factory(), 'sku' => fake()->unique()->bothify('P-####??'), 'name' => fake()->words(3, true), 'slug' => fake()->unique()->slug(), 'description' => 'Madu contoh untuk pengujian.', 'status' => 'active', 'weight_g' => 500, 'commission_eligible' => false];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
