<?php

namespace App\Domains\Orders\Services;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Orders\Models\Cart;
use App\Domains\Shared\ValueObjects\Idr;
use Illuminate\Validation\ValidationException;

class CheckoutQuote
{
    public function __construct(private ShippingSettings $settings) {}

    /**
     * @param  array<string, mixed>|null  $selection
     * @return array{cart_id: int, lines: list<array{variant_id: int, sku: string, name: string, attributes: array<string, string>, quantity: int, unit_price: int, discount: int, total: int, eligible: bool}>, subtotal: int, discount_total: int, shipping_total: int, tax_total: int, grand_total: int, weight_g: int, shipping_required: bool, shipping: array<string, mixed>, fingerprint: string}
     */
    public function calculate(Cart $cart, bool $lock = false, ?array $selection = null): array
    {
        $items = $cart->items()->orderBy('variant_id')->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang masih kosong.']);
        }
        if ($lock) {
            $productIds = ProductVariant::whereIn('id', $items->pluck('variant_id'))->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
            $categories = Category::whereIn('id', $products->pluck('category_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $products = $products->keyBy('id');
            $variants = ProductVariant::whereIn('id', $items->pluck('variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $balances = InventoryBalance::whereIn('variant_id', $items->pluck('variant_id'))->orderBy('variant_id')->lockForUpdate()->get()->keyBy('variant_id');
            foreach ($products as $product) {
                $product->setRelation('category', $categories->get($product->category_id));
            }
            foreach ($variants as $variant) {
                $variant->setRelation('product', $products->get($variant->product_id));
                $variant->setRelation('inventory', $balances->get($variant->id));
            }
        } else {
            $variants = ProductVariant::with(['product.category', 'inventory'])->whereIn('id', $items->pluck('variant_id'))->get()->keyBy('id');
        }
        $weight = 0;
        $lines = [];
        $subtotal = new Idr(0);
        $discount = new Idr(0);
        foreach ($items as $item) {
            $variant = $variants->get($item->variant_id);
            if ($variant === null || $variant->status !== 'active' || $variant->product->status !== 'active' || $variant->product->category->status !== 'active') {
                throw ValidationException::withMessages(['cart' => 'Ada produk yang tidak lagi aktif. Hapus dari keranjang.']);
            }
            if ($item->quantity < 1 || $item->quantity > 99 || $item->quantity > (($variant->inventory->on_hand ?? 0) - ($variant->inventory->reserved ?? 0))) {
                throw ValidationException::withMessages(['cart' => 'Stok '.$variant->sku.' tidak mencukupi.']);
            }
            $weight += max(1, $variant->product->weight_g) * $item->quantity;
            $lineSubtotal = (new Idr($variant->price))->multiply($item->quantity);
            $lineDiscount = (new Idr($variant->discount_amount))->multiply($item->quantity);
            $subtotal = $subtotal->add($lineSubtotal);
            $discount = $discount->add($lineDiscount);
            $lines[] = ['variant_id' => $variant->id, 'sku' => $variant->sku, 'name' => $variant->product->name, 'attributes' => $variant->attributes,
                'quantity' => $item->quantity, 'unit_price' => $variant->price, 'discount' => $lineDiscount->amount,
                'total' => $lineSubtotal->subtract($lineDiscount)->amount, 'eligible' => $variant->product->commission_eligible];
        }
        $setting = $this->settings->current($lock);
        $weight += $setting->packaging_weight_g;
        $shipping = new Idr($setting->mode === 'manual' ? $setting->manual_amount : 0);
        $snapshot = ['provider' => $setting->mode === 'manual' ? 'manual' : 'rajaongkir', 'service' => $setting->manual_label, 'revision' => $setting->revision, 'amount' => $shipping->amount, 'weight_g' => $weight];
        $quote = ['cart_id' => $cart->id, 'lines' => $lines, 'subtotal' => $subtotal->amount, 'discount_total' => $discount->amount,
            'shipping_total' => $shipping->amount, 'tax_total' => 0, 'grand_total' => $subtotal->subtract($discount)->add($shipping)->amount,
            'weight_g' => $weight, 'shipping_required' => $setting->mode === 'api', 'shipping' => $snapshot];
        $baseFingerprint = hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));
        if ($setting->mode === 'api' && $selection !== null) {
            if ($selection['cart_id'] !== $cart->id || ! hash_equals($baseFingerprint, $selection['base_fingerprint'])) {
                throw ValidationException::withMessages(['shipping_quote_token' => 'Keranjang, berat, atau pengaturan ongkir berubah. Hitung kembali ongkir.']);
            }
            $shipping = new Idr($selection['amount']);
            $quote['shipping_total'] = $shipping->amount;
            $quote['grand_total'] = $subtotal->subtract($discount)->add($shipping)->amount;
            $quote['shipping_required'] = false;
            $quote['shipping'] = ['provider' => 'rajaongkir', 'courier' => $selection['courier'], 'service' => $selection['service'], 'amount' => $selection['amount'], 'etd' => $selection['etd'], 'destination' => $selection['destination'], 'weight_g' => $weight, 'revision' => $setting->revision];
        } elseif ($selection !== null || ($lock && $setting->mode === 'api')) {
            throw ValidationException::withMessages(['shipping_quote_token' => 'Pilih layanan pengiriman dan hitung kembali ongkir.']);
        }

        return [...$quote, 'fingerprint' => hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR))];
    }
}
