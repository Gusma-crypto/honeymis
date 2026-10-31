<?php

namespace Database\Factories;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'courier' => 'Manual', 'tracking_number' => fake()->uuid(), 'status' => 'pending'];
    }
}
