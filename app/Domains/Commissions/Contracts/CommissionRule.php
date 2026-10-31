<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Contracts;

use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Shared\ValueObjects\Idr;
use DateTimeImmutable;

interface CommissionRule
{
    public function calculate(Idr $netMerchandise, RuleVersion $rule, DateTimeImmutable $placedAt): Idr;
}
