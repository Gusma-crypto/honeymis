<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Services;

use App\Domains\Shared\ValueObjects\Idr;
use DomainException;

final class PayoutEligibility
{
    public function ensureRequestable(Idr $requested, int $available, Idr $minimum, bool $partnerActive): void
    {
        if (! $partnerActive || $requested->amount < 1 || $requested->amount < $minimum->amount || $requested->amount > $available) {
            throw new DomainException('Payout violates partner, minimum or available-balance requirements.');
        }
    }
}
