<?php

namespace App\Actions\Fortify;

use App\Domains\Shared\Services\LocalTestingAccess;
use App\Domains\Shared\ValueObjects\WhatsAppNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Fortify;

class AuthenticateActiveUser
{
    public function __construct(private Timebox $timebox) {}

    public function handle(Request $request): ?User
    {
        return $this->timebox->call(function () use ($request): ?User {
            $identifier = trim($request->string(Fortify::username())->toString());
            $number = str_contains($identifier, '@') ? null : WhatsAppNumber::normalize($identifier);
            $user = $number === null ? User::where('email', $identifier)->first() : User::where('whatsapp_number', $number)->first();
            $password = $request->string('password')->toString();
            if ($user === null || ! Hash::check($password, $user->password) || ! $user->isActive()) {
                return null;
            }
            if ($number !== null && $user->whatsapp_verified_at === null && ! app(LocalTestingAccess::class)->allows($user)) {
                return null;
            }
            if (config('hashing.rehash_on_login', true) && Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }

            return $user;
        }, 200000);
    }
}
