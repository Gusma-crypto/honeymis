import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Eye,
    Leaf,
    Package,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import {
    ShopHeader,
    ShopFooter,
    Field,
    Pager,
    inputClass,
    money,
} from '@/components/commerce-ui';
import { ProductImage } from '@/components/product-image';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/catalog';
import type { CatalogProduct, Paginated } from '@/types/commerce';

type Props = {
    products: Paginated<CatalogProduct>;
    categories: { id: number; name: string }[];
    filters: { search: string; category: string | number };
};
export default function Catalog({ products, categories, filters }: Props) {
    const form = useForm(filters);
    return (
        <>
            <Head title="Katalog madu" />
            <ShopHeader />
            <main className="mx-auto flex max-w-6xl flex-col gap-10 px-5 py-6 sm:py-9">
                <section className="grid overflow-hidden rounded-3xl border border-emerald-800 bg-emerald-950 text-white lg:grid-cols-2">
                    <div className="store-reveal flex flex-col items-start justify-center p-7 sm:p-10 lg:py-14">
                        <p className="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-900/70 px-3 py-1.5 text-[11px] font-medium tracking-widest text-emerald-200 uppercase">
                            <Leaf className="size-3.5" />
                            Pilihan madu Nusantara
                        </p>
                        <h1 className="mt-5 max-w-lg text-4xl leading-[1.12] font-semibold tracking-tight sm:text-5xl">
                            Kebaikan alami,
                            <br />
                            <span className="text-emerald-300">
                                di setiap tetesnya.
                            </span>
                        </h1>
                        <p className="mt-5 max-w-md text-sm leading-7 text-emerald-100/80">
                            Temukan pilihan madu dari beragam bunga dan daerah.
                            Pilih kemasan yang sesuai untuk menemani keseharian
                            Anda.
                        </p>
                        <Button
                            asChild
                            className="store-button group mt-7 h-11 rounded-full bg-emerald-300 px-6 text-emerald-950 hover:bg-emerald-200"
                        >
                            <a href="#pilihan-produk">
                                Jelajahi koleksi
                                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transform-none" />
                            </a>
                        </Button>
                        <div className="mt-8 flex flex-wrap gap-x-5 gap-y-3 text-xs text-emerald-100/80">
                            <span className="inline-flex items-center gap-2">
                                <Package className="size-4 text-emerald-300" />
                                Pilihan ukuran kemasan
                            </span>
                            <span className="inline-flex items-center gap-2">
                                <Leaf className="size-4 text-emerald-300" />
                                Madu bunga & hutan
                            </span>
                        </div>
                    </div>
                    <figure className="relative min-h-72 lg:min-h-full">
                        <img
                            src="/honey-hero.jpg"
                            alt="Ilustrasi koleksi madu dengan sarang madu, sendok kayu, dan dedaunan"
                            width={1280}
                            height={853}
                            fetchPriority="high"
                            className="store-hero-image absolute inset-0 size-full object-cover"
                        />
                        <figcaption className="absolute right-4 bottom-4 rounded-full bg-white/90 px-3 py-1 text-[10px] font-medium text-emerald-950">
                            Ilustrasi koleksi HoneyCommerce
                        </figcaption>
                    </figure>
                </section>
                <section
                    id="pilihan-produk"
                    className="store-reveal scroll-mt-6 space-y-6"
                >
                    <header className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                                Koleksi kami
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                                Temukan madu pilihan Anda
                            </h2>
                        </div>
                        <p className="max-w-sm text-sm leading-6 text-muted-foreground">
                            Lihat varian, harga, dan ketersediaan sebelum
                            menambahkan produk ke keranjang.
                        </p>
                    </header>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.get(index.url(), {
                                preserveState: true,
                                preserveScroll: true,
                            });
                        }}
                        className="flex flex-wrap items-end gap-4 rounded-2xl border bg-card p-5 shadow-sm"
                    >
                        <div className="min-w-48 flex-1">
                            <Field label="Cari produk">
                                <span className="relative">
                                    <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <input
                                        type="search"
                                        maxLength={100}
                                        className={`${inputClass} pl-9`}
                                        value={form.data.search}
                                        onChange={(e) =>
                                            form.setData(
                                                'search',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Madu hutan, randu, kelengkeng..."
                                    />
                                </span>
                            </Field>
                        </div>
                        <div className="min-w-44">
                            <Field label="Kategori">
                                <select
                                    className={inputClass}
                                    value={form.data.category}
                                    onChange={(e) =>
                                        form.setData('category', e.target.value)
                                    }
                                >
                                    <option value="">Semua kategori</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <Button disabled={form.processing} className="h-10">
                            <SlidersHorizontal className="size-4" />
                            {form.processing ? 'Mencari...' : 'Terapkan'}
                        </Button>
                        {(filters.search || filters.category) && (
                            <Button variant="outline" asChild className="h-10">
                                <Link href={index()} preserveScroll>
                                    Reset
                                </Link>
                            </Button>
                        )}
                    </form>
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {products.data.map((product, productIndex) => (
                            <Link
                                key={product.id}
                                style={{
                                    animationDelay: `${Math.min(productIndex, 5) * 55}ms`,
                                }}
                                href={show(product.slug)}
                                className="store-product-card group overflow-hidden rounded-2xl border bg-card hover:border-primary/40 hover:shadow-xl hover:shadow-emerald-950/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <ProductImage
                                    name={product.name}
                                    imageUrl={product.image_url}
                                    className="aspect-square"
                                />
                                <div className="flex flex-col gap-3 p-5">
                                    <p className="inline-flex items-center gap-1.5 self-start rounded-full bg-accent/50 px-2.5 py-1 text-[10px] font-semibold tracking-wider text-primary uppercase">
                                        <Leaf
                                            aria-hidden="true"
                                            className="size-3"
                                        />
                                        {product.category}
                                    </p>
                                    <h3 className="line-clamp-2 min-h-12 text-lg leading-snug font-semibold transition-colors group-hover:text-primary">
                                        {product.name}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        {product.variants.length} pilihan
                                        kemasan
                                    </p>
                                    <div className="mt-1 flex items-end justify-between gap-3 border-t pt-4">
                                        <div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Harga mulai
                                            </p>
                                            <p className="mt-1 text-lg font-semibold text-primary">
                                                {product.variants.length
                                                    ? money(
                                                          Math.min(
                                                              ...product.variants.map(
                                                                  (variant) =>
                                                                      variant.price -
                                                                      variant.discount_amount,
                                                              ),
                                                          ),
                                                      )
                                                    : 'Belum tersedia'}
                                            </p>
                                        </div>
                                        <span className="store-product-action flex h-10 shrink-0 items-center justify-center gap-2 rounded-full bg-accent px-3 text-accent-foreground group-hover:bg-primary group-hover:text-primary-foreground">
                                            <Eye
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                            <span className="text-xs font-semibold">
                                                Detail
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                    {products.data.length === 0 && (
                        <div className="rounded-2xl border border-dashed bg-card p-10 text-center">
                            <Search className="mx-auto mb-3 size-7 text-primary" />
                            <p className="font-medium">
                                Produk belum ditemukan
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Coba kata kunci lain atau pilih semua kategori.
                            </p>
                        </div>
                    )}
                    <Pager page={products} />
                </section>
            </main>
            <ShopFooter />
        </>
    );
}
