<?php

namespace App\Http\Controllers;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'integer']]);
        $query = Product::with($this->catalogRelations())->where('status', 'active')->whereHas('category', fn ($query) => $query->where('status', 'active'));
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.addcslashes($filters['search'], '%_\\').'%');
        }
        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }
        $query->select('products.*')->selectSub(DB::table('product_images')->select('id')->whereColumn('product_id', 'products.id')->orderBy('sort_order')->orderByDesc('id')->limit(1), 'first_image_id');

        return Inertia::render('shop/catalog', ['products' => $query->orderBy('id')->paginate(12)->withQueryString()->through(fn (Product $product): array => $this->present($product)),
            'categories' => Category::where('status', 'active')->orderBy('name')->get(['id', 'name']), 'filters' => ['search' => $filters['search'] ?? '', 'category' => $filters['category'] ?? '']]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->status === 'active' && $product->category->status === 'active', 404);
        $product->load($this->catalogRelations());
        $product->setAttribute('first_image_id', DB::table('product_images')->where('product_id', $product->id)->orderBy('sort_order')->orderByDesc('id')->value('id'));

        return Inertia::render('shop/product', ['product' => $this->present($product)]);
    }

    public function image(int $image): StreamedResponse
    {
        $record = DB::table('product_images')->where('id', $image)->first();
        abort_if($record === null, 404);
        $product = Product::where('id', $record->product_id)->firstOrFail();
        abort_unless(($product->status === 'active' && $product->category->status === 'active') || auth()->user()?->can('catalog.manage'), 404);

        return Storage::disk('public')->response($record->storage_key, headers: ['X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<int|string, string|\Closure(Relation<*, *, *>): void> */
    private function catalogRelations(): array
    {
        return [
            'category:id,name,status',
            'variants' => function (Relation $query): void {
                $query->where('status', 'active')
                    ->select(['id', 'product_id', 'sku', 'attributes', 'price', 'discount_amount', 'status'])
                    ->with('inventory:id,variant_id,on_hand,reserved');
            },
        ];
    }

    /** @return array<string, mixed> */
    private function present(Product $product): array
    {
        return ['id' => $product->id, 'slug' => $product->slug, 'name' => $product->name, 'description' => $product->description,
            'category' => $product->category->name, 'weight_g' => $product->weight_g,
            'image_url' => $product->getAttribute('first_image_id') === null ? null : route('catalog.image', $product->getAttribute('first_image_id')),
            'variants' => $product->variants->where('status', 'active')->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id, 'sku' => $variant->sku, 'label' => $variant->attributes['label'] ?? $variant->sku,
                'price' => $variant->price, 'discount_amount' => $variant->discount_amount,
                'available' => ($variant->inventory->on_hand ?? 0) - ($variant->inventory->reserved ?? 0),
            ])->values()->all()];
    }
}
