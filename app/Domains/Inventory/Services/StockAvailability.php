<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use DomainException;

final class StockAvailability
{
    public function ensureReservable(int $onHand, int $reserved, int $requested): void
    {
        if ($onHand < 0 || $reserved < 0 || $reserved > $onHand || $requested < 1 || $requested > $onHand - $reserved) {
            throw new DomainException('Insufficient stock or invalid reservation.');
        }
    }
}
