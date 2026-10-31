<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Actions\AdjustInventory;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('inventory.manage');

        return Inertia::render('admin/inventory', ['listFilters' => $lists->values($request), 'variants' => $lists->apply($request, ProductVariant::with(['product:id,name', 'inventory']), ['sku'], ['product' => ['name']], ['active', 'inactive'])->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function update(InventoryAdjustmentRequest $request, ProductVariant $variant, AdjustInventory $inventory): RedirectResponse
    {
        $inventory->handle($request->user(), $variant, $request->integer('quantity_delta'), $request->string('reason')->toString(), $request->string('idempotency_key')->toString());

        return to_route('admin.inventory.index');
    }
}
