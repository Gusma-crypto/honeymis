<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $challengedUserId = $request->session()->get('login.id');
        $challengedUser = is_int($challengedUserId) || is_string($challengedUserId) ? User::whereKey($challengedUserId)->first() : null;
        if (($user !== null && ! $user->isActive()) || ($challengedUser !== null && ! $challengedUser->isActive())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Akun Anda dinonaktifkan. Hubungi pengelola toko.');
        }

        $response = $next($request);
        if ($request->user() !== null && ! $request->user()->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Akun Anda dinonaktifkan. Hubungi pengelola toko.');
        }

        return $response;
    }
}
