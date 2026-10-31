<?php

namespace Database\Factories;

use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Models\PayoutRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutRequest>
 */
class PayoutRequestFactory extends Factory
{
    protected $model = PayoutRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['partner_id' => Partner::factory()->active(), 'idempotency_key' => fake()->unique()->uuid(), 'amount' => 50000, 'status' => 'requested', 'requested_at' => now(), 'bank_account_encrypted' => ['bank_name' => 'Bank Test', 'account_name' => 'Test', 'account_number' => '1234567890'], 'bank_last4' => '7890'];
    }
}
