<?php

namespace Database\Factories;

use App\Domains\Partners\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'referral_code' => strtoupper(fake()->unique()->bothify('HC??????????')), 'status' => 'pending'];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active', 'approved_at' => now()]);
    }
}
