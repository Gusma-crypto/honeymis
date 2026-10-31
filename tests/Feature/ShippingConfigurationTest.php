<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\ShippingSetting;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Domains\Orders\Services\ShippingSettings;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShippingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function settingsData(): array
    {
        return ['mode' => 'manual', 'manual_amount' => 27000, 'manual_label' => 'Kurir toko', 'api_key' => null, 'clear_api_key' => false, 'origin_id' => null, 'couriers' => 'jne:jnt:pos', 'packaging_weight_g' => 100, 'revision' => app(ShippingSettings::class)->current()->revision, 'idempotency_key' => (string) Str::uuid()];
    }

    /** @return array<string, string> */
    private function address(): array
    {
        return ['recipient' => 'Tester', 'phone' => '081234567890', 'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong', 'postal_code' => '40132', 'address' => 'Jalan pengujian 1'];
    }

    /** @return array<string, mixed> */
    private function destination(): array
    {
        return ['id' => 99, 'label' => 'Dago, Coblong, Bandung, Jawa Barat, 40132', 'province_name' => 'Jawa Barat', 'city_name' => 'Bandung', 'district_name' => 'Coblong', 'subdistrict_name' => 'Dago', 'zip_code' => '40132'];
    }

    private function apiMode(): ShippingSetting
    {
        Http::preventStrayRequests();

        return ShippingSetting::factory()->create(['mode' => 'api', 'api_key' => 'secret-test-api', 'origin_id' => 321]);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create();
        $variant = ProductVariant::factory()->withStock()->create(['product_id' => Product::factory()->create(['weight_g' => 500])->id, 'price' => 100000, 'discount_amount' => 0]);
        app(CartManager::class)->setQuantity($buyer, $variant->id, 2);
        $this->actingAs($buyer);

        return $buyer;
    }

    /** @return array<string, mixed> */
    private function option(): array
    {
        Http::fake([
            'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response(['meta' => ['status' => 'success'], 'data' => [$this->destination()]]),
            'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response(['meta' => ['status' => 'success'], 'data' => [['code' => 'jne', 'service' => 'REG', 'cost' => 12000, 'etd' => '2-3 hari']]]),
        ]);
        $this->getJson(route('shipping.destinations', ['search' => 'Dago']))->assertOk()->assertJsonPath('destinations.0.id', 99);

        return $this->postJson(route('shipping.quotes'), ['destination_id' => 99])->assertOk()->json('options.0');
    }

    public function test_admin_manual_tariff_is_transactional_idempotent_and_uses_integer_idr(): void
    {
        $this->administrator();
        $data = $this->settingsData();
        $this->put(route('admin.shipping-settings.update'), $data)->assertRedirect();
        $this->put(route('admin.shipping-settings.update'), $data)->assertRedirect();
        $this->assertDatabaseCount('shipping_settings', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $buyer = $this->buyer();

        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));

        $this->assertSame(27000, $quote['shipping_total']);
        $this->assertSame(227000, $quote['grand_total']);
        $this->assertFalse($quote['shipping_required']);
        $this->assertSame('Kurir toko', $quote['shipping']['service']);
    }

    public function test_api_key_is_encrypted_and_never_returned_by_settings_page(): void
    {
        $this->administrator();
        $this->put(route('admin.shipping-settings.update'), [...$this->settingsData(), 'api_key' => 'secret-test-api'])->assertRedirect();
        $this->assertNotSame('secret-test-api', DB::table('shipping_settings')->value('api_key'));
        $this->assertSame('secret-test-api', ShippingSetting::firstOrFail()->api_key);
        $this->assertArrayNotHasKey('api_key', ShippingSetting::firstOrFail()->toArray());

        $this->get(route('admin.commerce-settings'))->assertOk()->assertDontSee('secret-test-api')->assertInertia(fn (Assert $page) => $page->where('shippingSettings.has_api_key', true)->missing('shippingSettings.api_key'));
    }

    /** @return array<string, array{string}> */
    public static function deniedRoles(): array
    {
        return ['customer' => ['customer'], 'store admin' => ['store_admin'], 'finance' => ['finance']];
    }

    #[DataProvider('deniedRoles')]
    public function test_settings_and_key_access_are_restricted_to_owner_administrator(string $role): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->put(route('admin.shipping-settings.update'), $this->settingsData())->assertForbidden();
        $this->getJson(route('admin.shipping-settings.destinations', ['search' => 'Dago']))->assertForbidden();
        $this->assertDatabaseCount('shipping_settings', 0);
    }

    public function test_api_mode_requires_credentials_and_rejects_invalid_manual_amounts(): void
    {
        $this->administrator();
        $data = $this->settingsData();

        $this->put(route('admin.shipping-settings.update'), [...$data, 'mode' => 'api', 'origin_id' => 321])->assertSessionHasErrors('api_key');
        $this->put(route('admin.shipping-settings.update'), [...$data, 'manual_amount' => -1])->assertSessionHasErrors('manual_amount');
        $this->put(route('admin.shipping-settings.update'), [...$data, 'api_key' => 'invalid secret value', 'origin_id' => 0])->assertSessionHasErrors(['api_key', 'origin_id'])->assertSessionMissing('_old_input.api_key');
        $this->assertDatabaseCount('shipping_settings', 0);
    }

    public function test_stale_settings_cannot_overwrite_a_newer_revision(): void
    {
        $this->administrator();
        $data = $this->settingsData();
        $this->put(route('admin.shipping-settings.update'), $data)->assertRedirect();

        $this->put(route('admin.shipping-settings.update'), [...$data, 'manual_amount' => 5000, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('revision');
        $this->assertDatabaseHas('shipping_settings', ['manual_amount' => 27000]);
    }

    public function test_api_tariff_uses_real_weight_and_destination_and_is_snapshotted_without_calls_during_checkout(): void
    {
        $this->apiMode();
        $buyer = $this->buyer();
        $base = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));
        $this->assertTrue($base['shipping_required']);
        $option = $this->option();
        $this->assertSame(1100, $option['quote']['weight_g']);
        $this->assertSame(212000, $option['quote']['grand_total']);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/calculate/domestic-cost') && $request->hasHeader('key', 'secret-test-api') && (string) $request['weight'] === '1100' && (string) $request['origin'] === '321' && (string) $request['destination'] === '99');
        $key = (string) Str::uuid();
        $data = [...$this->address(), 'quote_fingerprint' => $option['quote']['fingerprint'], 'shipping_quote_token' => $option['token'], 'idempotency_key' => $key];

        $this->post(route('checkout.store'), $data)->assertRedirect();
        $this->post(route('checkout.store'), $data)->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $order = Order::firstOrFail();
        $this->assertSame(12000, $order->shipping_total);
        $this->assertSame(212000, $order->grand_total);
        $this->assertSame('REG', $order->getAttribute('shipping_snapshot')['service']);
        $this->assertSame(99, $order->getAttribute('shipping_snapshot')['destination']['id']);
        Http::assertSentCount(2);
    }

    public function test_api_checkout_rejects_missing_or_client_invented_tariffs(): void
    {
        $this->apiMode();
        $buyer = $this->buyer();
        $base = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));
        $data = [...$this->address(), 'quote_fingerprint' => $base['fingerprint'], 'idempotency_key' => (string) Str::uuid()];

        $this->post(route('checkout.store'), $data)->assertSessionHasErrors('shipping_quote_token');
        $this->post(route('checkout.store'), [...$data, 'shipping_total' => 0])->assertSessionHasErrors('shipping_total');
        $this->post(route('checkout.store'), [...$data, 'shipping_quote_token' => (string) Str::uuid()])->assertSessionHasErrors('shipping_quote_token');
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function invalidQuoteContexts(): array
    {
        return ['expired' => ['expired'], 'settings' => ['settings'], 'weight' => ['weight'], 'address' => ['address'], 'other owner' => ['owner']];
    }

    #[DataProvider('invalidQuoteContexts')]
    public function test_api_quote_cannot_be_reused_after_context_changes(string $context): void
    {
        $settings = $this->apiMode();
        $this->buyer();
        $option = $this->option();
        $address = $this->address();
        if ($context === 'expired') {
            $this->travel(11)->minutes();
        } elseif ($context === 'settings') {
            $settings->update(['revision' => (string) Str::uuid()]);
        } elseif ($context === 'weight') {
            Product::query()->update(['weight_g' => 1000]);
        } elseif ($context === 'address') {
            $address['city'] = 'Jakarta';
        } else {
            $this->buyer();
        }

        $this->post(route('checkout.store'), [...$address, 'quote_fingerprint' => $option['quote']['fingerprint'], 'shipping_quote_token' => $option['token'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('shipping_quote_token');

        $this->assertDatabaseCount('orders', 0);
        Http::assertSentCount(2);
    }

    /** @return array<string, array{string}> */
    public static function failedProviders(): array
    {
        return ['decimal amount' => ['decimal'], 'timeout' => ['timeout'], 'unavailable' => ['unavailable']];
    }

    #[DataProvider('failedProviders')]
    public function test_api_errors_and_invalid_amounts_do_not_fall_back_to_free_shipping(string $failure): void
    {
        $this->apiMode();
        $this->buyer();
        $cost = $failure === 'timeout' ? fn () => throw new ConnectionException('Connection failed') : Http::response(['meta' => ['status' => 'success'], 'data' => $failure === 'decimal' ? [['code' => 'jne', 'service' => 'REG', 'cost' => 12.5]] : []], $failure === 'unavailable' ? 503 : 200);
        Http::fake([
            'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response(['meta' => ['status' => 'success'], 'data' => [$this->destination()]]),
            'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => $cost,
        ]);
        $this->getJson(route('shipping.destinations', ['search' => 'Dago']))->assertOk();

        $this->postJson(route('shipping.quotes'), ['destination_id' => 99])->assertUnprocessable()->assertJsonValidationErrors('shipping')->assertDontSee('secret-test-api');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manual_mode_never_calls_the_provider_and_old_orders_keep_their_shipping_amount(): void
    {
        $this->administrator();
        Http::preventStrayRequests();
        $buyer = $this->buyer();
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));
        $this->post(route('checkout.store'), [...$this->address(), 'quote_fingerprint' => $quote['fingerprint'], 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $order = Order::firstOrFail();
        ShippingSetting::factory()->create(['manual_amount' => 55000]);

        $this->assertSame(20000, $order->fresh()->shipping_total);
        $this->assertSame(20000, $order->fresh()->getAttribute('shipping_snapshot')['amount']);
        Http::assertNothingSent();
    }
}
