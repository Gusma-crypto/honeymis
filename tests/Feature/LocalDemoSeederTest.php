<?php

namespace Tests\Feature;

use App\Domains\Orders\Models\Order;
use App\Domains\Reports\Services\CommerceReport;
use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\FinanceTestCase;

class LocalDemoSeederTest extends FinanceTestCase
{
    public function test_local_demo_seeding_creates_accounts_catalog_images_and_reportable_orders(): void
    {
        $this->travelTo(now('Asia/Jakarta')->startOfDay()->addHours(12)->utc());
        config(['commerce.demo_password' => 'demo12345']);
        Storage::fake('public');

        $this->seed(LocalDemoSeeder::class);
        $this->seed(LocalDemoSeeder::class);

        $admin = User::where('email', 'admin@honey-demo.test')->firstOrFail();
        $finance = User::where('email', 'finance@honey-demo.test')->firstOrFail();
        $partner = User::where('email', 'partner@honey-demo.test')->firstOrFail();
        $buyer = User::where('email', 'customer@honey-demo.test')->firstOrFail();

        $this->assertTrue(Hash::check('demo12345', $admin->password));
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue($admin->hasRole(SystemRole::SuperAdmin->value));
        $this->assertTrue($finance->hasRole(SystemRole::Finance->value));
        $this->assertTrue($partner->hasRole(SystemRole::Partner->value));
        $this->assertTrue($buyer->hasRole(SystemRole::Customer->value));
        $this->assertDatabaseCount('products', 8);
        $this->assertDatabaseCount('product_images', 8);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('commission_entries', 2);
        $this->assertDatabaseHas('commission_entries', [
            'commission_type' => 'direct',
            'level' => 0,
            'status' => 'pending',
        ]);

        $images = DB::table('product_images')->orderBy('id')->get();
        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->storage_key);
        }
        $imageId = (int) DB::table('product_images')->orderBy('id')->value('id');
        $this->assertGreaterThan(0, $imageId);
        $this->get('/product-images/'.$imageId)->assertOk();

        $paidOrder = Order::where('idempotency_key', 'local-demo-order:paid:v1')->firstOrFail();
        $cancelledOrder = Order::where('idempotency_key', 'local-demo-order:cancelled:v1')->firstOrFail();
        $payment = $paidOrder->payments()->firstOrFail();
        $this->assertDatabaseHas('payments', ['order_id' => $payment->order_id, 'status' => 'verified']);
        $this->assertDatabaseHas('payments', ['order_id' => $cancelledOrder->id, 'status' => 'rejected']);
        Storage::disk('local')->assertExists($payment->proof_storage_key);

        $reportDate = now('Asia/Jakarta')->toDateString();
        $summary = app(CommerceReport::class)->summary($finance, $reportDate, $reportDate);
        $this->assertSame(2, $summary['orders']);
        $this->assertSame(1, $summary['paidOrders']);
        $this->assertSame(0, $summary['pendingOrders']);
        $this->assertSame(85000, $summary['gross']);
        $this->assertSame(1, $summary['activePartners']);
        $this->assertSame(32000, $summary['finance']['estimatedMargin']);
    }

    public function test_local_demo_seeding_requires_an_explicit_password(): void
    {
        config(['commerce.demo_password' => null]);
        try {
            app(LocalDemoSeeder::class)->run();
            $this->fail('Seeding must require an explicit demo password.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Set DEMO_PASSWORD in the local Docker environment before seeding demo accounts.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_local_demo_seeding_refuses_a_production_target(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config(['app.url' => 'https://shop.example.com', 'commerce.demo_password' => 'demo12345']);

        try {
            app(LocalDemoSeeder::class)->run();
            $this->fail('Demo seeding must reject production targets.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Demo accounts and transactions may only be seeded into a local Docker or test database.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
