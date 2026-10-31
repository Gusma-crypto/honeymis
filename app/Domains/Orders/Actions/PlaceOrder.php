<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Services\CheckoutAttribution;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Orders\Contracts\PaymentAdapter;
use App\Domains\Orders\Models\Cart;
use App\Domains\Orders\Models\Customer;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Payment;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Domains\Orders\Services\ShippingQuotes;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\CommerceNotifier;
use App\Domains\Shared\ValueObjects\Idr;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(private CartManager $carts, private CheckoutQuote $quotes, private AuditWriter $audit, private PaymentAdapter $payments, private CheckoutAttribution $attribution, private ShippingQuotes $shippingQuotes) {}

    /** @param array{recipient: string, phone: string, province: string, city: string, district: string, postal_code: string, address: string} $address */
    public function handle(User $user, array $address, string $quoteFingerprint, string $key, ?string $referralToken = null, ?string $shippingQuoteToken = null): Order
    {
        abort_unless($user->isActive() && $user->hasVerifiedEmail(), 403);
        $requestData = $shippingQuoteToken === null ? [$address, $quoteFingerprint] : [$address, $quoteFingerprint, $shippingQuoteToken];
        $fingerprint = hash('sha256', json_encode($requestData, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $address, $quoteFingerprint, $key, $fingerprint, $referralToken, $shippingQuoteToken): Order {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Order::where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ($existing->customer->user_id !== $user->id || $existing->request_fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key checkout telah dipakai untuk permintaan lain.']);
                }

                return $existing;
            }
            $cart = $this->carts->current($user);
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $selection = $this->shippingQuotes->selection($user, $shippingQuoteToken);
            $quote = $this->quotes->calculate($cart, true, $selection);
            if ($selection !== null) {
                foreach (['province', 'city', 'district', 'postal_code'] as $field) {
                    if ($address[$field] !== $selection['destination'][$field]) {
                        throw ValidationException::withMessages(['shipping_quote_token' => 'Alamat tujuan berbeda dari tarif ongkir. Pilih kembali tujuan.']);
                    }
                }
            }
            if (! hash_equals($quote['fingerprint'], $quoteFingerprint)) {
                throw ValidationException::withMessages(['quote_fingerprint' => 'Harga atau isi keranjang berubah. Muat ulang checkout.']);
            }
            if ($quote['grand_total'] < 1) {
                throw ValidationException::withMessages(['cart' => 'Total pembayaran harus lebih dari nol.']);
            }
            $attribution = $this->attribution->snapshot($user, $referralToken, array_column($quote['lines'], 'variant_id'));
            $customer = Customer::findOrFail($cart->customer_id);
            $customer->update(['name' => $user->name, 'email' => $user->email, 'phone' => $address['phone']]);
            $order = Order::create([
                'order_number' => 'HC-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()), 'customer_id' => $customer->id,
                'idempotency_key' => $key, 'request_fingerprint' => $fingerprint, 'cart_id' => $cart->id,
                'shipping_address_snapshot' => $address, 'shipping_snapshot' => $quote['shipping'], 'subtotal' => $quote['subtotal'], 'discount_total' => $quote['discount_total'],
                'shipping_total' => $quote['shipping_total'], 'tax_total' => 0, 'grand_total' => $quote['grand_total'], 'currency' => 'IDR',
                'return_window_days' => config('partners.return_window_days'), 'status' => 'pending_payment', 'placed_at' => now(), 'expires_at' => now()->addHours(config('commerce.payment_window_hours')),
                'partner_referral_id' => $attribution['partner_id'], 'commission_rule_id' => $attribution['first_rule_id'], 'referral_snapshot' => $attribution['referral'], 'rule_snapshot' => $attribution['rules'],
            ]);
            foreach ($quote['lines'] as $line) {
                $variant = ProductVariant::whereKey($line['variant_id'])->lockForUpdate()->firstOrFail();
                OrderItem::create(['order_id' => $order->id, 'variant_id' => $variant->id, 'quantity' => $line['quantity'],
                    'sku_snapshot' => $line['sku'], 'name_snapshot' => $line['name'], 'attributes_snapshot' => $line['attributes'],
                    'unit_price_snapshot' => $line['unit_price'], 'discount_snapshot' => $line['discount'], 'cost_snapshot' => $variant->cost,
                    'commission_eligible_snapshot' => $line['eligible'], 'commission_rule_id' => $attribution['items'][$variant->id]['rule_id'] ?? null, 'commission_snapshot' => $attribution['items'][$variant->id] ?? null]);
                InventoryBalance::where('variant_id', $variant->id)->increment('reserved', $line['quantity']);
                DB::table('inventory_movements')->insert(['variant_id' => $variant->id, 'quantity_delta' => 0, 'reserved_delta' => $line['quantity'],
                    'reason' => 'order_reserved', 'reference_type' => 'orders', 'reference_id' => $order->id,
                    'idempotency_key' => 'order:'.$order->id.':reserve:'.$variant->id, 'created_at' => now()]);
            }
            Payment::create(['order_id' => $order->id, 'provider' => $this->payments->provider(), 'amount' => $order->grand_total, 'status' => $this->payments->initialStatus(new Idr($order->grand_total))->value, 'idempotency_key' => 'order:'.$order->id.':manual']);
            $cart->items()->delete();
            $this->audit->record($user->id, 'order_placed', 'orders', $order->id, 'order:'.$order->id.':placed', ['total' => $order->grand_total]);

            app(CommerceNotifier::class)->queue($user->id, 'order:'.$order->id.':placed', 'Pesanan diterima', $order->order_number.' menunggu pembayaran manual.', route('orders.show', $order->id, false));

            return $order;
        }, attempts: 3);
    }
}
