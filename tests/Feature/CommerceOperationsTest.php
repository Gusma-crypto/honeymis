<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Actions\ManageOrder;
use App\Domains\Orders\Actions\PlaceOrder;
use App\Domains\Orders\Actions\SubmitPaymentProof;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payment_and_fulfillment_complete_without_duplicate_stock_or_paid_events(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $buyer = User::factory()->create();
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        $store = User::factory()->create();
        $store->assignRole('store_admin');
        $order = $this->placed($buyer);
        $proofKey = (string) Str::uuid();
        $file = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');
        app(SubmitPaymentProof::class)->handle($buyer, $order, $file, $proofKey);
        app(SubmitPaymentProof::class)->handle($buyer, $order, $file, $proofKey);
        $verifyKey = (string) Str::uuid();

        app(ManageOrder::class)->handle($finance, $order, 'verify', $verifyKey);
        app(ManageOrder::class)->handle($finance, $order, 'verify', $verifyKey);
        app(ManageOrder::class)->handle($store, $order, 'process', (string) Str::uuid());
        $shipKey = (string) Str::uuid();
        app(ManageOrder::class)->handle($store, $order, 'ship', $shipKey, ['courier' => 'Test Courier', 'tracking_number' => 'TEST-001']);
        app(ManageOrder::class)->handle($store, $order, 'ship', $shipKey, ['courier' => 'Test Courier', 'tracking_number' => 'TEST-001']);
        app(ManageOrder::class)->handle($store, $order, 'deliver', (string) Str::uuid());

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $order->items()->firstOrFail()->variant_id, 'on_hand' => 9, 'reserved' => 0]);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertSame(1, DB::table('outbox_messages')->where('event_type', 'orders.state_changed')->whereJsonContains('payload->to', 'paid')->count());
        $this->assertDatabaseCount('commission_entries', 0);
    }

    public function test_payment_proof_is_private_and_other_customer_cannot_read_order_or_file(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->placed($buyer);
        $this->actingAs($buyer)->post(route('payments.store', $order), ['proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $payment = $order->payments()->firstOrFail();
        Storage::disk('local')->assertExists($payment->proof_storage_key);

        $this->get(route('payments.proof', $payment))->assertOk();
        $this->actingAs($other)->get(route('orders.show', $order))->assertForbidden();
        $this->get(route('payments.proof', $payment))->assertForbidden();
    }

    public function test_invalid_proof_type_is_rejected(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $order = $this->placed($buyer);

        $this->actingAs($buyer)->post(route('payments.store', $order), ['proof' => UploadedFile::fake()->create('attack.html', 10, 'text/html'), 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('proof');
        $this->assertSame('pending', $order->payments()->firstOrFail()->status);
    }

    public function test_cancellation_releases_stock_only_once_and_cannot_be_verified_later(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        $buyer = User::factory()->create();
        $order = $this->placed($buyer);
        $key = (string) Str::uuid();

        app(ManageOrder::class)->handle($buyer, $order, 'cancel', $key);
        app(ManageOrder::class)->handle($buyer, $order, 'cancel', $key);

        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $order->items()->firstOrFail()->variant_id, 'on_hand' => 10, 'reserved' => 0]);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->actingAs($finance)->withSession(['auth.password_confirmed_at' => time()])->patch(route('orders.update', $order), ['operation' => 'verify', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
    }

    public function test_store_admin_cannot_verify_payment_and_unpaid_order_cannot_ship(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $buyer = User::factory()->create();
        $order = $this->placed($buyer);
        $store = User::factory()->create();
        $store->assignRole('store_admin');

        $this->actingAs($store)->withSession(['auth.password_confirmed_at' => time()])->patch(route('orders.update', $order), ['operation' => 'verify', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->patch(route('orders.update', $order), ['operation' => 'ship', 'courier' => 'Test', 'tracking_number' => '123', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_expiry_releases_stock_but_does_not_cancel_a_submitted_proof(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $buyer = User::factory()->create();
        $order = $this->placed($buyer);
        $other = User::factory()->create();
        $review = $this->placed($other);
        app(SubmitPaymentProof::class)->handle($other, $review, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), (string) Str::uuid());
        $this->travel(25)->hours();

        $this->artisan('honey:expire-orders')->assertSuccessful();
        $this->artisan('honey:expire-orders')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('pending_payment', $review->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'order_expired')->count());
    }

    public function test_rejected_payment_can_be_resubmitted_as_a_new_attempt(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $buyer = User::factory()->create();
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        $order = $this->placed($buyer);
        $proof = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');
        app(SubmitPaymentProof::class)->handle($buyer, $order, $proof, (string) Str::uuid());
        app(ManageOrder::class)->handle($finance, $order, 'reject', (string) Str::uuid());

        app(SubmitPaymentProof::class)->handle($buyer, $order, $proof, (string) Str::uuid());
        app(ManageOrder::class)->handle($finance, $order, 'verify', (string) Str::uuid());

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['rejected', 'verified'], $order->payments()->pluck('status')->all());
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_expired_order_rejects_proof_upload_without_storing_a_file(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $order = $this->placed($buyer);
        $order->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($buyer)->post(route('payments.store', $order), ['proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('proof');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('pending', $order->payments()->firstOrFail()->status);
    }

    private function placed(User $buyer): Order
    {
        $variant = ProductVariant::factory()->withStock()->create();
        app(CartManager::class)->setQuantity($buyer, $variant->id, 1);
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));

        return app(PlaceOrder::class)->handle($buyer, ['recipient' => 'Test', 'phone' => '08123', 'province' => 'Test', 'city' => 'Test', 'district' => 'Test', 'postal_code' => '12345', 'address' => 'Test address'], $quote['fingerprint'], (string) Str::uuid());
    }
}
