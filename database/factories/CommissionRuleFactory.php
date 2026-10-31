<?php

namespace Database\Factories;

use App\Domains\Commissions\Models\CommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['version' => fake()->unique()->numberBetween(1, 1000000), 'strategy' => 'direct_v1', 'level' => 1, 'rate_bps' => 1000, 'fixed_amount' => 0, 'cap_amount' => 1000000, 'valid_from' => now()->subDay(), 'status' => 'draft'];
    }
}
