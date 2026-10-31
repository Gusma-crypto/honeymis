<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalTestingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function enableLocalMode(): void
    {
        config(['auth.local_testing_bypass' => true, 'app.url' => 'http://127.0.0.1:8000']);
        if (config('database.default') === 'sqlite') {
            config(['database.connections.sqlite.host' => '127.0.0.1']);
        }
        $this->seed(RolePermissionSeeder::class);
    }

    /** @return array<string, string> */
    private function registration(): array
    {
        return ['name' => 'Tester Honey', 'email' => 'tester.honey@gmail.com', 'whatsapp_number' => '081234567890', 'password' => 'LocalHoney123!', 'password_confirmation' => 'LocalHoney123!'];
    }

    public function test_local_registration_can_checkout_without_falsely_verifying_identity_or_granting_admin_access(): void
    {
        $this->enableLocalMode();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
        $variant = ProductVariant::factory()->withStock()->create();

        $this->post(route('register.store'), [...$this->registration(), 'email_verified_at' => now()->toIso8601String(), 'whatsapp_verified_at' => now()->toIso8601String(), 'roles' => ['super_admin']])->assertRedirect();

        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->whatsapp_verified_at);
        $this->assertSame('6281234567890', $user->whatsapp_number);
        $this->assertSame(['customer'], $user->getRoleNames()->all());
        Notification::assertNothingSent();
        $this->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.catalog.index'))->assertForbidden();
        $this->get(route('cart.index'))->assertOk();
        $this->put(route('cart.update'), ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk();
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($user));
        $this->post(route('checkout.store'), ['recipient' => 'Tester', 'phone' => '081234567890', 'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong', 'postal_code' => '40132', 'address' => 'Alamat pengujian', 'quote_fingerprint' => $quote['fingerprint'], 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('pending_payment', Order::firstOrFail()->status);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('commission_entries', 0);
    }

    public function test_normal_registration_still_requires_email_verification(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->registration())->assertRedirect();

        $user = User::firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get(route('cart.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_disabling_local_mode_restores_verification_for_existing_test_accounts(): void
    {
        $this->enableLocalMode();
        $user = User::factory()->unverified()->create();
        $user->assignRole('customer');
        $this->actingAs($user)->get(route('cart.index'))->assertOk();

        config(['auth.local_testing_bypass' => false]);

        $this->get(route('cart.index'))->assertRedirect(route('verification.notice'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /** @return array<string, array{string}> */
    public static function nonLocalContexts(): array
    {
        return ['public URL' => ['url'], 'remote database' => ['database'], 'public host' => ['host'], 'remote client' => ['client']];
    }

    #[DataProvider('nonLocalContexts')]
    public function test_local_mode_is_ignored_outside_loopback_context(string $context): void
    {
        $this->enableLocalMode();
        $user = User::factory()->unverified()->create();
        $user->assignRole('customer');
        $this->actingAs($user);
        if ($context === 'url') {
            config(['app.url' => 'https://shop.example.test']);
        } elseif ($context === 'database') {
            config(['database.connections.'.config('database.default').'.host' => 'db.example.test']);
        } elseif ($context === 'client') {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
        }
        $url = $context === 'host' ? 'http://shop.example.test/cart' : route('cart.index');

        $this->get($url)->assertRedirect(route('verification.notice'));
    }

    /** @return array<string, array{string}> */
    public static function privilegedRoles(): array
    {
        return ['owner' => ['super_admin'], 'store' => ['store_admin'], 'finance' => ['finance']];
    }

    #[DataProvider('privilegedRoles')]
    public function test_privileged_accounts_cannot_skip_email_verification(string $role): void
    {
        $this->enableLocalMode();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->unverified()->create();
        $user->assignRole(['customer', $role]);

        $this->actingAs($user)->get(route('cart.index'))->assertRedirect(route('verification.notice'));
    }

    /** @return array<string, array{string}> */
    public static function phoneFormats(): array
    {
        return ['national' => ['081234567890'], 'international' => ['+62 812-3456-7890'], 'digits' => ['6281234567890']];
    }

    #[DataProvider('phoneFormats')]
    public function test_customer_can_log_in_by_normalized_whatsapp_number_in_local_mode(string $number): void
    {
        $this->enableLocalMode();
        $user = User::factory()->unverified()->create(['whatsapp_number' => '6281234567890']);
        $user->assignRole('customer');

        $this->post(route('login.store'), ['email' => $number, 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->get(route('cart.index'))->assertOk();
    }

    public function test_whatsapp_login_still_requires_the_correct_password_and_active_account(): void
    {
        $this->enableLocalMode();
        $user = User::factory()->create(['whatsapp_number' => '6281234567890']);
        $user->assignRole('customer');
        $this->post(route('login.store'), ['email' => '081234567890', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->forceFill(['status' => 'suspended'])->save();

        $this->post(route('login.store'), ['email' => '081234567890', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unverified_whatsapp_cannot_be_used_for_login_when_local_mode_is_off(): void
    {
        $user = User::factory()->create(['whatsapp_number' => '6281234567890']);

        $this->post(route('login.store'), ['email' => '081234567890', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_verified_whatsapp_can_be_used_without_local_mode(): void
    {
        $user = User::factory()->create(['whatsapp_number' => '6281234567890', 'whatsapp_verified_at' => now()]);

        $this->post(route('login.store'), ['email' => '+6281234567890', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_normalized_whatsapp_numbers_are_unique_across_formats(): void
    {
        $this->enableLocalMode();
        $this->post(route('register.store'), $this->registration())->assertRedirect();
        $this->post(route('logout'));

        $this->post(route('register.store'), [...$this->registration(), 'email' => 'another@gmail.com', 'whatsapp_number' => '+62 812-3456-7890'])->assertSessionHasErrors('whatsapp_number');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_invalid_whatsapp_input_is_rejected_without_creating_account(): void
    {
        $this->enableLocalMode();

        $this->post(route('register.store'), [...$this->registration(), 'whatsapp_number' => 'not-a-number'])->assertSessionHasErrors('whatsapp_number');
        $this->post(route('register.store'), [...$this->registration(), 'whatsapp_number' => ['unexpected']])->assertSessionHasErrors('whatsapp_number');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_local_production_password_validation_does_not_call_external_services(): void
    {
        $this->enableLocalMode();
        app()->detectEnvironment(fn (): string => 'production');
        Http::preventStrayRequests();
        $token = (string) Str::uuid();

        $this->withSession(['_token' => $token])->post(route('register.store'), [...$this->registration(), '_token' => $token])->assertRedirect();

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_public_production_readiness_rejects_enabled_bypass_flag(): void
    {
        config(['auth.local_testing_bypass' => true]);

        $this->artisan('honey:check-production')->expectsOutputToContain('GAGAL Bypass verifikasi uji coba nonaktif')->assertFailed();
    }

    public function test_whatsapp_login_rate_limit_is_shared_across_number_formats(): void
    {
        $this->enableLocalMode();
        $user = User::factory()->create(['whatsapp_number' => '6281234567890']);
        $user->assignRole('customer');
        foreach (['081234567890', '+6281234567890', '6281234567890', '0812-3456-7890', '+62 812 3456 7890'] as $number) {
            $this->post(route('login.store'), ['email' => $number, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => '081234567890', 'password' => 'password'])->assertTooManyRequests();

        $this->assertGuest();
    }
}
