<?php

namespace App\Policies;

use App\Domains\Partners\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function view(User $user, Partner $partner): bool
    {
        return $user->isActive() && ($partner->user_id === $user->id || $user->can('partners.manage') || $user->can('wallets.view'));
    }

    public function requestPayout(User $user, Partner $partner): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $partner->user_id === $user->id && $partner->status === 'active' && $user->can('payouts.request-own');
    }
}
