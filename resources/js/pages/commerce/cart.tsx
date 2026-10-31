import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowRight, ShoppingBag, Trash2 } from 'lucide-react';
import {
    Errors,
    ShopHeader,
    ShopFooter,
    inputClass,
    money,
} from '@/components/commerce-ui';
import { PurchaseSteps } from '@/components/purchase-steps';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/cart';
import { index as checkout } from '@/routes/checkout';
import { index as catalog } from '@/routes/catalog';

type Item = {
    variant_id: number;
    quantity: number;
    name: string;
    label: string;
    price: number;
    discount_amount: number;
    active: boolean;
    available: number;
};
function CartRow({
    item,
    onDirtyChange,
}: {
    item: Item;
    onDirtyChange: (id: number, dirty: boolean) => void;
}) {
    const form = useForm({
        variant_id: item.variant_id,
        quantity: item.quantity,
    });
    return (
        <form
            className="grid gap-4 rounded-2xl border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({ ...data }));
                form.put(update.url(), {
                    preserveScroll: true,
                    onSuccess: () => onDirtyChange(item.variant_id, false),
                });
            }}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">{item.name}</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {item.label} ?{' '}
                        {money(item.price - item.discount_amount)} / unit
                    </p>
                </div>
                <p className="font-semibold text-primary">
                    {money((item.price - item.discount_amount) * item.quantity)}
                </p>
            </div>
            {(!item.active || item.quantity > item.available) && (
                <p className="text-sm text-red-600 dark:text-red-400">
                    Produk tidak aktif atau stok berubah. Ubah jumlah atau hapus
                    sebelum checkout.
                </p>
            )}
            <div className="flex flex-wrap items-end gap-3">
                <label className="grid gap-2 text-xs font-medium">
                    Jumlah
                    <input
                        aria-label={`Jumlah ${item.name}`}
                        className={`${inputClass} max-w-24`}
                        required
                        type="number"
                        min={0}
                        max={99}
                        step={1}
                        value={form.data.quantity}
                        onChange={(e) => {
                            const quantity = Number(e.target.value);
                            form.setData('quantity', quantity);
                            onDirtyChange(
                                item.variant_id,
                                quantity !== item.quantity,
                            );
                        }}
                    />
                </label>
                <Button variant="outline" disabled={form.processing}>
                    Simpan jumlah
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    disabled={form.processing}
                    onClick={() => {
                        form.transform((data) => ({ ...data, quantity: 0 }));
                        form.put(update.url(), {
                            preserveScroll: true,
                            onSuccess: () =>
                                onDirtyChange(item.variant_id, false),
                        });
                    }}
                >
                    <Trash2 className="size-4" />
                    Hapus
                </Button>
            </div>
            <Errors errors={form.errors} />
        </form>
    );
}
export default function Cart({ items }: { items: Item[] }) {
    const { errors } = usePage().props;
    const [dirtyItems, setDirtyItems] = useState<number[]>([]);
    const hasUnsavedItems = dirtyItems.some((id) =>
        items.some((item) => item.variant_id === id),
    );
    const invalidItems = items.some(
        (item) => !item.active || item.quantity > item.available,
    );
    const total = items.reduce(
        (sum, item) =>
            sum + (item.price - item.discount_amount) * item.quantity,
        0,
    );
    function onDirtyChange(id: number, dirty: boolean) {
        setDirtyItems((current) =>
            dirty
                ? [...new Set([...current, id])]
                : current.filter((value) => value !== id),
        );
    }
    return (
        <>
            <Head title="Keranjang" />
            <ShopHeader />
            <main className="mx-auto grid max-w-6xl gap-6 px-5 py-8">
                <PurchaseSteps current={1} />
                <header>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Keranjang belanja
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Periksa pilihan produk dan simpan perubahan jumlah
                        sebelum checkout.
                    </p>
                </header>
                <Errors errors={errors} />
                {items.length ? (
                    <div className="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">
                        <div className="grid gap-4">
                            {items.map((item) => (
                                <CartRow
                                    key={`${item.variant_id}-${item.quantity}`}
                                    item={item}
                                    onDirtyChange={onDirtyChange}
                                />
                            ))}
                            <Link
                                href={catalog()}
                                className="text-sm font-medium text-primary"
                            >
                                Kembali memilih produk
                            </Link>
                        </div>
                        <aside className="grid gap-5 rounded-2xl border bg-card p-6 lg:sticky lg:top-6">
                            <h2 className="text-lg font-semibold">
                                Ringkasan belanja
                            </h2>
                            <div className="flex justify-between text-sm">
                                <span>
                                    {items.reduce(
                                        (sum, item) => sum + item.quantity,
                                        0,
                                    )}{' '}
                                    unit produk
                                </span>
                                <span>{money(total)}</span>
                            </div>
                            <div className="flex justify-between border-t pt-4 font-semibold">
                                <span>Total produk</span>
                                <span className="text-primary">
                                    {money(total)}
                                </span>
                            </div>
                            <p className="text-xs leading-6 text-muted-foreground">
                                Ongkir dan total akhir ditampilkan pada halaman
                                checkout. Pembayaran melalui transfer manual.
                            </p>
                            {invalidItems || hasUnsavedItems ? (
                                <>
                                    <Button disabled className="h-12">
                                        Lanjut checkout
                                        <ArrowRight className="size-4" />
                                    </Button>
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        {hasUnsavedItems
                                            ? 'Simpan perubahan jumlah terlebih dahulu.'
                                            : 'Perbaiki produk atau stok yang bermasalah terlebih dahulu.'}
                                    </p>
                                </>
                            ) : (
                                <Button asChild className="h-12">
                                    <Link href={checkout()}>
                                        Lanjut checkout
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </aside>
                    </div>
                ) : (
                    <section className="grid justify-items-center gap-4 rounded-2xl border border-dashed bg-card p-12 text-center">
                        <ShoppingBag className="size-10 text-primary" />
                        <h2 className="text-lg font-semibold">
                            Keranjang masih kosong
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Pilih produk madu untuk mulai berbelanja.
                        </p>
                        <Button asChild>
                            <Link href={catalog()}>
                                Jelajahi katalog
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </section>
                )}
            </main>
            <ShopFooter />
        </>
    );
}
