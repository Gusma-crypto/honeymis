<?php

namespace Database\Factories;

use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Models\ReferralAttribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralAttribution>
 */
class ReferralAttributionFactory extends Factory
{
    protected $model = ReferralAttribution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['partner_id' => Partner::factory()->active(), 'token_hash' => hash('sha256', fake()->unique()->uuid()), 'source' => 'referral_link', 'expires_at' => now()->addDays(30)];
    }
}
