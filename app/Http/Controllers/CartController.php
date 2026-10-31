<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Services\CartManager;
use App\Http\Requests\Commerce\CartItemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request, CartManager $carts): Response
    {
        $cart = $carts->current($request->user());
        $items = $cart->items()->with(['variant.product.category', 'variant.inventory'])->orderBy('id')->get()->map(fn ($item): array => [
            'variant_id' => $item->variant_id, 'quantity' => $item->quantity, 'name' => $item->variant->product->name,
            'label' => $item->variant->attributes['label'] ?? $item->variant->sku, 'price' => $item->variant->price,
            'discount_amount' => $item->variant->discount_amount,
            'active' => $item->variant->status === 'active' && $item->variant->product->status === 'active' && $item->variant->product->category->status === 'active',
            'available' => ($item->variant->inventory->on_hand ?? 0) - ($item->variant->inventory->reserved ?? 0),
        ])->all();

        return Inertia::render('commerce/cart', ['items' => $items]);
    }

    public function update(CartItemRequest $request, CartManager $carts): RedirectResponse
    {
        $carts->setQuantity($request->user(), $request->integer('variant_id'), $request->integer('quantity'));

        return to_route('cart.index');
    }
}
