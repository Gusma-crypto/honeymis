<?php

namespace Database\Seeders;

use App\Domains\Commissions\Models\CommissionRule;
use Illuminate\Database\Seeder;

class FinanceSandboxSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Sandbox fixtures are restricted to local and testing environments.');
        }
        if (! CommissionRule::exists()) {
            CommissionRule::create(['version' => 1, 'strategy' => 'direct_v1', 'level' => 1, 'rate_bps' => 0, 'fixed_amount' => 0, 'cap_amount' => 1, 'valid_from' => now(), 'status' => 'draft']);
        }
    }
}
