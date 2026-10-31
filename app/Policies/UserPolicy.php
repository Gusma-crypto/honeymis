<?php

namespace App\Policies;

use App\Domains\Shared\Enums\SystemPermission;
use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->can(SystemPermission::ManageUserAccess->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, User $subject): bool
    {
        return $user->isActive() && ($user->is($subject) || $user->can(SystemPermission::ManageUserAccess->value));
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->isActive() && $user->is($subject)
            && ! $subject->roles()->where('name', '!=', SystemRole::Customer->value)->exists()
            && ! DB::table('audit_logs')->where('actor_id', $subject->id)->exists()
            && ! DB::table('customers')->where('user_id', $subject->id)->exists()
            && ! DB::table('partners')->where('user_id', $subject->id)->exists();
    }

    public function updateAccess(User $user, User $subject): bool
    {
        return $this->viewAny($user) && ! $user->is($subject);
    }
}
