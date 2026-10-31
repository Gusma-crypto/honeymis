<?php

namespace Database\Factories;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Wallet\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletLedgerEntry>
 */
class WalletLedgerEntryFactory extends Factory
{
    protected $model = WalletLedgerEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['commission_entry_id' => CommissionEntry::factory(), 'partner_id' => fn (array $attributes): int => CommissionEntry::where('id', $attributes['commission_entry_id'])->firstOrFail()->beneficiary_partner_id, 'transaction_key' => fake()->unique()->uuid(), 'idempotency_key' => fake()->unique()->uuid(), 'bucket' => 'pending', 'entry_type' => 'commission', 'amount_signed' => 14000, 'currency' => 'IDR', 'created_at' => now()];
    }
}
