<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

use App\Domains\Shared\Contracts\TransitionState;

enum PaymentStatus: string implements TransitionState
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function canTransitionTo(TransitionState $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Submitted, self::Rejected], true),
            self::Submitted => in_array($target, [self::Verified, self::Rejected], true),
            self::Verified => in_array($target, [self::Refunded], true),
            self::Rejected => false,
            self::Refunded => false,
        };
    }
}
