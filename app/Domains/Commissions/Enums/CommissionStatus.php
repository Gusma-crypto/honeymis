<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Enums;

use App\Domains\Shared\Contracts\TransitionState;

enum CommissionStatus: string implements TransitionState
{
    case Pending = 'pending';
    case Available = 'available';
    case Reserved = 'reserved';
    case PaidOut = 'paid_out';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function canTransitionTo(TransitionState $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Available, self::Cancelled], true),
            self::Available => in_array($target, [self::Reserved, self::Reversed], true),
            self::Reserved => in_array($target, [self::Available, self::PaidOut], true),
            self::PaidOut => in_array($target, [self::Reversed], true),
            self::Cancelled => false,
            self::Reversed => false,
        };
    }
}
