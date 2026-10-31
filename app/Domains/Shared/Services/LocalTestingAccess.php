<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;

class LocalTestingAccess
{
    public function enabled(): bool
    {
        if (! config('auth.local_testing_bypass', false)) {
            return false;
        }
        $loopback = ['127.0.0.1', 'localhost', '::1', '[::1]'];
        $database = (string) config('database.default');
        if (! in_array(parse_url((string) config('app.url'), PHP_URL_HOST), $loopback, true)
            || ! in_array(config('database.connections.'.$database.'.host'), $loopback, true)) {
            return false;
        }

        return in_array(request()->getHost(), $loopback, true) && in_array(request()->ip(), $loopback, true);
    }

    public function allows(User $user): bool
    {
        return $this->enabled() && $user->isActive()
            && $user->hasAnyRole([SystemRole::Customer->value, SystemRole::Partner->value])
            && ! $user->hasAnyRole([SystemRole::SuperAdmin->value, SystemRole::StoreAdmin->value, SystemRole::Finance->value]);
    }
}
