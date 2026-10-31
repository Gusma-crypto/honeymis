import { useState } from 'react';
import { ShippingPicker } from '@/components/shipping-picker';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Landmark, ShieldCheck } from 'lucide-react';
import {
    Errors,
    Field,
    ShopHeader,
    ShopFooter,
    inputClass,
    money,
    useRequestKey,
} from '@/components/commerce-ui';
import { PurchaseSteps } from '@/components/purchase-steps';
import { Button } from '@/components/ui/button';
import { confirmAction } from '@/lib/sweet-alert';
import { store } from '@/routes/checkout';
import { index as cart } from '@/routes/cart';
import type { Quote } from '@/types/commerce';
export default function Checkout({ quote }: { quote: Quote }) {
    const [shippingChoice, setShippingChoice] = useState<{
        base: string;
        quote: Quote;
    } | null>(null);
    const currentQuote =
        shippingChoice?.base === quote.fingerprint
            ? shippingChoice.quote
            : quote;
    const requestKey = useRequestKey();
    const { auth } = usePage().props;
    const form = useForm({
        recipient: auth.user.name,
        phone: auth.user.whatsapp_number ?? '',
        province: '',
        city: '',
        district: '',
        postal_code: '',
        address: '',
        quote_fingerprint: quote.fingerprint,
        idempotency_key: '',
        shipping_quote_token: '',
    });
    const fields = {
        recipient: 'Nama penerima',
        phone: 'Nomor telepon',
        province: 'Provinsi',
        city: 'Kota / kabupaten',
        district: 'Kecamatan',
        postal_code: 'Kode pos',
    } as const;
    return (
        <>
            <Head title="Checkout" />
            <ShopHeader />
            <main className="mx-auto grid w-full max-w-6xl gap-6 px-5 py-8">
                <PurchaseSteps current={2} />
                <header>
                    <Link
                        href={cart()}
                        className="mb-3 inline-flex items-center gap-2 text-sm font-medium text-primary"
                    >
                        <ArrowLeft className="size-4" />
                        Kembali ke keranjang
                    </Link>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Selesaikan pesanan Anda
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Isi alamat, periksa total, lalu lanjutkan ke petunjuk
                        pembayaran.
                    </p>
                </header>
                <div className="grid items-start gap-6 lg:grid-cols-[1.3fr_1fr]">
                    <div className="grid gap-5">
                        <form
                            id="checkout-form"
                            className="grid gap-5 rounded-2xl border bg-card p-6"
                            onSubmit={async (e) => {
                                e.preventDefault();
                                if (
                                    currentQuote.shipping_required ||
                                    form.processing ||
                                    !(await confirmAction(
                                        'Buat pesanan dan lanjut pembayaran?',
                                        `Total ${money(currentQuote.grand_total)}. Pembayaran melalui transfer manual; instruksi ditampilkan setelah pesanan dibuat.`,
                                    ))
                                )
                                    return;
                                form.transform((data) => ({
                                    ...data,
                                    quote_fingerprint: currentQuote.fingerprint,
                                    idempotency_key: requestKey.get(),
                                }));
                                form.post(store.url());
                            }}
                        >
                            <h2 className="text-lg font-semibold">
                                Alamat pengiriman
                            </h2>
                            {quote.shipping.provider === 'rajaongkir' && (
                                <ShippingPicker
                                    key={quote.fingerprint}
                                    weight={quote.weight_g}
                                    onClear={() => {
                                        setShippingChoice(null);
                                        form.setData(
                                            'shipping_quote_token',
                                            '',
                                        );
                                    }}
                                    onSelect={(option) => {
                                        setShippingChoice({
                                            base: quote.fingerprint,
                                            quote: option.quote,
                                        });
                                        form.setData((data) => ({
                                            ...data,
                                            shipping_quote_token: option.token,
                                            province:
                                                option.destination.province,
                                            city: option.destination.city,
                                            district:
                                                option.destination.district,
                                            postal_code:
                                                option.destination.postal_code,
                                        }));
                                        form.clearErrors(
                                            'shipping_quote_token',
                                        );
                                    }}
                                />
                            )}

                            <div className="grid gap-4 sm:grid-cols-2">
                                {Object.entries(fields).map(([key, label]) => (
                                    <Field key={key} label={label}>
                                        <input
                                            className={inputClass}
                                            required
                                            readOnly={
                                                quote.shipping.provider ===
                                                    'rajaongkir' &&
                                                [
                                                    'province',
                                                    'city',
                                                    'district',
                                                    'postal_code',
                                                ].includes(key)
                                            }
                                            type={
                                                key === 'phone' ? 'tel' : 'text'
                                            }
                                            inputMode={
                                                key === 'postal_code'
                                                    ? 'numeric'
                                                    : undefined
                                            }
                                            autoComplete={
                                                key === 'recipient'
                                                    ? 'shipping name'
                                                    : key === 'phone'
                                                      ? 'shipping tel'
                                                      : key === 'postal_code'
                                                        ? 'shipping postal-code'
                                                        : 'off'
                                            }
                                            maxLength={
                                                key === 'recipient'
                                                    ? 150
                                                    : key === 'phone'
                                                      ? 30
                                                      : key === 'postal_code'
                                                        ? 12
                                                        : 100
                                            }
                                            value={
                                                form.data[
                                                    key as keyof typeof fields
                                                ]
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    key as keyof typeof fields,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                ))}
                            </div>
                            <Field label="Alamat lengkap">
                                <textarea
                                    required
                                    rows={3}
                                    maxLength={1000}
                                    autoComplete="shipping street-address"
                                    className={inputClass}
                                    value={form.data.address}
                                    onChange={(e) =>
                                        form.setData('address', e.target.value)
                                    }
                                    placeholder="Nama jalan, nomor rumah, RT/RW, dan patokan"
                                />
                            </Field>
                            <Errors errors={form.errors} />
                        </form>
                        <section className="rounded-2xl border border-primary/20 bg-accent/35 p-6">
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Landmark className="size-5 text-primary" />
                                Transfer bank manual
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                Setelah pesanan dibuat, buka petunjuk pembayaran
                                dan transfer ke rekening resmi toko. Unggah
                                bukti transfer agar Finance dapat memverifikasi
                                pembayaran.
                            </p>
                            <p className="mt-3 inline-flex items-start gap-2 text-xs leading-5 text-primary">
                                <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                                Status pesanan berubah menjadi dibayar setelah
                                verifikasi selesai.
                            </p>
                        </section>
                    </div>
                    <aside className="grid gap-5 rounded-2xl border bg-card p-6 shadow-sm lg:sticky lg:top-6">
                        <h2 className="text-lg font-semibold">
                            Ringkasan pesanan
                        </h2>
                        <div className="grid gap-4">
                            {quote.lines.map((line) => (
                                <div
                                    key={line.variant_id}
                                    className="flex justify-between gap-4 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {line.name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {line.quantity} unit
                                        </p>
                                    </div>
                                    <span className="shrink-0 font-medium">
                                        {money(line.total)}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <dl className="grid gap-3 border-t pt-4 text-sm">
                            <div className="flex justify-between">
                                <dt>Subtotal</dt>
                                <dd>{money(quote.subtotal)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>Diskon produk</dt>
                                <dd>-{money(quote.discount_total)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>Ongkir</dt>
                                <dd>
                                    {currentQuote.shipping_required
                                        ? 'Pilih pengiriman'
                                        : money(currentQuote.shipping_total)}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t pt-4 text-lg font-semibold">
                                <dt>
                                    {currentQuote.shipping_required
                                        ? 'Total sementara'
                                        : 'Total pembayaran'}
                                </dt>
                                <dd className="text-primary">
                                    {money(currentQuote.grand_total)}
                                </dd>
                            </div>
                        </dl>
                        <Button
                            type="submit"
                            form="checkout-form"
                            disabled={
                                form.processing ||
                                quote.lines.length === 0 ||
                                currentQuote.shipping_required
                            }
                            className="h-auto min-h-12 py-3 text-center whitespace-normal"
                        >
                            {form.processing
                                ? 'Membuat pesanan...'
                                : 'Buat pesanan & lanjut pembayaran'}
                            <ArrowRight className="size-4 shrink-0" />
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            {currentQuote.shipping_required
                                ? 'Pilih tujuan dan layanan kurir untuk mendapatkan total akhir.'
                                : `Pengiriman: ${currentQuote.shipping.courier?.toUpperCase() ?? ''} ${currentQuote.shipping.service}`}
                        </p>
                        <p className="text-xs leading-5 text-muted-foreground">
                            Periksa alamat dan rincian pesanan. Anda akan
                            diarahkan ke halaman pembayaran setelah berhasil.
                        </p>
                    </aside>
                </div>
            </main>
            <ShopFooter />
        </>
    );
}
