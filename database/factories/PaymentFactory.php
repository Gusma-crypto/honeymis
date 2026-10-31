<?php

namespace Database\Factories;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'provider' => 'manual', 'amount' => 160000, 'status' => 'pending', 'idempotency_key' => fake()->uuid()];
    }
}
