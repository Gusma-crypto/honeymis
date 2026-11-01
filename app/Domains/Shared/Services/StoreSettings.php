<?php

namespace App\Domains\Shared\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreSettings
{
    /** @return array<string, mixed> */
    public function current(): array
    {
        $defaults = [
            'company_name' => config('app.name', 'HoneyCommerce'), 'brand_name' => config('app.name', 'HoneyCommerce'),
            'slogan' => 'Madu Sehat, Hidup Lebih Baik', 'email' => '', 'phone' => '', 'website' => '', 'address' => '',
            'description' => 'HoneyCommerce adalah platform penjualan produk madu dengan program mitra berbasis penjualan produk.',
            'payment_instructions' => config('commerce.manual_payment_instructions'),
            'system_active' => true, 'registration_enabled' => true, 'maintenance_mode' => false,
            'logo_path' => null, 'favicon_path' => null, 'revision' => 'initial',
        ];
        if (! Schema::hasTable('store_settings')) {
            return $defaults;
        }
        $row = DB::table('store_settings')->where('id', 1)->first();

        return $row === null ? $defaults : [...$defaults, ...json_decode($row->values, true, flags: JSON_THROW_ON_ERROR), 'revision' => $row->revision];
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        $values = $this->current();
        foreach (['logo', 'favicon'] as $kind) {
            $values[$kind.'_url'] = $values[$kind.'_path'] === null ? null : route('store.branding', ['kind' => $kind, 'v' => $values['revision']]);
            unset($values[$kind.'_path']);
        }

        return $values;
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data): void
    {
        Gate::forUser($actor)->authorize('users.manage-access');
        $created = [];
        $obsolete = [];
        try {
            DB::transaction(function () use ($actor, $data, &$created, &$obsolete): void {
                DB::table('store_settings')->insertOrIgnore([
                    'id' => 1, 'values' => '{}', 'revision' => 'initial', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('store_settings')->where('id', 1)->lockForUpdate()->first();
                $before = $this->current();
                if (! hash_equals($before['revision'], $data['revision'])) {
                    throw ValidationException::withMessages(['revision' => 'Pengaturan telah berubah. Muat ulang halaman sebelum menyimpan.']);
                }
                $values = array_intersect_key($data, array_flip([
                    'company_name', 'brand_name', 'slogan', 'email', 'phone', 'website', 'address', 'description',
                    'payment_instructions', 'system_active', 'registration_enabled', 'maintenance_mode',
                ]));
                foreach (['system_active', 'registration_enabled', 'maintenance_mode'] as $field) {
                    $values[$field] = (bool) $values[$field];
                }
                foreach (['logo', 'favicon'] as $kind) {
                    $path = $before[$kind.'_path'];
                    if (($data['remove_'.$kind] ?? false) || ($data[$kind] ?? null) instanceof UploadedFile) {
                        if ($path !== null) {
                            $obsolete[] = $path;
                        }
                        $path = null;
                    }
                    if (($data[$kind] ?? null) instanceof UploadedFile) {
                        $path = $data[$kind]->store('branding', 'local');
                        if ($path === false) {
                            throw new \RuntimeException('Unggahan identitas brand gagal disimpan.');
                        }
                        $created[] = $path;
                    }
                    $values[$kind.'_path'] = $path;
                }
                $revision = (string) Str::uuid();
                DB::table('store_settings')->where('id', 1)->update([
                    'values' => json_encode($values, JSON_THROW_ON_ERROR), 'revision' => $revision, 'updated_by' => $actor->id, 'updated_at' => now(),
                ]);
                app(AuditWriter::class)->record($actor->id, 'store_settings_updated', 'store_settings', 1, 'store-settings:'.$revision, [
                    'revision' => $revision, 'changed_fields' => array_keys(array_filter($values, fn (mixed $value, string $key): bool => $value !== ($before[$key] ?? null), ARRAY_FILTER_USE_BOTH)),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($created);
            throw $exception;
        }
        Storage::disk('local')->delete($obsolete);
    }
}
