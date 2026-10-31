<?php

namespace App\Domains\Orders\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingQuotes
{
    public function __construct(private CartManager $carts, private CheckoutQuote $quotes, private ShippingSettings $settings, private RajaOngkirClient $client) {}

    /** @return list<array<string, mixed>> */
    public function destinations(User $user, string $search, bool $administration = false): array
    {
        $setting = $this->settings->current();
        if (! $administration && $setting->mode !== 'api') {
            throw ValidationException::withMessages(['shipping' => 'Toko menggunakan tarif pengiriman manual.']);
        }
        $key = 'shipping-destinations:'.$setting->revision.':'.hash('sha256', mb_strtolower(trim($search)));
        $destinations = Cache::remember($key, 900, fn (): array => $this->client->destinations($setting, $search));
        foreach ($destinations as $destination) {
            Cache::put('shipping-destination:'.$user->id.':'.$destination['id'], $destination, 900);
        }

        return $destinations;
    }

    /** @return list<array<string, mixed>> */
    public function options(User $user, int $destinationId): array
    {
        $setting = $this->settings->current();
        if ($setting->mode !== 'api') {
            throw ValidationException::withMessages(['shipping' => 'Toko menggunakan tarif pengiriman manual.']);
        }
        $destination = Cache::get('shipping-destination:'.$user->id.':'.$destinationId);
        if (! is_array($destination)) {
            throw ValidationException::withMessages(['shipping' => 'Cari dan pilih kembali lokasi tujuan pengiriman.']);
        }
        $cart = $this->carts->current($user);
        $base = $this->quotes->calculate($cart);
        if ($base['shipping']['revision'] !== $setting->revision) {
            throw ValidationException::withMessages(['shipping' => 'Pengaturan ongkir berubah. Muat ulang checkout.']);
        }
        if ($base['weight_g'] > 1000000) {
            throw ValidationException::withMessages(['shipping' => 'Berat pesanan melebihi batas. Hubungi toko untuk pengiriman khusus.']);
        }
        $rates = $this->client->rates($setting, $destinationId, $base['weight_g']);
        $options = [];
        foreach ($rates as $rate) {
            $token = (string) Str::uuid();
            $expiresAt = now()->addMinutes(10);
            $selection = [...$rate, 'provider' => 'rajaongkir', 'user_id' => $user->id, 'cart_id' => $cart->id,
                'base_fingerprint' => $base['fingerprint'], 'destination' => $destination, 'expires_at' => $expiresAt->getTimestamp()];
            Cache::put('shipping-quote:'.$token, $selection, $expiresAt);
            $quoted = $this->quotes->calculate($cart, false, $selection);
            $options[] = ['token' => $token, ...$rate, 'quote' => $quoted, 'destination' => $destination, 'expires_at' => $expiresAt->toIso8601String()];
        }

        return $options;
    }

    /** @return array<string, mixed>|null */
    public function selection(User $user, ?string $token): ?array
    {
        if ($token === null) {
            return null;
        }
        $selection = Cache::get('shipping-quote:'.$token);
        if (! is_array($selection) || $selection['user_id'] !== $user->id || $selection['expires_at'] <= now()->getTimestamp()) {
            throw ValidationException::withMessages(['shipping_quote_token' => 'Tarif ongkir tidak valid atau kedaluwarsa. Hitung kembali ongkir.']);
        }

        return $selection;
    }
}
