<?php

namespace App\Policies;

use App\Domains\Payouts\Models\PayoutRequest;
use App\Models\User;

class PayoutPolicy
{
    public function view(User $user, PayoutRequest $payout): bool
    {
        return $user->isActive() && ($payout->partner->user_id === $user->id || $user->can('payouts.review'));
    }

    public function review(User $user, PayoutRequest $payout): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->can('payouts.review') && $payout->partner->user_id !== $user->id;
    }
}
