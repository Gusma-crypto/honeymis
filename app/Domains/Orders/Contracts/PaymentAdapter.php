<?php

declare(strict_types=1);

namespace App\Domains\Orders\Contracts;

use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Shared\ValueObjects\Idr;

interface PaymentAdapter
{
    public function provider(): string;

    public function initialStatus(Idr $amount): PaymentStatus;
}
