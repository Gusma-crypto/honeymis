import { useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { KeyRound, Search, Truck } from 'lucide-react';
import {
    Errors,
    Field,
    inputClass,
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update, destinations } from '@/routes/admin/shipping-settings';
import type { ShippingDestination } from '@/types/commerce';

export type ShippingConfiguration = {
    mode: 'manual' | 'api';
    manual_amount: number;
    manual_label: string;
    origin_id: number | null;
    couriers: string;
    packaging_weight_g: number;
    revision: string;
    has_api_key: boolean;
};
export function ShippingSettingsForm({
    settings,
}: {
    settings: ShippingConfiguration;
}) {
    const key = useRequestKey();
    const form = useForm({
        mode: settings.mode,
        manual_amount: settings.manual_amount,
        manual_label: settings.manual_label,
        origin_id: settings.origin_id?.toString() ?? '',
        couriers: settings.couriers,
        packaging_weight_g: settings.packaging_weight_g,
        revision: settings.revision,
        api_key: '',
        clear_api_key: false,
        idempotency_key: '',
    });
    const lookup = useHttp<
        { search: string },
        { destinations: ShippingDestination[] }
    >({ search: '' });
    const [locations, setLocations] = useState<ShippingDestination[]>([]);
    return (
        <form
            className="grid gap-5 rounded-2xl border border-primary/20 bg-card p-5 sm:p-6"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    idempotency_key: key.get(),
                }));
                form.put(update.url(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        key.clear();
                        form.reset('api_key');
                    },
                });
            }}
        >
            <header>
                <h2 className="flex items-center gap-2 text-lg font-semibold">
                    <Truck className="size-5 text-primary" />
                    Pengaturan ongkir
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    Berlaku untuk checkout baru. Ongkir pesanan yang sudah
                    dibuat tetap tersimpan.
                </p>
            </header>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Mode ongkir">
                    <select
                        className={inputClass}
                        value={form.data.mode}
                        onChange={(e) =>
                            form.setData(
                                'mode',
                                e.target.value as 'manual' | 'api',
                            )
                        }
                    >
                        <option value="manual">Tarif manual / flat</option>
                        <option value="api">API RajaOngkir / Komerce</option>
                    </select>
                </Field>
                <Field label="Tarif manual (IDR)">
                    <input
                        type="number"
                        min={0}
                        max={100000000}
                        step={1}
                        required
                        className={inputClass}
                        value={form.data.manual_amount}
                        onChange={(e) =>
                            form.setData(
                                'manual_amount',
                                Number(e.target.value),
                            )
                        }
                    />
                </Field>
                <Field label="Nama layanan manual">
                    <input
                        className={inputClass}
                        required
                        maxLength={100}
                        value={form.data.manual_label}
                        onChange={(e) =>
                            form.setData('manual_label', e.target.value)
                        }
                    />
                </Field>
                <Field label="Berat tambahan kemasan (gram)">
                    <input
                        className={inputClass}
                        type="number"
                        min={0}
                        max={5000}
                        step={1}
                        required
                        value={form.data.packaging_weight_g}
                        onChange={(e) =>
                            form.setData(
                                'packaging_weight_g',
                                Number(e.target.value),
                            )
                        }
                    />
                </Field>
            </div>
            <section className="grid gap-4 rounded-xl border bg-muted/15 p-4">
                <h3 className="flex items-center gap-2 text-sm font-semibold">
                    <KeyRound className="size-4" />
                    Konfigurasi RajaOngkir / Komerce
                </h3>
                <p className="text-xs leading-6 text-muted-foreground">
                    API key disimpan terenkripsi dan tidak ditampilkan kembali.
                    Kosongkan untuk mempertahankan key tersimpan. Tarif manual
                    hanya digunakan saat mode manual dipilih.
                </p>
                <Field
                    label={`API key ${settings.has_api_key ? '(sudah tersimpan)' : '(belum tersimpan)'}`}
                >
                    <input
                        type="password"
                        autoComplete="new-password"
                        className={inputClass}
                        maxLength={500}
                        value={form.data.api_key}
                        onChange={(e) =>
                            form.setData('api_key', e.target.value)
                        }
                        placeholder={
                            settings.has_api_key
                                ? 'Biarkan kosong jika tidak mengganti key'
                                : 'Masukkan key Shipping Cost RajaOngkir'
                        }
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="ID lokasi asal RajaOngkir">
                        <input
                            type="number"
                            min={1}
                            step={1}
                            required={form.data.mode === 'api'}
                            className={inputClass}
                            value={form.data.origin_id}
                            onChange={(e) =>
                                form.setData('origin_id', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Kode kurir (pisahkan dengan titik dua)">
                        <input
                            required
                            className={inputClass}
                            maxLength={150}
                            value={form.data.couriers}
                            onChange={(e) =>
                                form.setData(
                                    'couriers',
                                    e.target.value.toLowerCase(),
                                )
                            }
                            placeholder="jne:jnt:pos"
                        />
                    </Field>
                </div>
                <div className="grid gap-3 border-t pt-4">
                    <label className="grid gap-2 text-xs font-medium">
                        Cari lokasi asal
                        <div className="flex gap-2">
                            <input
                                type="search"
                                className={inputClass}
                                placeholder="Kelurahan atau kode pos asal"
                                maxLength={100}
                                value={lookup.data.search}
                                onChange={(e) => {
                                    lookup.cancel();
                                    lookup.setData('search', e.target.value);
                                    setLocations([]);
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    !settings.has_api_key ||
                                    lookup.processing ||
                                    lookup.data.search.trim().length < 3
                                }
                                onClick={() => {
                                    void lookup
                                        .get(destinations.url(), {
                                            onSuccess: (data) =>
                                                setLocations(data.destinations),
                                        })
                                        .catch(() => undefined);
                                }}
                            >
                                <Search className="size-4" />
                                Cari
                            </Button>
                        </div>
                    </label>
                    <p className="text-xs text-muted-foreground">
                        Untuk memakai pencarian, simpan API key terlebih dahulu
                        dalam mode manual. Pilih hasil untuk mengisi ID asal.
                    </p>
                    <Errors errors={lookup.errors} />
                    {lookup.wasSuccessful && locations.length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            Lokasi tidak ditemukan.
                        </p>
                    )}
                    {locations.length > 0 && (
                        <div className="grid max-h-48 overflow-y-auto rounded-lg border bg-card p-2">
                            {locations.map((location) => (
                                <button
                                    type="button"
                                    key={location.id}
                                    className="rounded-lg p-2 text-left text-xs hover:bg-accent"
                                    onClick={() => {
                                        form.setData(
                                            'origin_id',
                                            String(location.id),
                                        );
                                        setLocations([]);
                                        lookup.setData(
                                            'search',
                                            location.label,
                                        );
                                    }}
                                >
                                    {location.label} (ID {location.id})
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <label className="flex items-center gap-2 text-xs">
                    <input
                        type="checkbox"
                        checked={form.data.clear_api_key}
                        disabled={form.data.mode === 'api'}
                        onChange={(e) =>
                            form.setData('clear_api_key', e.target.checked)
                        }
                    />
                    Hapus API key tersimpan (mode manual)
                </label>
            </section>
            <Errors errors={form.errors} />
            <Button disabled={form.processing} className="justify-self-start">
                {form.processing ? 'Menyimpan...' : 'Simpan pengaturan ongkir'}
            </Button>
        </form>
    );
}
