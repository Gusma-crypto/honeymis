<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

use App\Domains\Shared\Contracts\TransitionState;

enum OrderStatus: string implements TransitionState
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function canTransitionTo(TransitionState $target): bool
    {
        return match ($this) {
            self::PendingPayment => in_array($target, [self::Paid, self::Cancelled], true),
            self::Paid => in_array($target, [self::Processing, self::Refunded], true),
            self::Processing => in_array($target, [self::Shipped, self::Refunded], true),
            self::Shipped => in_array($target, [self::Delivered, self::Refunded], true),
            self::Delivered => in_array($target, [self::Completed, self::Refunded], true),
            self::Completed => in_array($target, [self::Refunded], true),
            self::Cancelled => false,
            self::Refunded => false,
        };
    }
}
