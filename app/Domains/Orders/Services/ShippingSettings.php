<?php

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Models\ShippingSetting;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingSettings
{
    public function __construct(private AuditWriter $audit) {}

    public function current(bool $lock = false): ShippingSetting
    {
        $query = ShippingSetting::query()->whereKey(1);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? new ShippingSetting([
            'mode' => 'manual', 'manual_amount' => (int) config('commerce.shipping_flat_idr'), 'manual_label' => 'Pengiriman reguler',
            'origin_id' => null, 'couriers' => 'jne:jnt:pos', 'packaging_weight_g' => 100,
            'revision' => 'default-'.config('commerce.shipping_flat_idr'),
        ]);
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        $setting = $this->current();

        return ['mode' => $setting->mode, 'manual_amount' => $setting->manual_amount, 'manual_label' => $setting->manual_label, 'origin_id' => $setting->origin_id,
            'couriers' => $setting->couriers, 'packaging_weight_g' => $setting->packaging_weight_g, 'revision' => $setting->revision, 'has_api_key' => filled($setting->api_key)];
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data): void
    {
        DB::transaction(function () use ($actor, $data): void {
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize('manage', ShippingSetting::class);
            $key = 'shipping-settings:'.$data['idempotency_key'];
            $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = DB::table('audit_logs')->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                $metadata = json_decode($existing->after_json, true, flags: JSON_THROW_ON_ERROR);
                if ((int) $existing->actor_id !== $actor->id || $metadata['fingerprint'] !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key telah dipakai untuk perubahan lain.']);
                }

                return;
            }
            $setting = $this->current(true);
            if (! hash_equals($setting->revision, $data['revision'])) {
                throw ValidationException::withMessages(['revision' => 'Pengaturan telah berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            $apiKey = $data['clear_api_key'] ? null : ($data['api_key'] ?? $setting->api_key);
            if ($data['mode'] === 'api' && blank($apiKey)) {
                throw ValidationException::withMessages(['api_key' => 'Isi API key RajaOngkir/Komerce untuk mengaktifkan mode API.']);
            }
            $setting->id = 1;
            $setting->fill(collect($data)->only(['mode', 'manual_amount', 'manual_label', 'origin_id', 'couriers', 'packaging_weight_g'])->all());
            $setting->api_key = $apiKey;
            $setting->revision = (string) Str::uuid();
            $setting->setAttribute('updated_by', $actor->id);
            $setting->save();
            $this->audit->record($actor->id, 'shipping_settings_updated', 'shipping_settings', 1, $key, [
                'fingerprint' => $fingerprint, 'mode' => $setting->mode, 'manual_amount' => $setting->manual_amount,
                'revision' => $setting->revision, 'api_key_changed' => filled($data['api_key'] ?? null) || $data['clear_api_key'],
            ]);
        }, attempts: 3);
    }
}
