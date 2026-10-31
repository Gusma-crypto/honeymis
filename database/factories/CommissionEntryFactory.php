<?php

namespace Database\Factories;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionEntry>
 */
class CommissionEntryFactory extends Factory
{
    protected $model = CommissionEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['order_item_id' => OrderItem::factory(), 'beneficiary_partner_id' => Partner::factory()->active(), 'rule_id' => CommissionRule::factory(), 'level' => 1, 'base_amount' => 140000, 'amount' => 14000, 'reversed_amount' => 0, 'offset_amount' => 0, 'status' => 'pending', 'unique_key' => fake()->unique()->uuid()];
    }
}
