<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Services;

use DomainException;

final class BalanceCalculator
{
    /** @param iterable<int> $signedAmounts */
    public function sum(iterable $signedAmounts): int
    {
        $balance = 0;
        foreach ($signedAmounts as $amount) {
            if (($amount > 0 && $balance > PHP_INT_MAX - $amount) || ($amount < 0 && $balance < PHP_INT_MIN - $amount)) {
                throw new DomainException('Ledger balance exceeds integer capacity.');
            }
            $balance += $amount;
        }

        return $balance;
    }
}
