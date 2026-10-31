<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function manage(User $user): bool
    {
        return $user->isActive() && $user->can('catalog.manage');
    }
}
