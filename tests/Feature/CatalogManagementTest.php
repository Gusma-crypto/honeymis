<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Actions\AdjustInventory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_variants_with_zero_stock_and_can_update_prices_without_rewriting_orders(): void
    {
        $admin = $this->storeAdmin();
        $category = Category::factory()->create();
        $payload = $this->productData($category->id);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.catalog.store'), $payload)->assertRedirect();

        $product = Product::firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $variant->id, 'on_hand' => 0, 'reserved' => 0]);
        $payload['variants'][0]['id'] = $variant->id;
        $payload['variants'][0]['price'] = 170000;
        $this->put(route('admin.catalog.update', $product), $payload)->assertRedirect();
        $this->assertSame(170000, $variant->fresh()->price);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_customer_cannot_manage_catalog_or_inventory(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $buyer = User::factory()->create();
        $buyer->assignRole('customer');

        $this->actingAs($buyer)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.catalog.index'))->assertForbidden();
        $this->get(route('admin.inventory.index'))->assertForbidden();
        $this->post(route('admin.catalog.store'), [])->assertForbidden();
    }

    public function test_price_and_discount_validation_reject_invalid_money(): void
    {
        $admin = $this->storeAdmin();
        $category = Category::factory()->create();
        $payload = $this->productData($category->id);
        $payload['variants'][0]['price'] = 1.5;
        $payload['variants'][0]['discount_amount'] = 99999;

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.catalog.store'), $payload)->assertSessionHasErrors(['variants.0.price', 'variants.0.discount_amount']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_cannot_modify_a_variant_from_a_different_product(): void
    {
        $admin = $this->storeAdmin();
        $variant = ProductVariant::factory()->withStock()->create();
        $other = ProductVariant::factory()->withStock()->create();
        $payload = $this->productData($variant->product->category_id);
        $payload['sku'] = $variant->product->sku;
        $payload['slug'] = $variant->product->slug;
        $payload['variants'][0]['id'] = $other->id;

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put(route('admin.catalog.update', $variant->product), $payload)->assertNotFound();
        $this->assertSame(150000, $other->fresh()->price);
    }

    public function test_inventory_adjustment_is_idempotent_and_cannot_consume_reserved_stock(): void
    {
        $admin = $this->storeAdmin();
        $variant = ProductVariant::factory()->withStock(5)->create();
        $variant->inventory->update(['reserved' => 3]);
        $key = (string) Str::uuid();
        app(AdjustInventory::class)->handle($admin, $variant, 2, 'Restock', $key);
        app(AdjustInventory::class)->handle($admin, $variant, 2, 'Restock', $key);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.inventory.update', $variant), ['quantity_delta' => -5, 'reason' => 'Invalid reduction', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('quantity_delta');

        $this->assertSame(7, $variant->inventory()->firstOrFail()->on_hand);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_product_image_upload_and_category_activation_are_authorized(): void
    {
        Storage::fake('public');
        $admin = $this->storeAdmin();
        $variant = ProductVariant::factory()->withStock()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.catalog.image', $variant->product), ['image' => UploadedFile::fake()->createWithContent('honey.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC6kAAAAASUVORK5CYII='))])->assertRedirect();
        $this->assertDatabaseCount('product_images', 1);
        $this->put(route('admin.categories.update', $variant->product->category), ['name' => 'Inactive', 'slug' => $variant->product->category->slug, 'status' => 'inactive'])->assertRedirect();
        $this->get(route('catalog.show', $variant->product->slug))->assertNotFound();
    }

    public function test_admin_can_create_a_category_without_an_existing_model(): void
    {
        $admin = $this->storeAdmin();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.categories.store'), ['name' => 'Herbal', 'slug' => 'herbal', 'status' => 'active'])->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Herbal', 'status' => 'active']);
    }

    /** @return array{category_id: int, sku: string, name: string, slug: string, description: string, status: string, weight_g: int, variants: list<array<string, int|string>>} */
    private function productData(int $categoryId): array
    {
        return ['category_id' => $categoryId, 'sku' => 'MADU', 'name' => 'Madu', 'slug' => 'madu', 'description' => 'Deskripsi', 'status' => 'active', 'weight_g' => 500,
            'variants' => [['sku' => 'MADU-500', 'label' => '500 gram', 'price' => 150000, 'cost' => 80000, 'discount_amount' => 10000, 'status' => 'active']]];
    }

    private function storeAdmin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('store_admin');

        return $admin;
    }
}
