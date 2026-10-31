<?php

namespace Database\Factories;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'amount' => 1000, 'reason' => 'Permintaan pengujian', 'status' => 'requested', 'idempotency_key' => fake()->unique()->uuid()];
    }
}
