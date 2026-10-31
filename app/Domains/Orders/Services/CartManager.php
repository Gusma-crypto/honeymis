<?php

namespace App\Domains\Orders\Services;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Cart;
use App\Domains\Orders\Models\CartItem;
use App\Domains\Orders\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartManager
{
    public function current(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::firstOrCreate(['user_id' => $user->id], ['name' => $user->name, 'email' => $user->email, 'phone' => $user->whatsapp_number ?? '']);

            return Cart::where('customer_id', $customer->id)->where('expires_at', '>', now())->orderByDesc('id')->first()
                ?? Cart::create(['customer_id' => $customer->id, 'token_hash' => hash('sha256', Str::random(64)), 'expires_at' => now()->addDays(30)]);
        }, attempts: 3);
    }

    public function setQuantity(User $user, int $variantId, int $quantity): void
    {
        DB::transaction(function () use ($user, $variantId, $quantity): void {
            $cart = $this->current($user);
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            if ($quantity === 0) {
                CartItem::where('cart_id', $cart->id)->where('variant_id', $variantId)->delete();

                return;
            }
            $variant = ProductVariant::with(['product.category', 'inventory'])->findOrFail($variantId);
            if ($quantity < 1 || $quantity > 99 || $variant->status !== 'active' || $variant->product->status !== 'active' || $variant->product->category->status !== 'active') {
                throw ValidationException::withMessages(['quantity' => 'Produk tidak aktif atau jumlah tidak valid.']);
            }
            if ($quantity > (($variant->inventory->on_hand ?? 0) - ($variant->inventory->reserved ?? 0))) {
                throw ValidationException::withMessages(['quantity' => 'Stok tidak mencukupi.']);
            }
            CartItem::updateOrCreate(['cart_id' => $cart->id, 'variant_id' => $variantId], ['quantity' => $quantity]);
        }, attempts: 3);
    }
}
