<?php

namespace App\Domains\Commissions\Services;

use App\Domains\Shared\ValueObjects\Idr;

class GenerationCommissionCalculator
{
    /**
     * @param  list<int>  $rates
     * @return array<int, array{level: int, rate_bps: int, amount: int, type: string}>
     */
    public function calculate(int $base, array $rates, int $firstOrderRate, bool $firstOrder): array
    {
        $result = [];
        for ($level = 1; $level <= 5; $level++) {
            $rate = $level === 1 && $firstOrder ? $firstOrderRate : ($rates[$level - 1] ?? 0);
            $result[$level] = ['level' => $level, 'rate_bps' => $rate, 'amount' => (new Idr($base))->basisPoints($rate)->amount, 'type' => $level === 1 && $firstOrder ? 'referral' : 'network'];
        }

        return $result;
    }
}
