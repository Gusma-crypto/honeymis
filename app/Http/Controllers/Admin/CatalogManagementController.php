<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
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

class CatalogManagementController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('manage', Product::class);

        return Inertia::render('admin/catalog', ['listFilters' => $lists->values($request), 'products' => $lists->apply($request, Product::with('variants'), ['name', 'sku'], ['variants' => ['sku']], ['active', 'inactive'])->orderByDesc('id')->paginate(15)->withQueryString(), 'categories' => Category::orderBy('name')->get(['id', 'name', 'slug', 'status'])]);
    }

    public function store(ProductRequest $request, SaveProduct $products): RedirectResponse
    {
        $products->handle($request->user(), null, $request->productData());

        return to_route('admin.catalog.index');
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $products): RedirectResponse
    {
        $products->handle($request->user(), $product, $request->productData());

        return to_route('admin.catalog.index');
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
