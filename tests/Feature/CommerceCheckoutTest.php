<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Actions\PlaceOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommerceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_is_searchable_and_hides_inactive_products_and_costs(): void
    {
        $active = ProductVariant::factory()->withStock()->create();
        $inactive = ProductVariant::factory()->withStock()->create();
        $inactive->product->update(['status' => 'inactive']);

        $this->get(route('catalog.index', ['search' => $active->product->name]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('shop/catalog')
            ->has('products.data', 1)->where('products.data.0.id', $active->product_id)->missing('products.data.0.variants.0.cost'));
        $this->get(route('catalog.show', $inactive->product->slug))->assertNotFound();
    }

    public function test_guest_cannot_modify_cart_or_checkout(): void
    {
        $this->put(route('cart.update'), ['variant_id' => 1, 'quantity' => 1])->assertRedirect(route('login'));
        $this->post(route('checkout.store'), [])->assertRedirect(route('login'));
    }

    public function test_cart_quantity_is_persistent_and_cannot_add_inactive_or_oversold_items(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->withStock(2)->create();
        $this->actingAs($user)->put(route('cart.update'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->put(route('cart.update'), ['variant_id' => $variant->id, 'quantity' => 3])->assertSessionHasErrors('quantity');
        $variant->update(['status' => 'inactive']);
        $this->put(route('cart.update'), ['variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
        $this->put(route('cart.update'), ['variant_id' => $variant->id, 'quantity' => 0])->assertRedirect();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_snapshots_integer_totals_and_reserves_stock_once_on_retry(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->withStock(5)->create();
        $cart = app(CartManager::class);
        $cart->setQuantity($user, $variant->id, 2);
        $quote = app(CheckoutQuote::class)->calculate($cart->current($user));
        $key = (string) Str::uuid();
        $data = [...$this->address(), 'quote_fingerprint' => $quote['fingerprint'], 'idempotency_key' => $key];

        $this->actingAs($user)->post(route('checkout.store'), $data)->assertRedirect();
        $this->post(route('checkout.store'), $data)->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(300000, $order->subtotal);
        $this->assertSame(20000, $order->discount_total);
        $this->assertSame(300000, $order->grand_total);
        $this->assertSame('Penerima', $order->shipping_address_snapshot['recipient']);
        $this->assertSame(20000, $order->items()->firstOrFail()->discount_snapshot);
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $variant->id, 'on_hand' => 5, 'reserved' => 2]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('commission_entries', 0);
        $variant->update(['price' => 999999]);
        $this->assertSame(150000, $order->items()->firstOrFail()->unit_price_snapshot);
    }

    public function test_checkout_rejects_changed_prices_and_rolls_back_all_effects(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->withStock()->create();
        app(CartManager::class)->setQuantity($user, $variant->id, 1);
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($user));
        $variant->update(['price' => 180000]);

        $this->actingAs($user)->post(route('checkout.store'), [...$this->address(), 'quote_fingerprint' => $quote['fingerprint'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('quote_fingerprint');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $variant->id, 'reserved' => 0]);
    }

    public function test_checkout_rejects_a_replayed_key_with_another_address(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->withStock()->create();
        app(CartManager::class)->setQuantity($user, $variant->id, 1);
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($user));
        $key = (string) Str::uuid();
        app(PlaceOrder::class)->handle($user, $this->address(), $quote['fingerprint'], $key);
        $this->expectException(ValidationException::class);

        app(PlaceOrder::class)->handle($user, [...$this->address(), 'recipient' => 'Berbeda'], $quote['fingerprint'], $key);
    }

    public function test_checkout_validates_address_and_rejects_browser_totals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkout.store'), ['grand_total' => 1])->assertSessionHasErrors(['recipient', 'phone', 'province', 'city', 'district', 'postal_code', 'address', 'quote_fingerprint', 'idempotency_key', 'grand_total']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_stock_that_was_reserved_by_another_order(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->withStock(1)->create();
        app(CartManager::class)->setQuantity($user, $variant->id, 1);
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($user));
        $variant->inventory->update(['reserved' => 1]);

        $this->actingAs($user)->post(route('checkout.store'), [...$this->address(), 'quote_fingerprint' => $quote['fingerprint'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_checkout_redirects_to_cart_without_a_redirect_loop(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('checkout.index'))->assertRedirect(route('cart.index'))->assertSessionHasErrors('cart');
    }

    /** @return array{recipient: string, phone: string, province: string, city: string, district: string, postal_code: string, address: string} */
    private function address(): array
    {
        return ['recipient' => 'Penerima', 'phone' => '08123456789', 'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong', 'postal_code' => '40132', 'address' => 'Alamat pengujian nomor 1'];
    }
}
