<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCatalogQuery
{
    public function __construct(private ListFilters $lists) {}

    /** @return Builder<Product> */
    public function filtered(Request $request): Builder
    {
        $request->validate([
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['nullable', 'in:newest,oldest,name,price_low,price_high,stock,sold'],
            'label' => ['nullable', 'in:mlm,promo,bestseller,bundle'],
            'per_page' => ['nullable', 'integer', 'in:8,10,16,24,48'],
        ]);
        $query = $this->lists->apply($request, $this->base(), ['products.name', 'products.sku'], ['variants' => ['sku'], 'category' => ['name']], ['active', 'inactive', 'draft'], 'products.status');
        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        match ($request->input('label')) {
            'mlm' => $query->where('commission_eligible', true),
            'promo' => $query->whereHas('variants', fn ($variants) => $variants->where('discount_amount', '>', 0)),
            'bestseller' => $query->whereRaw('COALESCE(sales.units, 0) > 0'),
            'bundle' => $query->whereHas('category', fn ($category) => $category->where(fn ($names) => $names->where('slug', 'like', '%paket%')->orWhere('slug', 'like', '%bundl%'))),
            default => null,
        };
        [$column, $direction] = match ($request->input('sort', 'newest')) {
            'oldest' => ['products.id', 'asc'], 'name' => ['products.name', 'asc'],
            'price_low' => ['price', 'asc'], 'price_high' => ['price', 'desc'],
            'stock' => ['stock', 'desc'], 'sold' => ['sold', 'desc'], default => ['products.id', 'desc'],
        };

        return $query->orderBy($column, $direction)->orderByDesc('products.id');
    }

    /** @return Builder<Product> */
    public function base(): Builder
    {
        $inventory = DB::table('product_variants as variants')->leftJoin('inventory_balances as balances', 'balances.variant_id', '=', 'variants.id')
            ->select('variants.product_id')->selectRaw('SUM(COALESCE(balances.on_hand, 0) - COALESCE(balances.reserved, 0)) as stock, SUM(COALESCE(balances.on_hand, 0) * variants.cost) as stock_value, MIN(variants.price - variants.discount_amount) as price')->groupBy('variants.product_id');

        return Product::query()->select('products.*')->leftJoinSub($inventory, 'inventory', 'inventory.product_id', '=', 'products.id')
            ->leftJoinSub($this->sales(), 'sales', 'sales.product_id', '=', 'products.id')
            ->selectRaw('COALESCE(inventory.stock, 0) as stock, COALESCE(inventory.stock_value, 0) as stock_value, COALESCE(inventory.price, 0) as price, COALESCE(sales.units, 0) as sold')
            ->addSelect(['first_image_id' => DB::table('product_images')->select('id')->whereColumn('product_id', 'products.id')->orderBy('sort_order')->orderByDesc('id')->limit(1)]);
    }

    private function sales(): QueryBuilder
    {
        $settled = DB::table('payments')->select('order_id')->selectRaw('SUM(amount) as amount')->where('status', 'verified')->whereNotNull('verified_at')->groupBy('order_id');

        return DB::table('order_items as items')->join('orders', 'orders.id', '=', 'items.order_id')
            ->join('product_variants as sold_variants', 'sold_variants.id', '=', 'items.variant_id')
            ->joinSub($settled, 'settled', 'settled.order_id', '=', 'orders.id')
            ->whereColumn('settled.amount', 'orders.grand_total')->where('orders.fraud_hold', false)
            ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereNotExists(fn ($refunds) => $refunds->selectRaw('1')->from('refunds')->whereColumn('refunds.order_item_id', 'items.id')->where('refunds.status', 'processed'))
            ->select('sold_variants.product_id')->selectRaw('SUM(items.quantity) as units')->groupBy('sold_variants.product_id');
    }
}
