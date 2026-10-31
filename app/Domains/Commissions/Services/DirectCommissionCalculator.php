<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Services;

use App\Domains\Commissions\Contracts\CommissionRule;
use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Shared\ValueObjects\Idr;
use DateTimeImmutable;
use DomainException;

final class DirectCommissionCalculator implements CommissionRule
{
    public function calculate(Idr $netMerchandise, RuleVersion $rule, DateTimeImmutable $placedAt): Idr
    {
        if (! $rule->effectiveAt($placedAt)) {
            throw new DomainException('Rule is not effective at order placement.');
        }

        $amount = $rule->fixedAmount > 0 ? $rule->fixedAmount : $netMerchandise->basisPoints($rule->rateBps)->amount;

        return new Idr(min($amount, $rule->capAmount, $netMerchandise->amount));
    }
}
