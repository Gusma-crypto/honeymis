<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Catalog\Actions\ImportProducts;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\AdminCatalogQuery;
use App\Domains\Catalog\Services\ProductSpreadsheet;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\ProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogManagementController extends Controller
{
    public function index(Request $request, ListFilters $lists, AdminCatalogQuery $catalog): Response
    {
        Gate::authorize('manage', Product::class);

        $query = $catalog->filtered($request);
        $totals = DB::query()->fromSub($catalog->base()->toBase(), 'catalog_totals')->selectRaw('COUNT(*) as total_products, SUM(stock) as stock, SUM(stock_value) as stock_value, SUM(sold) as sold')->first();
        $best = $catalog->base()->orderByDesc('sold')->orderBy('products.id')->first();
        $categories = Category::orderBy('name')->get(['id', 'name', 'slug', 'status']);
        $counts = Product::select('category_id')->selectRaw('COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $categories->each(fn (Category $category) => $category->setAttribute('product_count', (int) ($counts[$category->id] ?? 0)));

        return Inertia::render('admin/catalog', [
            'listFilters' => $lists->values($request),
            'filters' => [...$lists->values($request), 'category' => $request->input('category', ''), 'label' => $request->input('label', ''), 'sort' => $request->input('sort', 'newest'), 'per_page' => $request->integer('per_page', 8)],
            'products' => $query->with('variants.inventory')->paginate($request->integer('per_page', 8))->withQueryString()->through(function (Product $product): array {
                return [...$product->toArray(), 'image_url' => $product->getAttribute('first_image_id') === null ? null : route('catalog.image', $product->getAttribute('first_image_id'))];
            }),
            'categories' => $categories,
            'stats' => [
                'total_products' => (int) $totals->total_products, 'stock' => (int) $totals->stock, 'stock_value' => (int) $totals->stock_value, 'sold' => (int) $totals->sold,
                'new_this_month' => Product::where('created_at', '>=', now('Asia/Jakarta')->startOfMonth()->utc())->count(),
                'bestseller' => (int) ($best?->getAttribute('sold') ?? 0) > 0 ? ['name' => $best->name, 'sold' => (int) $best->getAttribute('sold')] : null,
                'active' => Product::where('status', 'active')->count(), 'inactive' => Product::where('status', 'inactive')->count(), 'draft' => Product::where('status', 'draft')->count(),
            ],
        ]);
    }

    public function archive(Request $request, Product $product, AuditWriter $audit): RedirectResponse
    {
        Gate::authorize('manage', Product::class);
        DB::transaction(function () use ($request, $product, $audit): void {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->update(['status' => 'inactive']);
            $audit->record($request->user()->id, 'product_archived', 'products', $product->id, 'product-archive:'.Str::uuid());
        });

        return back()->with('success', 'Produk dinonaktifkan. Riwayat transaksi tetap tersimpan.');
    }

    public function import(Request $request, ImportProducts $products): RedirectResponse
    {
        Gate::authorize('manage', Product::class);
        $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:xlsx,csv,txt']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $count = $products->handle($request->user(), $file);

        return back()->with('success', $count.' produk berhasil diimpor dengan stok awal nol.');
    }

    public function export(Request $request, AdminCatalogQuery $catalog): StreamedResponse
    {
        Gate::authorize('manage', Product::class);
        $query = $catalog->filtered($request)->with(['variants', 'category']);

        return response()->streamDownload(function () use ($query, $request): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Export produk gagal.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ProductSpreadsheet::Columns, escape: '');
            if (! $request->boolean('template')) {
                foreach ($query->lazy(200) as $product) {
                    foreach ($product->variants as $variant) {
                        $row = [$product->sku, $product->name, $product->category->slug, $variant->sku, $variant->attributes['label'] ?? $variant->sku, $variant->price, $variant->member_price ?? '', $variant->cost, $variant->discount_amount, $product->weight_g, $product->status, $product->commission_eligible ? 1 : 0, $product->description ?? ''];
                        fputcsv($stream, array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $row), escape: '');
                    }
                }
            }
            fclose($stream);
        }, $request->boolean('template') ? 'template-produk.csv' : 'produk.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(ProductRequest $request, SaveProduct $products, AuditWriter $audit): RedirectResponse
    {
        $this->saveWithPhoto($request, null, $products, $audit);

        return to_route('admin.catalog.index');
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $products, AuditWriter $audit): RedirectResponse
    {
        $this->saveWithPhoto($request, $product, $products, $audit);

        return to_route('admin.catalog.index');
    }

    private function saveWithPhoto(ProductRequest $request, ?Product $product, SaveProduct $products, AuditWriter $audit): void
    {
        $file = $request->file('image');
        $path = $file instanceof UploadedFile ? $file->store('products', 'public') : null;
        abort_if($path === false, 500, 'Penyimpanan foto gagal. Silakan coba kembali.');

        try {
            DB::transaction(function () use ($request, $product, $products, $audit, $path): void {
                $saved = $products->handle($request->user(), $product, $request->productData());
                if ($path !== null) {
                    $imageId = DB::table('product_images')->insertGetId(['product_id' => $saved->id, 'storage_key' => $path, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    $audit->record($request->user()->id, 'product_photo_saved', 'products', $saved->id, 'product-photo:'.$imageId);
                }
            }, attempts: 3);
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }
    }

    public function category(CategoryRequest $request, AuditWriter $audit, ?Category $category = null): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit, $category): void {
            $category = $category === null ? Category::create($request->validated()) : tap(Category::whereKey($category->id)->lockForUpdate()->firstOrFail(), fn ($record) => $record->update($request->validated()));
            $audit->record($request->user()->id, 'category_saved', 'categories', $category->id, 'category:'.Str::uuid(), ['status' => $category->status]);
        });

        return to_route('admin.catalog.index');
    }

    public function image(Request $request, Product $product, AuditWriter $audit): RedirectResponse
    {
        Gate::authorize('manage', Product::class);
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $file = $request->file('image');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->store('products', 'public');
        abort_if($path === false, 500, 'Penyimpanan gambar gagal.');
        try {
            DB::transaction(function () use ($request, $product, $path, $audit): void {
                $imageId = DB::table('product_images')->insertGetId(['product_id' => $product->id, 'storage_key' => $path, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $audit->record($request->user()->id, 'product_image_added', 'products', $product->id, 'product-image:'.$imageId);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return to_route('admin.catalog.index');
    }
}
