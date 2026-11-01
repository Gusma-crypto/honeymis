<?php

namespace App\Http\Middleware;

use App\Domains\Shared\Services\StoreSettings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreAvailable
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(StoreSettings::class)->current();
        config(['commerce.manual_payment_instructions' => $settings['payment_instructions']]);
        $isAdministrator = $request->user()?->can('users.manage-access') ?? false;
        $isRecoveryRoute = $request->is('login', 'logout', 'forgot-password', 'reset-password*', 'two-factor-challenge', 'user/confirm-password', 'branding/*');
        if (! $isAdministrator && ! $isRecoveryRoute && (! $settings['system_active'] || $settings['maintenance_mode'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sistem sedang dalam pemeliharaan. Silakan coba kembali nanti.'], 503, ['Retry-After' => '300']);
            }

            $response = Inertia::render('shop/unavailable', ['brandName' => $settings['brand_name']])->toResponse($request);
            $response->setStatusCode(503);
            $response->headers->set('Retry-After', '300');

            return $response;
        }
        if (! $settings['registration_enabled'] && $request->is('register')) {
            abort(403, 'Pendaftaran member baru sedang ditutup.');
        }

        return $next($request);
    }
}
