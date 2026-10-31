import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { MapPin, Search, Truck } from 'lucide-react';
import { Errors, inputClass, money } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { destinations, quotes } from '@/routes/shipping';
import type { ShippingDestination, ShippingOption } from '@/types/commerce';

export function ShippingPicker({
    weight,
    onClear,
    onSelect,
}: {
    weight: number;
    onClear: () => void;
    onSelect: (option: ShippingOption) => void;
}) {
    const lookup = useHttp<
        { search: string },
        { destinations: ShippingDestination[] }
    >({ search: '' });
    const rates = useHttp<
        { destination_id: number },
        { options: ShippingOption[] }
    >({ destination_id: 0 });
    const [locations, setLocations] = useState<ShippingDestination[]>([]);
    const [options, setOptions] = useState<ShippingOption[]>([]);
    const [destination, setDestination] = useState<ShippingDestination | null>(
        null,
    );
    const [selected, setSelected] = useState('');
    const [message, setMessage] = useState('');
    function resetChoice() {
        setSelected('');
        setOptions([]);
        setMessage('');
        onClear();
    }
    return (
        <section className="grid gap-4 rounded-xl border border-primary/20 bg-accent/15 p-4">
            <h3 className="flex items-center gap-2 font-semibold">
                <Truck className="size-4 text-primary" />
                Pilih pengiriman
            </h3>
            <p className="text-xs text-muted-foreground">
                Berat produk + kemasan: {weight.toLocaleString('id-ID')} gram.
                Cari kelurahan atau kode pos, lalu pilih tarif kurir.
            </p>
            <label className="grid gap-2 text-sm font-medium">
                Cari tujuan
                <div className="flex gap-2">
                    <input
                        className={inputClass}
                        type="search"
                        placeholder="Kelurahan, kecamatan, atau kode pos"
                        maxLength={100}
                        value={lookup.data.search}
                        onChange={(e) => {
                            lookup.cancel();
                            rates.cancel();
                            lookup.setData('search', e.target.value);
                            setLocations([]);
                            setDestination(null);
                            resetChoice();
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            lookup.processing ||
                            rates.processing ||
                            lookup.data.search.trim().length < 3
                        }
                        onClick={() => {
                            resetChoice();
                            setLocations([]);
                            void lookup
                                .get(destinations.url(), {
                                    onSuccess: (data) => {
                                        setLocations(data.destinations);
                                        if (!data.destinations.length)
                                            setMessage(
                                                'Lokasi belum ditemukan. Coba kata kunci lain.',
                                            );
                                    },
                                    onNetworkError: () => {
                                        setMessage(
                                            'Tidak dapat tersambung. Coba kembali.',
                                        );
                                        return false;
                                    },
                                })
                                .catch(() => undefined);
                        }}
                    >
                        <Search className="size-4" />
                        {lookup.processing ? 'Mencari...' : 'Cari'}
                    </Button>
                </div>
            </label>
            <Errors errors={lookup.errors} />
            {locations.length > 0 && (
                <div
                    className="grid max-h-52 gap-1 overflow-y-auto rounded-lg border bg-card p-2"
                    aria-label="Pilihan lokasi tujuan"
                >
                    {locations.map((location) => (
                        <button
                            type="button"
                            key={location.id}
                            className={`rounded-lg p-3 text-left text-xs leading-5 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${destination?.id === location.id ? 'bg-accent text-accent-foreground' : ''}`}
                            disabled={rates.processing}
                            onClick={() => {
                                resetChoice();
                                setDestination(location);
                                rates.transform(() => ({
                                    destination_id: location.id,
                                }));
                                void rates
                                    .post(quotes.url(), {
                                        onSuccess: (data) => {
                                            setOptions(data.options);
                                            setLocations([]);
                                        },
                                        onNetworkError: () => {
                                            setMessage(
                                                'Tidak dapat mengambil tarif. Coba kembali.',
                                            );
                                            return false;
                                        },
                                    })
                                    .catch(() => undefined);
                            }}
                        >
                            <MapPin className="mr-2 inline size-3.5" />
                            {location.label}
                        </button>
                    ))}
                </div>
            )}
            {rates.processing && (
                <p
                    role="status"
                    className="animate-pulse text-xs text-muted-foreground"
                >
                    Menghitung ongkir kurir...
                </p>
            )}
            {destination && (
                <p className="text-xs font-medium text-primary">
                    Tujuan: {destination.label}
                </p>
            )}
            <Errors errors={rates.errors} />
            {message && (
                <p role="status" className="text-xs text-muted-foreground">
                    {message}
                </p>
            )}
            <div className="grid gap-2">
                {options.map((option) => (
                    <button
                        type="button"
                        key={option.token}
                        aria-pressed={selected === option.token}
                        className={`flex items-center justify-between gap-3 rounded-xl border bg-card p-4 text-left text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${selected === option.token ? 'border-primary ring-1 ring-primary' : 'hover:border-primary/40'}`}
                        onClick={() => {
                            setSelected(option.token);
                            onSelect(option);
                        }}
                    >
                        <span>
                            <span className="font-semibold">
                                {option.courier.toUpperCase()} ?{' '}
                                {option.service}
                            </span>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                Estimasi: {option.etd || 'sesuai kurir'}
                            </span>
                        </span>
                        <span className="shrink-0 font-semibold text-primary">
                            {money(option.amount)}
                        </span>
                    </button>
                ))}
            </div>
            {selected && (
                <p className="text-xs text-muted-foreground">
                    Tarif berlaku 10 menit. Hitung ulang jika keranjang atau
                    tujuan berubah.
                </p>
            )}
        </section>
    );
}
