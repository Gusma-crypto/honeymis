<?php

namespace Database\Factories;

use App\Domains\Orders\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => '081234567890'];
    }
}
