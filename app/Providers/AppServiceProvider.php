<?php

namespace App\Providers;

use App\Domains\Orders\Contracts\PaymentAdapter;
use App\Domains\Orders\Services\ManualPaymentAdapter;
use App\Domains\Shared\Services\LocalTestingAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentAdapter::class, ManualPaymentAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (config('commerce.use_built_assets')) {
            Vite::useHotFile(storage_path('framework/vite-preview.hot'));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): ?Password {
            if (! app()->isProduction()) {
                return null;
            }
            $password = Password::min(12)->mixedCase()->letters()->numbers()->symbols();

            return app(LocalTestingAccess::class)->enabled() ? $password : $password->uncompromised();
        });
    }
}
