<?php

declare(strict_types=1);

namespace App\Domains\Partners\Services;

use DateTimeImmutable;

final class ReferralEligibility
{
    public function eligible(int $buyerUserId, int $partnerUserId, string $partnerStatus, DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool
    {
        return $buyerUserId !== $partnerUserId && $partnerStatus === 'active' && $expiresAt > $now;
    }
}
