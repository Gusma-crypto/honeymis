<?php

namespace Database\Seeders;

use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Inventory\Actions\AdjustInventory;
use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new \RuntimeException('Demo catalog is restricted to local development.');
        }
        $admin = User::role(SystemRole::SuperAdmin->value)->where('status', 'active')->firstOrFail();
        if (Product::where('sku', 'DEMO-HONEY')->exists()) {
            return;
        }
        $category = Category::firstOrCreate(['slug' => 'demo-madu'], ['name' => 'Madu Demo', 'status' => 'active']);
        $product = app(SaveProduct::class)->handle($admin, null, ['category_id' => $category->id, 'sku' => 'DEMO-HONEY', 'name' => 'Madu Demo', 'slug' => 'demo-madu', 'description' => 'Data demonstrasi, bukan informasi produk riil.', 'status' => 'active', 'weight_g' => 500, 'variants' => [['sku' => 'DEMO-HONEY-500', 'label' => '500 gram', 'price' => 150000, 'cost' => 80000, 'discount_amount' => 0, 'status' => 'active']]]);
        app(AdjustInventory::class)->handle($admin, $product->variants()->firstOrFail(), 10, 'Demo initial stock', 'demo-honey-initial');
    }
}
