<?php

namespace Database\Seeders;

use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Inventory\Actions\AdjustInventory;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HoneyDemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! (
            in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['127.0.0.1', 'localhost', '::1'], true)
            && in_array(config('database.connections.'.config('database.default').'.host'), ['127.0.0.1', 'localhost', '::1'], true)
        )) {
            throw new RuntimeException('Demo honey data may only be seeded on a local installation.');
        }
        /** @var array{actor: User, images: list<array{product: Product, source: string}>} $seed */
        $seed = DB::transaction(function (): array {
            $actor = User::role('super_admin')->where('status', 'active')->whereNotNull('email_verified_at')->orderBy('id')->lockForUpdate()->firstOrFail();
            $products = app(SaveProduct::class);
            $inventory = app(AdjustInventory::class);
            $audit = app(AuditWriter::class);
            $images = [];
            foreach ($this->catalog() as $item) {
                $product = Product::where('sku', $item['sku'])->first();
                if ($product === null) {
                    $categorySlug = $item['category'] === 'nusantara' ? 'demo-madu-nusantara' : 'demo-madu-flora';
                    $category = Category::firstOrCreate(['slug' => $categorySlug], [
                        'name' => $item['category'] === 'nusantara' ? 'Demo Madu Nusantara' : 'Demo Madu Flora', 'status' => 'active',
                    ]);
                    if ($category->wasRecentlyCreated) {
                        $audit->record($actor->id, 'demo_category_created', 'categories', $category->id, 'honey-demo:category:'.$categorySlug);
                    }
                    $variants = [];
                    foreach ([250, 500] as $index => $grams) {
                        $variants[] = ['sku' => $item['sku'].'-'.$grams, 'label' => $grams.' gram', 'price' => $item['prices'][$index],
                            'cost' => $item['costs'][$index], 'discount_amount' => $index === 1 ? 5000 : 0, 'status' => 'active'];
                    }
                    $product = $products->handle($actor, null, [
                        'category_id' => $category->id, 'sku' => $item['sku'], 'slug' => strtolower($item['sku']), 'name' => 'Demo '.$item['name'],
                        'description' => $item['description'].' Data contoh untuk simulasi katalog dan pembelian. Harga serta stok bukan penawaran penjualan riil.',
                        'status' => 'active', 'weight_g' => 500, 'commission_eligible' => true, 'variants' => $variants,
                    ]);
                    foreach ($product->variants()->orderBy('id')->get() as $variant) {
                        $inventory->handle($actor, $variant, 25, 'Stok awal produk madu dummy', 'honey-demo:v1:'.$variant->sku);
                    }
                }
                $images[] = ['product' => $product, 'source' => $item['image']];
            }

            return ['actor' => $actor, 'images' => $images];
        }, attempts: 3);
        $audit = app(AuditWriter::class);
        foreach ($seed['images'] as $image) {
            $this->storeImage($seed['actor'], $image['product'], $image['source'], $audit);
        }
        Cache::forget('dashboard.products.v1');
        Cache::forget('dashboard.stock.v1');
    }

    private function storeImage(User $actor, Product $product, string $source, AuditWriter $audit): void
    {
        if (DB::table('product_images')->where('product_id', $product->id)->exists()) {
            return;
        }
        $sourcePath = public_path($source);
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Demo product image is missing: '.$source);
        }
        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new RuntimeException('Demo product image could not be read: '.$source);
        }
        $storageKey = 'demo/products/'.Str::slug($product->sku).'.jpg';
        if (! Storage::disk('public')->put($storageKey, $contents)) {
            throw new RuntimeException('Demo product image could not be stored: '.$storageKey);
        }
        try {
            DB::transaction(function () use ($actor, $audit, $product, $storageKey): void {
                if (DB::table('product_images')->where('product_id', $product->id)->exists()) {
                    return;
                }
                $imageId = DB::table('product_images')->insertGetId([
                    'product_id' => $product->id, 'storage_key' => $storageKey, 'sort_order' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $audit->record($actor->id, 'demo_product_image_added', 'products', $product->id, 'honey-demo:image:'.$imageId);
            });
        } catch (Throwable $exception) {
            if (! DB::table('product_images')->where('storage_key', $storageKey)->exists()) {
                Storage::disk('public')->delete($storageKey);
            }
            throw $exception;
        }
    }

    /** @return list<array{sku: string, name: string, category: string, description: string, prices: array{int, int}, costs: array{int, int}, image: string}> */
    private function catalog(): array
    {
        return [
            ['sku' => 'DEMO-MADU-HUTAN', 'name' => 'Madu Hutan Nusantara', 'category' => 'nusantara', 'description' => 'Pilihan contoh madu hutan dalam dua ukuran botol.', 'prices' => [65000, 120000], 'costs' => [40000, 75000], 'image' => 'honey-hero.jpg'],
            ['sku' => 'DEMO-MADU-RANDU', 'name' => 'Madu Bunga Randu', 'category' => 'flora', 'description' => 'Contoh madu bunga randu untuk katalog varian floral.', 'prices' => [45000, 85000], 'costs' => [28000, 52000], 'image' => 'honey-product.jpg'],
            ['sku' => 'DEMO-MADU-KELENGKENG', 'name' => 'Madu Bunga Kelengkeng', 'category' => 'flora', 'description' => 'Contoh produk madu kelengkeng dengan pilihan kemasan.', 'prices' => [60000, 110000], 'costs' => [38000, 70000], 'image' => 'honey-hero.jpg'],
            ['sku' => 'DEMO-MADU-MULTIFLORA', 'name' => 'Madu Multiflora', 'category' => 'flora', 'description' => 'Contoh madu multiflora untuk simulasi pembelian harian.', 'prices' => [50000, 95000], 'costs' => [32000, 60000], 'image' => 'honey-product.jpg'],
            ['sku' => 'DEMO-MADU-KALIANDRA', 'name' => 'Madu Bunga Kaliandra', 'category' => 'flora', 'description' => 'Contoh madu kaliandra dalam botol 250 dan 500 gram.', 'prices' => [55000, 100000], 'costs' => [35000, 65000], 'image' => 'honey-hero.jpg'],
            ['sku' => 'DEMO-MADU-AKASIA', 'name' => 'Madu Akasia', 'category' => 'flora', 'description' => 'Contoh madu akasia untuk pengujian pencarian produk.', 'prices' => [50000, 90000], 'costs' => [30000, 56000], 'image' => 'honey-product.jpg'],
            ['sku' => 'DEMO-MADU-SUMATRA', 'name' => 'Madu Hutan Sumatra', 'category' => 'nusantara', 'description' => 'Contoh lini madu Nusantara dengan nama varian Sumatra.', 'prices' => [70000, 130000], 'costs' => [45000, 82000], 'image' => 'honey-hero.jpg'],
            ['sku' => 'DEMO-MADU-SUMBAWA', 'name' => 'Madu Sumbawa', 'category' => 'nusantara', 'description' => 'Contoh lini madu Nusantara dengan nama varian Sumbawa.', 'prices' => [75000, 140000], 'costs' => [48000, 88000], 'image' => 'honey-product.jpg'],
        ];
    }
}
