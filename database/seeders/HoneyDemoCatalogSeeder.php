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
use RuntimeException;

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
        DB::transaction(function (): void {
            $actor = User::role('super_admin')->where('status', 'active')->whereNotNull('email_verified_at')->orderBy('id')->lockForUpdate()->firstOrFail();
            $products = app(SaveProduct::class);
            $inventory = app(AdjustInventory::class);
            $audit = app(AuditWriter::class);
            foreach ($this->catalog() as $item) {
                if (Product::where('sku', $item['sku'])->exists()) {
                    continue;
                }
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
        }, attempts: 3);
        Cache::forget('dashboard.products.v1');
        Cache::forget('dashboard.stock.v1');
    }

    /** @return list<array{sku: string, name: string, category: string, description: string, prices: array{int, int}, costs: array{int, int}}> */
    private function catalog(): array
    {
        return [
            ['sku' => 'DEMO-MADU-HUTAN', 'name' => 'Madu Hutan Nusantara', 'category' => 'nusantara', 'description' => 'Pilihan contoh madu hutan dalam dua ukuran botol.', 'prices' => [65000, 120000], 'costs' => [40000, 75000]],
            ['sku' => 'DEMO-MADU-RANDU', 'name' => 'Madu Bunga Randu', 'category' => 'flora', 'description' => 'Contoh madu bunga randu untuk katalog varian floral.', 'prices' => [45000, 85000], 'costs' => [28000, 52000]],
            ['sku' => 'DEMO-MADU-KELENGKENG', 'name' => 'Madu Bunga Kelengkeng', 'category' => 'flora', 'description' => 'Contoh produk madu kelengkeng dengan pilihan kemasan.', 'prices' => [60000, 110000], 'costs' => [38000, 70000]],
            ['sku' => 'DEMO-MADU-MULTIFLORA', 'name' => 'Madu Multiflora', 'category' => 'flora', 'description' => 'Contoh madu multiflora untuk simulasi pembelian harian.', 'prices' => [50000, 95000], 'costs' => [32000, 60000]],
            ['sku' => 'DEMO-MADU-KALIANDRA', 'name' => 'Madu Bunga Kaliandra', 'category' => 'flora', 'description' => 'Contoh madu kaliandra dalam botol 250 dan 500 gram.', 'prices' => [55000, 100000], 'costs' => [35000, 65000]],
            ['sku' => 'DEMO-MADU-AKASIA', 'name' => 'Madu Akasia', 'category' => 'flora', 'description' => 'Contoh madu akasia untuk pengujian pencarian produk.', 'prices' => [50000, 90000], 'costs' => [30000, 56000]],
            ['sku' => 'DEMO-MADU-SUMATRA', 'name' => 'Madu Hutan Sumatra', 'category' => 'nusantara', 'description' => 'Contoh lini madu Nusantara dengan nama varian Sumatra.', 'prices' => [70000, 130000], 'costs' => [45000, 82000]],
            ['sku' => 'DEMO-MADU-SUMBAWA', 'name' => 'Madu Sumbawa', 'category' => 'nusantara', 'description' => 'Contoh lini madu Nusantara dengan nama varian Sumbawa.', 'prices' => [75000, 140000], 'costs' => [48000, 88000]],
        ];
    }
}
