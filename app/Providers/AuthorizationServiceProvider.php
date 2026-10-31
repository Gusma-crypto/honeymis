<?php

namespace App\Providers;

use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Customer;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Domains\Orders\Models\ShippingSetting;
use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PayoutPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ShippingSettingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ShippingSetting::class, ShippingSettingPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Partner::class, PartnerPolicy::class);
        Gate::policy(PayoutRequest::class, PayoutPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            return $user->checkPermissionTo($ability, 'web') ? true : null;
        });
    }
}
