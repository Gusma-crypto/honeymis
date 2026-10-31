import { ArrowRight, LogIn, ShoppingBasket } from 'lucide-react';
import { index as checkout } from '@/routes/checkout';
import { ProductImage } from '@/components/product-image';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ShopHeader,
    ShopFooter,
    Errors,
    Field,
    inputClass,
    money,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/cart';
import { login } from '@/routes';
import type { CatalogProduct } from '@/types/commerce';
export default function Product({ product }: { product: CatalogProduct }) {
    const { auth } = usePage().props;
    const form = useForm({
        variant_id: product.variants[0]?.id ?? 0,
        quantity: 1,
    });
    const variant = product.variants.find(
        (item) => item.id === form.data.variant_id,
    );
    return (
        <>
            <Head title={product.name} />
            <ShopHeader />
            <main className="store-reveal mx-auto grid max-w-5xl gap-10 px-5 py-12 md:grid-cols-2">
                <div className="group">
                    <ProductImage
                        name={product.name}
                        imageUrl={product.image_url}
                        priority
                        className="aspect-square rounded-2xl border"
                    />
                </div>
                <section className="flex flex-col gap-5">
                    <p className="text-sm text-muted-foreground">
                        {product.category} / {product.weight_g} gram
                    </p>
                    <h1 className="text-3xl font-bold">{product.name}</h1>
                    <p className="whitespace-pre-line text-muted-foreground">
                        {product.description}
                    </p>
                    <form
                        id="pilih-kemasan"
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const button = (e.nativeEvent as SubmitEvent)
                                .submitter;
                            const goToCheckout =
                                button instanceof HTMLButtonElement &&
                                button.value === 'checkout';
                            form.put(update.url(), {
                                onSuccess: () => {
                                    if (goToCheckout) router.visit(checkout());
                                },
                            });
                        }}
                    >
                        <Field label="Varian">
                            <select
                                className={inputClass}
                                value={form.data.variant_id}
                                onChange={(e) =>
                                    form.setData(
                                        'variant_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                {product.variants.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {variant && (
                            <>
                                <p className="text-2xl font-bold text-primary">
                                    {money(
                                        variant.price - variant.discount_amount,
                                    )}
                                </p>
                                {variant.discount_amount > 0 && (
                                    <p className="text-sm text-muted-foreground line-through">
                                        {money(variant.price)}
                                    </p>
                                )}
                                <p className="text-sm">
                                    Tersedia {variant.available} unit
                                </p>
                            </>
                        )}
                        <Field label="Jumlah">
                            <input
                                type="number"
                                required
                                step={1}
                                min={1}
                                max={Math.min(99, variant?.available ?? 0)}
                                className={inputClass}
                                value={form.data.quantity}
                                onChange={(e) =>
                                    form.setData(
                                        'quantity',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <Errors errors={form.errors} />
                        {auth.user ? (
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Button
                                    type="submit"
                                    name="intent"
                                    value="cart"
                                    variant="outline"
                                    disabled={
                                        form.processing ||
                                        !variant ||
                                        variant.available < 1
                                    }
                                    className="store-button h-12 rounded-full"
                                >
                                    <ShoppingBasket
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Tambah ke keranjang
                                </Button>
                                <Button
                                    type="submit"
                                    name="intent"
                                    value="checkout"
                                    disabled={
                                        form.processing ||
                                        !variant ||
                                        variant.available < 1
                                    }
                                    className="store-button h-12 rounded-full"
                                >
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    {form.processing
                                        ? 'Memproses...'
                                        : 'Checkout sekarang'}
                                </Button>
                                <p className="text-xs leading-5 text-muted-foreground sm:col-span-2">
                                    Checkout mencakup seluruh isi keranjang
                                    Anda. Alamat dan total pembayaran diperiksa
                                    pada langkah berikutnya.
                                </p>
                            </div>
                        ) : (
                            <Button
                                asChild
                                className="store-button h-12 rounded-full"
                            >
                                <Link href={login()}>
                                    <LogIn
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Masuk untuk checkout
                                </Link>
                            </Button>
                        )}
                    </form>
                </section>
            </main>
            <ShopFooter />
        </>
    );
}
