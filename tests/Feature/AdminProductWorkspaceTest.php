<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\ProductSpreadsheet;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\FinanceTestCase;
use ZipArchive;

class AdminProductWorkspaceTest extends FinanceTestCase
{
    public function test_new_product_photo_is_saved_with_product_and_can_be_replaced_from_edit(): void
    {
        Storage::fake('public');
        $this->financeActors();
        $category = Category::factory()->create();
        $payload = $this->photoProductData($category->id);
        $payload['image'] = $this->photo();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.catalog.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::where('sku', 'PHOTO')->firstOrFail();
        $first = DB::table('product_images')->where('product_id', $product->id)->first();
        Storage::disk('public')->assertExists($first->storage_key);

        $payload = $this->photoProductData($category->id);
        $payload['_method'] = 'put';
        $payload['variants'][0]['id'] = $product->variants()->firstOrFail()->id;
        $payload['image'] = $this->photo();
        $payload['name'] = 'Madu Foto Baru';
        $this->post(route('admin.catalog.update', $product), $payload)->assertSessionHasNoErrors()->assertRedirect();

        $latest = DB::table('product_images')->where('product_id', $product->id)->orderByDesc('id')->first();
        $this->assertNotSame($first->storage_key, $latest->storage_key);
        Storage::disk('public')->assertExists($latest->storage_key);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Madu Foto Baru']);
        $this->get(route('admin.catalog.index', ['search' => 'PHOTO']))->assertInertia(fn (Assert $page) => $page->where('products.data.0.image_url', route('catalog.image', $latest->id)));
        unset($payload['image']);
        $this->post(route('admin.catalog.update', $product), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_invalid_photo_cannot_create_product(): void
    {
        Storage::fake('public');
        $this->financeActors();
        $category = Category::factory()->create();
        $payload = $this->photoProductData($category->id);
        $payload['image'] = UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.catalog.store'), $payload)->assertSessionHasErrors('image');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }

    public function test_failed_product_update_removes_new_upload_and_preserves_existing_data(): void
    {
        Storage::fake('public');
        $this->financeActors();
        $variant = ProductVariant::factory()->create();
        $other = ProductVariant::factory()->create();
        $payload = $this->photoProductData($variant->product->category_id);
        $payload['sku'] = $variant->product->sku;
        $payload['slug'] = $variant->product->slug;
        $payload['image'] = $this->photo();
        $payload['variants'][0]['id'] = $other->id;
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.catalog.update', $variant->product), $payload)->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
        $this->assertDatabaseCount('product_images', 0);
        $this->assertNotSame('Madu Foto', $variant->product->fresh()->name);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('honey.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC6kAAAAASUVORK5CYII='));
    }

    /** @return array<string, mixed> */
    private function photoProductData(int $categoryId): array
    {
        return ['category_id' => $categoryId, 'sku' => 'PHOTO', 'name' => 'Madu Foto', 'slug' => 'madu-foto', 'description' => 'Madu pilihan', 'status' => 'draft', 'weight_g' => 500,
            'variants' => [['sku' => 'PHOTO-500', 'label' => '500 gram', 'price' => 150000, 'member_price' => 125000, 'cost' => 80000, 'discount_amount' => 0, 'status' => 'draft']]];
    }

    public function test_categories_sort_stock_and_real_summary_are_returned(): void
    {
        $this->financeActors();
        $cheap = ProductVariant::factory()->withStock(12)->create(['price' => 100000, 'cost' => 60000]);
        ProductVariant::factory()->withStock(5)->create(['product_id' => $cheap->product_id, 'price' => 200000, 'cost' => 80000]);
        ProductVariant::factory()->withStock(3)->create(['price' => 300000, 'cost' => 100000]);
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.catalog.index', ['category' => $cheap->product->category_id, 'sort' => 'price_low']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/catalog')
            ->where('stats.total_products', 2)->where('stats.stock', 20)->where('stats.stock_value', 1420000)
            ->where('stats.sold', 0)->where('stats.bestseller', null)->has('products.data', 1)
            ->where('products.data.0.id', $cheap->product_id)->where('products.data.0.stock', 17));
    }

    public function test_archiving_preserves_product_variants_and_records_audit(): void
    {
        $this->financeActors();
        $variant = ProductVariant::factory()->withStock()->create();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('admin.catalog.archive', $variant->product))->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $variant->product_id, 'status' => 'inactive']);
        $this->assertModelExists($variant);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_archived', 'subject_id' => $variant->product_id]);
        $this->get(route('catalog.show', $variant->product->slug))->assertNotFound();
    }

    public function test_csv_imports_variants_atomically_and_does_not_overwrite_existing_products(): void
    {
        $this->financeActors();
        Category::factory()->create(['slug' => 'madu-murni']);
        $row = ['NEW', 'Madu Baru', 'madu-murni', 'NEW-250', '250 gram', '150000', '125000', '80000', '10000', '250', 'draft', '1', 'Madu pilihan'];
        $second = $row;
        $second[3] = 'NEW-500';
        $second[4] = '500 gram';
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.catalog.import'), ['file' => $this->csv([$row, $second])])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseHas('product_variants', ['sku' => 'NEW-250', 'member_price' => 125000]);
        $this->assertDatabaseHas('inventory_balances', ['on_hand' => 0, 'reserved' => 0]);
        $row[5] = '900000';
        $this->post(route('admin.catalog.import'), ['file' => $this->csv([$row])])->assertSessionHasErrors('file');
        $this->assertDatabaseHas('product_variants', ['sku' => 'NEW-250', 'price' => 150000]);
    }

    public function test_invalid_import_does_not_save_earlier_rows(): void
    {
        $this->financeActors();
        Category::factory()->create(['slug' => 'madu-murni']);
        $row = ['NEW', 'Madu Baru', 'madu-murni', 'NEW-250', '250 gram', '150000', '', '80000', '0', '250', 'draft', '0', 'Madu pilihan'];
        $invalid = $row;
        $invalid[0] = 'INVALID';
        $invalid[5] = '-100';
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.catalog.import'), ['file' => $this->csv([$row, $invalid])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_xlsx_import_supports_values_and_rejects_formulas(bool $formula): void
    {
        $this->financeActors();
        Category::factory()->create(['slug' => 'madu-murni']);
        $rows = [ProductSpreadsheet::Columns, ['XLS', 'Madu Excel', 'madu-murni', 'XLS-250', '250 gram', '150000', '', '80000', '0', '250', 'draft', '0', 'Madu pilihan']];
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row>';
            foreach ($row as $column => $value) {
                $xml .= '<c r="'.chr(65 + $column).($r + 1).'" t="inlineStr">'.($formula && $r === 1 && $column === 0 ? '<f>1+1</f>' : '').'<is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'product-test-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();
            $file = UploadedFile::fake()->createWithContent('products.xlsx', file_get_contents($path));
            $response = $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.catalog.import'), ['file' => $file]);
            if ($formula) {
                $response->assertSessionHasErrors(['file' => 'Rumus Excel tidak didukung. Tempel sebagai nilai sebelum mengimpor.']);
                $this->assertDatabaseCount('products', 0);
            } else {
                $response->assertSessionHasNoErrors()->assertRedirect();
                $this->assertDatabaseHas('products', ['sku' => 'XLS', 'status' => 'draft']);
            }
        } finally {
            unlink($path);
        }
    }

    public function test_export_applies_filters_and_escapes_spreadsheet_formulas(): void
    {
        $this->financeActors();
        $match = ProductVariant::factory()->create();
        $match->product->update(['name' => '=HYPERLINK("bad")']);
        ProductVariant::factory()->create();
        $response = $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.catalog.export', ['search' => $match->product->sku]))->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->assertSame(2, substr_count(trim($response->streamedContent()), "\n") + 1);
    }

    public function test_customer_cannot_import_export_or_archive(): void
    {
        $this->financeActors();
        $variant = ProductVariant::factory()->create();
        $this->actingAs($this->partner->user)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.catalog.export'))->assertForbidden();
        $this->post(route('admin.catalog.import'), [])->assertForbidden();
        $this->delete(route('admin.catalog.archive', $variant->product))->assertForbidden();
        $this->assertSame('active', $variant->product->fresh()->status);
    }

    public function test_member_price_applies_only_to_active_members_without_stacking_promotions(): void
    {
        $this->financeActors();
        $variant = ProductVariant::factory()->withStock(10)->create(['price' => 150000, 'member_price' => 120000, 'discount_amount' => 10000]);
        $buyer = $this->partner->user;
        app(CartManager::class)->setQuantity($buyer, $variant->id, 1);
        $cart = app(CartManager::class)->current($buyer);
        $this->assertSame(120000, app(CheckoutQuote::class)->calculate($cart)['lines'][0]['total']);
        $variant->update(['discount_amount' => 40000]);
        $this->assertSame(110000, app(CheckoutQuote::class)->calculate($cart)['lines'][0]['total']);
        $variant->update(['discount_amount' => 10000]);
        $this->partner->update(['status' => 'suspended']);
        $this->assertSame(140000, app(CheckoutQuote::class)->calculate($cart)['lines'][0]['total']);
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ProductSpreadsheet::Columns, escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('products.csv', $contents);
    }
}
