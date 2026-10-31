<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Contracts\PaymentAdapter;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Shared\ValueObjects\Idr;
use DomainException;

final class ManualPaymentAdapter implements PaymentAdapter
{
    public function provider(): string
    {
        return 'manual';
    }

    public function initialStatus(Idr $amount): PaymentStatus
    {
        if ($amount->amount < 1) {
            throw new DomainException('Payment amount must be positive.');
        }

        return PaymentStatus::Pending;
    }
}
