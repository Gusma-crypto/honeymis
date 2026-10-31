<?php

namespace Database\Factories;

use App\Domains\Orders\Models\Customer;
use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'order_number' => 'HC-'.fake()->uuid(), 'idempotency_key' => fake()->uuid(), 'shipping_address_snapshot' => ['recipient' => 'Test', 'address' => 'Alamat test'], 'subtotal' => 150000, 'discount_total' => 10000, 'shipping_total' => 20000, 'tax_total' => 0, 'grand_total' => 160000, 'status' => 'pending_payment', 'placed_at' => now(), 'expires_at' => now()->addDay()];
    }
}
