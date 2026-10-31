<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Enums;

use App\Domains\Shared\Contracts\TransitionState;

enum PayoutStatus: string implements TransitionState
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Failed = 'failed';

    public function canTransitionTo(TransitionState $target): bool
    {
        return match ($this) {
            self::Requested => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => in_array($target, [self::Paid, self::Failed], true),
            self::Rejected => false,
            self::Paid => false,
            self::Failed => false,
        };
    }
}
