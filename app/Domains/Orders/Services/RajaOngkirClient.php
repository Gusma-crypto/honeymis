<?php

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Models\ShippingSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RajaOngkirClient
{
    private const string BASE_URL = 'https://rajaongkir.komerce.id/api/v1';

    private function client(ShippingSetting $setting): PendingRequest
    {
        if (blank($setting->api_key)) {
            throw ValidationException::withMessages(['shipping' => 'API ongkir belum dikonfigurasi administrator.']);
        }

        return Http::baseUrl(self::BASE_URL)->withHeaders(['key' => $setting->api_key])->acceptJson()->connectTimeout(3)->timeout(8)->withoutRedirecting();
    }

    /** @return list<array{id: int, label: string, province: string, city: string, district: string, subdistrict: string, postal_code: string}> */
    public function destinations(ShippingSetting $setting, string $search): array
    {
        try {
            $response = $this->client($setting)->get('/destination/domestic-destination', ['search' => $search, 'limit' => 15, 'offset' => 0]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['shipping' => 'Layanan lokasi tidak dapat dihubungi. Coba kembali.']);
        }
        if ($response->status() === 404) {
            return [];
        }
        if (! $response->successful() || $response->json('meta.status') !== 'success' || ! is_array($response->json('data'))) {
            throw ValidationException::withMessages(['shipping' => 'Pencarian lokasi gagal. Periksa konfigurasi API atau coba kembali.']);
        }
        $destinations = [];
        foreach ($response->json('data') as $row) {
            if (! is_array($row) || filter_var($row['id'] ?? null, FILTER_VALIDATE_INT) === false || (int) $row['id'] < 1) {
                continue;
            }
            $fields = ['label', 'province_name', 'city_name', 'district_name', 'subdistrict_name', 'zip_code'];
            foreach ($fields as $field) {
                if (! isset($row[$field]) || ! is_scalar($row[$field]) || strlen((string) $row[$field]) > 255) {
                    continue 2;
                }
            }
            if (strlen((string) $row['zip_code']) > 12 || blank($row['zip_code']) || blank($row['province_name']) || blank($row['city_name']) || blank($row['district_name'])) {
                continue;
            }
            $destinations[] = ['id' => (int) $row['id'], 'label' => (string) $row['label'], 'province' => (string) $row['province_name'], 'city' => (string) $row['city_name'], 'district' => (string) $row['district_name'], 'subdistrict' => (string) $row['subdistrict_name'], 'postal_code' => (string) $row['zip_code']];
        }

        return array_slice($destinations, 0, 15);
    }

    /** @return list<array{courier: string, service: string, amount: int, etd: string}> */
    public function rates(ShippingSetting $setting, int $destination, int $weight): array
    {
        try {
            $response = $this->client($setting)->asForm()->post('/calculate/domestic-cost', ['origin' => $setting->origin_id, 'destination' => $destination, 'weight' => $weight, 'courier' => $setting->couriers, 'price' => 'lowest']);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['shipping' => 'Layanan ongkir tidak dapat dihubungi. Coba kembali.']);
        }
        if (! $response->successful() || $response->json('meta.status') !== 'success' || ! is_array($response->json('data'))) {
            throw ValidationException::withMessages(['shipping' => 'Tarif ongkir belum tersedia. Periksa tujuan atau hubungi toko.']);
        }
        $rates = [];
        foreach ($response->json('data') as $row) {
            if (! is_array($row) || ! is_string($row['code'] ?? null) || ! in_array($row['code'], explode(':', $setting->couriers), true)
                || ! is_string($row['service'] ?? null) || strlen($row['service']) > 100
                || ! is_scalar($row['etd'] ?? '') || strlen((string) ($row['etd'] ?? '')) > 100
                || ! (is_int($row['cost'] ?? null) || (is_string($row['cost'] ?? null) && ctype_digit($row['cost'])))
                || (int) $row['cost'] < 0 || (int) $row['cost'] > 100000000) {
                continue;
            }
            $rates[] = ['courier' => $row['code'], 'service' => $row['service'], 'amount' => (int) $row['cost'], 'etd' => (string) ($row['etd'] ?? '')];
        }
        if ($rates === []) {
            throw ValidationException::withMessages(['shipping' => 'Tidak ada layanan pengiriman untuk tujuan ini.']);
        }

        return array_slice($rates, 0, 50);
    }
}
