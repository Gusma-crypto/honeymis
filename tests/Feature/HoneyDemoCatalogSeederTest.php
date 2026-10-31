<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Actions\AdjustInventory;
use App\Models\User;
use Database\Seeders\HoneyDemoCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class HoneyDemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_is_buyable_and_rerun_preserves_existing_products_and_consumed_stock(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $existing = ProductVariant::factory()->withStock(7)->create();
        $this->seed(HoneyDemoCatalogSeeder::class);
        $demo = Product::where('sku', 'DEMO-MADU-HUTAN')->firstOrFail();
        $variant = $demo->variants()->firstOrFail();
        $demo->update(['name' => 'Nama demo yang telah diedit']);
        app(AdjustInventory::class)->handle($admin, $variant, -2, 'Pemakaian stok uji', 'demo-test-consumption');
        $this->seed(HoneyDemoCatalogSeeder::class);

        $this->assertDatabaseCount('products', 9);
        $this->assertDatabaseCount('product_variants', 17);
        $this->assertSame('Nama demo yang telah diedit', $demo->fresh()->name);
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $variant->id, 'on_hand' => 23]);
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $existing->id, 'on_hand' => 7]);
        $this->assertDatabaseCount('inventory_movements', 17);
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('commission_rules', 0);
        $this->get(route('catalog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('shop/catalog')->has('products.data', 9));
    }

    public function test_demo_seeding_refuses_a_public_production_target(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config(['app.url' => 'https://shop.example.com']);
        $this->expectException(RuntimeException::class);
        app(HoneyDemoCatalogSeeder::class)->run();
    }
}
