<?php

declare(strict_types=1);

namespace App\Domains\Commissions\ValueObjects;

use DateTimeImmutable;
use DomainException;

final readonly class RuleVersion
{
    public function __construct(
        public int $version,
        public int $rateBps,
        public int $fixedAmount,
        public int $capAmount,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil = null,
        public int $level = 1,
        public string $strategy = 'direct_v1',
    ) {
        if ($version < 1 || $level !== 1 || $strategy !== 'direct_v1') {
            throw new DomainException('Only versioned direct one-level rules are supported.');
        }
        if ($rateBps < 0 || $rateBps > 10000 || $fixedAmount < 0 || $capAmount < 0 || ($rateBps > 0 && $fixedAmount > 0)) {
            throw new DomainException('Invalid commission rate, fixed amount or cap.');
        }
        if ($validUntil !== null && $validUntil <= $validFrom) {
            throw new DomainException('Rule expiry must be after its effective date.');
        }
    }

    public function effectiveAt(DateTimeImmutable $placedAt): bool
    {
        return $placedAt >= $this->validFrom && ($this->validUntil === null || $placedAt < $this->validUntil);
    }
}
