<?php

declare(strict_types=1);

namespace App\Domains\Reports\Services;

use DateTimeImmutable;
use DomainException;

final readonly class DateRange
{
    public function __construct(public DateTimeImmutable $from, public DateTimeImmutable $until)
    {
        if ($until <= $from) {
            throw new DomainException('Report range must have a positive duration.');
        }
    }
}
