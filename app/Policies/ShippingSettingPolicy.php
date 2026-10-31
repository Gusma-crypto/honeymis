<?php

namespace App\Policies;

use App\Models\User;

class ShippingSettingPolicy
{
    public function manage(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->hasRole('super_admin');
    }
}
