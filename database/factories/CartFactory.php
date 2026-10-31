<?php

namespace Database\Factories;

use App\Domains\Orders\Models\Cart;
use App\Domains\Orders\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'token_hash' => hash('sha256', fake()->uuid()), 'expires_at' => now()->addDays(30)];
    }
}
