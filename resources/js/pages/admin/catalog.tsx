import { MenuTitle } from '@/components/menu-title';
import { Package as MenuIcon } from 'lucide-react';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    StatusBadge,
} from '@/components/data-list';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Boxes,
    Layers3,
    ShoppingCart,
    Star,
    Download,
    Upload,
    Plus,
    Search,
    LayoutGrid,
    List,
    Filter,
    Eye,
    Pencil,
    Copy,
    Trash2,
    Heart,
    ChevronLeft,
    ChevronRight,
    Droplet,
    Leaf,
    Tag,
    Camera,
    ImagePlus,
    X,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from '@/components/ui/dialog';
import { confirmAction } from '@/lib/sweet-alert';
import type { Auth } from '@/types/auth';
import { Errors, Field, inputClass, money } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import {
    index,
    store,
    update,
    archive,
    importMethod,
    exportMethod,
} from '@/routes/admin/catalog';
import {
    store as createCategory,
    update as updateCategory,
} from '@/routes/admin/categories';
import type { Paginated } from '@/types/commerce';
const CloseProductDialog = createContext<() => void>(() => {});

function ProductPhotoPreview({
    src,
    name,
    className,
}: {
    src: string;
    name: string;
    className?: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className={`product-photo-zoom ${className ?? ''}`}
                    title="Klik untuk memperbesar foto"
                    aria-label={`Preview foto ${name}`}
                >
                    <img src={src} alt={name} loading="lazy" />
                </button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Preview foto produk</DialogTitle>
                    <DialogDescription>{name}</DialogDescription>
                </DialogHeader>
                <img
                    src={src}
                    alt={`Preview ${name}`}
                    className="mx-auto max-h-[70dvh] w-full rounded-xl bg-white object-contain"
                />
                <p className="text-center text-xs text-muted-foreground">
                    Foto ditampilkan dengan perbandingan ukuran aslinya.
                </p>
            </DialogContent>
        </Dialog>
    );
}

type Category = {
    id: number;
    name: string;
    slug: string;
    status: string;
    product_count: number;
};
type Variant = {
    id: number | null;
    sku: string;
    attributes?: { label?: string };
    label: string;
    price: number;
    member_price?: number | null;
    cost: number;
    discount_amount: number;
    status: string;
};
type Product = {
    id: number;
    category_id: number;
    sku: string;
    name: string;
    slug: string;
    description: string;
    status: string;
    weight_g: number;
    commission_eligible: boolean;
    variants: Variant[];
    image_url: string | null;
    stock: number;
    sold: number;
};
const blankVariant = (): Variant => ({
    id: null,
    sku: '',
    label: '',
    price: 150000,
    cost: 0,
    discount_amount: 0,
    status: 'draft',
});
function CategoryEditor({ category }: { category?: Category }) {
    const form = useForm({
        name: category?.name ?? '',
        slug: category?.slug ?? '',
        status: category?.status ?? 'active',
    });
    return (
        <form
            className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            onSubmit={(e) => {
                e.preventDefault();
                if (category) form.put(updateCategory.url(category.id));
                else
                    form.post(createCategory.url(), {
                        onSuccess: () => form.reset(),
                    });
            }}
        >
            <Field label="Nama kategori">
                <input
                    required
                    className={inputClass}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
            </Field>
            <Field label="Slug">
                <input
                    required
                    className={inputClass}
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                />
            </Field>
            <Field label="Status">
                <select
                    className={inputClass}
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    {['active', 'draft', 'inactive'].map((status) => (
                        <option key={status} value={status}>
                            {
                                (
                                    {
                                        active: 'Aktif',
                                        draft: 'Draf',
                                        inactive: 'Nonaktif',
                                    } as Record<string, string>
                                )[status]
                            }
                        </option>
                    ))}
                </select>
            </Field>
            <Button disabled={form.processing}>
                {category ? 'Simpan kategori' : 'Tambah kategori'}
            </Button>
            <Errors errors={form.errors} />
        </form>
    );
}
function ProductEditor({
    product,
    categories,
    duplicate = false,
}: {
    product?: Product;
    categories: Category[];
    duplicate?: boolean;
}) {
    const closeDialog = useContext(CloseProductDialog);
    const form = useForm({
        category_id: product?.category_id ?? categories[0]?.id ?? 0,
        image: null as File | null,
        sku: product ? `${product.sku}${duplicate ? '-COPY' : ''}` : '',
        name: product ? `${product.name}${duplicate ? ' (Salinan)' : ''}` : '',
        slug: product ? `${product.slug}${duplicate ? '-copy' : ''}` : '',
        description: product?.description ?? '',
        status: duplicate ? 'draft' : (product?.status ?? 'draft'),
        weight_g: product?.weight_g ?? 500,
        commission_eligible: product?.commission_eligible ?? false,
        variants: product?.variants.map((variant) => ({
            ...variant,
            id: duplicate ? null : variant.id,
            sku: `${variant.sku}${duplicate ? '-COPY' : ''}`,
            status: duplicate ? 'draft' : variant.status,
            label: variant.attributes?.label ?? variant.sku,
        })) ?? [blankVariant()],
    });
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    useEffect(() => {
        if (!form.data.image) {
            setPhotoPreview(null);
            return;
        }
        const url = URL.createObjectURL(form.data.image);
        setPhotoPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.image]);
    const choosePhoto = (file: File | null) => {
        if (
            file &&
            (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
                file.size > 5 * 1024 * 1024)
        ) {
            form.setError(
                'image',
                'Gunakan foto JPG, PNG, atau WebP, maksimal 5 MB.',
            );
            return;
        }
        form.clearErrors('image');
        form.setData('image', file);
    };
    const currentPhoto =
        photoPreview ?? (!duplicate ? product?.image_url : null);
    return (
        <section className="flex flex-col gap-5 rounded-xl border p-5">
            <form
                className="flex flex-col gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (product && !duplicate) {
                        form.transform((data) => ({ ...data, _method: 'put' }));
                        form.post(update.url(product.id), {
                            forceFormData: true,
                            onSuccess: closeDialog,
                        });
                    } else {
                        form.post(store.url(), {
                            forceFormData: true,
                            onSuccess: closeDialog,
                        });
                    }
                }}
            >
                <h3 className="text-lg font-semibold">
                    {product ? product.name : 'Produk baru'}
                </h3>
                <section
                    className="product-photo-editor"
                    aria-label="Foto produk"
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => {
                        event.preventDefault();
                        choosePhoto(event.dataTransfer.files[0] ?? null);
                    }}
                >
                    <div className="product-photo-preview">
                        {currentPhoto ? (
                            <ProductPhotoPreview
                                src={currentPhoto}
                                name={form.data.name || 'Produk baru'}
                            />
                        ) : (
                            <div>
                                <Camera />
                                <span>Foto produk</span>
                            </div>
                        )}
                    </div>
                    <div className="product-photo-controls">
                        <span className="product-photo-tag">
                            <ImagePlus />
                            Foto Produk
                        </span>
                        <h4>
                            {currentPhoto
                                ? 'Tampilkan produk dari sisi terbaiknya'
                                : 'Tambahkan foto produk Anda'}
                        </h4>
                        <p>
                            Pilih atau seret foto ke area ini. Gunakan JPG, PNG,
                            atau WebP, maksimal 5 MB.
                        </p>
                        <label className="product-photo-picker">
                            <Camera />
                            {currentPhoto ? 'Ganti foto' : 'Pilih foto'}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                aria-label={
                                    currentPhoto
                                        ? 'Ganti foto produk'
                                        : 'Pilih foto produk'
                                }
                                onChange={(event) =>
                                    choosePhoto(event.target.files?.[0] ?? null)
                                }
                            />
                        </label>
                        {form.data.image && (
                            <div className="product-photo-selected">
                                <span>{form.data.image.name}</span>
                                <button
                                    type="button"
                                    onClick={() => choosePhoto(null)}
                                >
                                    <X />
                                    Batalkan pilihan
                                </button>
                            </div>
                        )}
                        <p className="product-photo-hint">
                            Foto akan tersimpan bersama data produk saat Anda
                            menekan Simpan produk.
                        </p>
                        {form.errors.image && (
                            <p className="text-sm text-red-600" role="alert">
                                {form.errors.image}
                            </p>
                        )}
                    </div>
                </section>
                <div className="grid gap-4 md:grid-cols-2">
                    {(['name', 'sku', 'slug'] as const).map((field) => (
                        <Field
                            key={field}
                            label={
                                {
                                    name: 'Nama produk',
                                    sku: 'SKU produk',
                                    slug: 'Slug produk',
                                }[field]
                            }
                        >
                            <input
                                required
                                className={inputClass}
                                value={form.data[field]}
                                onChange={(e) =>
                                    form.setData(field, e.target.value)
                                }
                            />
                        </Field>
                    ))}
                    <Field label="Kategori">
                        <select
                            className={inputClass}
                            value={form.data.category_id}
                            onChange={(e) =>
                                form.setData(
                                    'category_id',
                                    Number(e.target.value),
                                )
                            }
                        >
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Status produk">
                        <select
                            className={inputClass}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {['draft', 'active', 'inactive'].map((status) => (
                                <option key={status} value={status}>
                                    {
                                        (
                                            {
                                                active: 'Aktif',
                                                draft: 'Draf',
                                                inactive: 'Nonaktif',
                                            } as Record<string, string>
                                        )[status]
                                    }
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Berat (gram)">
                        <input
                            className={inputClass}
                            type="number"
                            min={1}
                            value={form.data.weight_g}
                            onChange={(e) =>
                                form.setData('weight_g', Number(e.target.value))
                            }
                        />
                    </Field>
                </div>
                <Field label="Deskripsi">
                    <textarea
                        className={inputClass}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                </Field>
                <h4 className="font-semibold">
                    Varian - seluruh nominal dalam rupiah
                </h4>
                {form.data.variants.map((variant, i) => (
                    <div
                        key={variant.id ?? `new-${i}`}
                        className="grid gap-3 rounded-lg bg-muted p-4 md:grid-cols-3"
                    >
                        {(
                            [
                                'sku',
                                'label',
                                'price',
                                'cost',
                                'discount_amount',
                            ] as const
                        ).map((field) => (
                            <Field
                                key={field}
                                label={
                                    {
                                        sku: 'SKU varian',
                                        label: 'Nama varian',
                                        price: 'Harga retail',
                                        cost: 'Harga pokok',
                                        discount_amount: 'Diskon (Rp)',
                                    }[field]
                                }
                            >
                                <input
                                    required
                                    className={inputClass}
                                    type={
                                        [
                                            'price',
                                            'cost',
                                            'discount_amount',
                                        ].includes(field)
                                            ? 'number'
                                            : 'text'
                                    }
                                    min={0}
                                    value={variant[field]}
                                    onChange={(e) =>
                                        form.setData(
                                            'variants',
                                            form.data.variants.map(
                                                (row, index) =>
                                                    index === i
                                                        ? {
                                                              ...row,
                                                              [field]: [
                                                                  'price',
                                                                  'cost',
                                                                  'discount_amount',
                                                              ].includes(field)
                                                                  ? Number(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                  : e.target
                                                                        .value,
                                                          }
                                                        : row,
                                            ),
                                        )
                                    }
                                />
                            </Field>
                        ))}
                        <Field label="Status varian">
                            <select
                                className={inputClass}
                                value={variant.status}
                                onChange={(e) =>
                                    form.setData(
                                        'variants',
                                        form.data.variants.map((row, index) =>
                                            index === i
                                                ? {
                                                      ...row,
                                                      status: e.target.value,
                                                  }
                                                : row,
                                        ),
                                    )
                                }
                            >
                                {['draft', 'active', 'inactive'].map(
                                    (status) => (
                                        <option key={status} value={status}>
                                            {
                                                (
                                                    {
                                                        active: 'Aktif',
                                                        draft: 'Draf',
                                                        inactive: 'Nonaktif',
                                                    } as Record<string, string>
                                                )[status]
                                            }
                                        </option>
                                    ),
                                )}
                            </select>
                        </Field>
                        <Field label="Harga member (opsional)">
                            <input
                                className={inputClass}
                                type="number"
                                min={1}
                                max={variant.price}
                                value={variant.member_price ?? ''}
                                onChange={(event) =>
                                    form.setData(
                                        'variants',
                                        form.data.variants.map((row, index) =>
                                            index === i
                                                ? {
                                                      ...row,
                                                      member_price:
                                                          event.target.value ===
                                                          ''
                                                              ? null
                                                              : Number(
                                                                    event.target
                                                                        .value,
                                                                ),
                                                  }
                                                : row,
                                        ),
                                    )
                                }
                            />
                        </Field>
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    className="self-start"
                    onClick={() =>
                        form.setData('variants', [
                            ...form.data.variants,
                            blankVariant(),
                        ])
                    }
                >
                    Tambah varian
                </Button>
                <label className="flex gap-3">
                    <input
                        type="checkbox"
                        checked={form.data.commission_eligible}
                        onChange={(e) =>
                            form.setData(
                                'commission_eligible',
                                e.target.checked,
                            )
                        }
                    />
                    Produk memenuhi syarat komisi penjualan MLM
                </label>
                <Errors errors={form.errors} />
                <Button
                    className="self-start"
                    disabled={form.processing || categories.length === 0}
                >
                    Simpan produk
                </Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Stok produk baru dimulai dari nol dan dapat ditambah melalui
                menu Persediaan. Harga member berlaku untuk mitra aktif; sistem
                memilih harga termurah antara promo dan harga member.
            </p>
        </section>
    );
}

type CatalogFilters = {
    search: string;
    status: string;
    category: string | number;
    label: string;
    sort: string;
    per_page: number;
};
type CatalogStats = {
    total_products: number;
    stock: number;
    stock_value: number;
    sold: number;
    new_this_month: number;
    active: number;
    inactive: number;
    draft: number;
    bestseller: { name: string; sold: number } | null;
};
type ProductPage = Paginated<Product> & {
    total: number;
    from: number | null;
    to: number | null;
    per_page: number;
};
const number = (value: number) => new Intl.NumberFormat('id-ID').format(value);

function ProductDialog({
    label,
    icon,
    children,
    primary = false,
}: {
    label: string;
    icon: ReactNode;
    children: ReactNode;
    primary?: boolean;
}) {
    const [open, setOpen] = useState(false);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className={primary ? 'product-primary' : 'product-action'}
                    title={label}
                    aria-label={label}
                >
                    {icon}
                    {primary && label}
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>{label}</DialogTitle>
                    <DialogDescription>
                        Kelola data produk dan periksa informasi sebelum
                        menyimpan.
                    </DialogDescription>
                </DialogHeader>
                <CloseProductDialog.Provider value={() => setOpen(false)}>
                    {open && children}
                </CloseProductDialog.Provider>
            </DialogContent>
        </Dialog>
    );
}
function ImportDialog() {
    const form = useForm<{ file: File | null }>({ file: null });
    return (
        <ProductDialog label="Import Excel" icon={<Upload />} primary>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(importMethod.url(), { preserveScroll: true });
                }}
            >
                <p>
                    Unggah .xlsx atau CSV, maksimal 2 MB dan 100 baris. Gunakan
                    template pada lembar pertama. SKU yang sudah ada akan
                    ditolak; produk dan harga lama tetap tersimpan. Stok awal
                    produk baru adalah nol.
                </p>
                <a
                    className="text-primary underline"
                    href={exportMethod.url({ query: { template: 1 } })}
                >
                    Unduh template CSV untuk Excel
                </a>
                <p className="text-sm text-muted-foreground">
                    Isi kategori sesuai slug kategori yang tersedia. Nominal
                    berupa angka rupiah tanpa titik/koma; status: draft, active,
                    atau inactive; komisi MLM: 1 atau 0. Beberapa varian boleh
                    memakai SKU produk yang sama dengan SKU varian berbeda.
                </p>
                <input
                    required
                    aria-label="File produk Excel atau CSV"
                    type="file"
                    accept=".xlsx,.csv,.txt"
                    onChange={(event) =>
                        form.setData('file', event.target.files?.[0] ?? null)
                    }
                />
                <Errors errors={form.errors} />
                <Button disabled={form.processing || !form.data.file}>
                    {form.processing
                        ? 'Mengimpor...'
                        : 'Validasi dan impor produk'}
                </Button>
            </form>
        </ProductDialog>
    );
}
function ProductActions({
    product,
    categories,
}: {
    product: Product;
    categories: Category[];
}) {
    const [processing, setProcessing] = useState(false);
    return (
        <div className="product-actions">
            <ProductDialog label={`Detail ${product.name}`} icon={<Eye />}>
                {product.image_url && (
                    <ProductPhotoPreview
                        className="product-detail-photo"
                        src={product.image_url}
                        name={product.name}
                    />
                )}
                <DetailFields
                    rows={[
                        ['Nama', product.name],
                        ['SKU', product.sku],
                        [
                            'Kategori',
                            categories.find(
                                (category) =>
                                    category.id === product.category_id,
                            )?.name,
                        ],
                        ['Status', <StatusBadge status={product.status} />],
                        ['Stok tersedia', number(Number(product.stock))],
                        ['Berat', `${product.weight_g} gram`],
                        [
                            'Komisi MLM',
                            product.commission_eligible
                                ? 'Memenuhi syarat'
                                : 'Tidak',
                        ],
                        ['Deskripsi', product.description],
                    ]}
                />
                <DataTable
                    label="Varian produk"
                    rows={product.variants}
                    rowKey={(variant) => variant.sku}
                    columns={[
                        {
                            header: 'Varian',
                            render: (variant) =>
                                variant.attributes?.label ?? variant.sku,
                        },
                        { header: 'SKU', render: (variant) => variant.sku },
                        {
                            header: 'Harga retail',
                            render: (variant) => money(variant.price),
                        },
                        {
                            header: 'Harga member',
                            render: (variant) =>
                                variant.member_price
                                    ? money(variant.member_price)
                                    : 'Belum diatur',
                        },
                        {
                            header: 'Diskon',
                            render: (variant) => money(variant.discount_amount),
                        },
                        {
                            header: 'Status',
                            render: (variant) => (
                                <StatusBadge status={variant.status} />
                            ),
                        },
                    ]}
                />
            </ProductDialog>
            <ProductDialog label={`Edit ${product.name}`} icon={<Pencil />}>
                <ProductEditor product={product} categories={categories} />
            </ProductDialog>
            <ProductDialog label={`Salin ${product.name}`} icon={<Copy />}>
                <ProductEditor
                    product={product}
                    categories={categories}
                    duplicate
                />
            </ProductDialog>
            <button
                type="button"
                className="product-action product-danger"
                disabled={processing || product.status === 'inactive'}
                title="Nonaktifkan produk"
                aria-label={`Nonaktifkan ${product.name}`}
                onClick={async () => {
                    if (
                        !(await confirmAction(
                            'Nonaktifkan produk?',
                            `${product.name} tidak akan tersedia untuk pembelian. Riwayat pesanan tetap tersimpan.`,
                            true,
                        ))
                    )
                        return;
                    setProcessing(true);
                    router.delete(archive.url(product.id), {
                        preserveScroll: true,
                        onFinish: () => setProcessing(false),
                    });
                }}
            >
                <Trash2 />
            </button>
        </div>
    );
}
function ProductBadges({ product }: { product: Product }) {
    return (
        <span className="product-badges">
            {Number(product.sold) > 0 && (
                <span className="badge-gold">Bestseller</span>
            )}
            {product.commission_eligible && (
                <span className="badge-purple">MLM</span>
            )}
            {product.status !== 'active' && (
                <span className="badge-muted">
                    {product.status === 'draft' ? 'Draf' : 'Nonaktif'}
                </span>
            )}
        </span>
    );
}
export default function CatalogManagement({
    products,
    categories,
    filters,
    stats,
}: {
    products: ProductPage;
    categories: Category[];
    filters: CatalogFilters;
    stats: CatalogStats;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [search, setSearch] = useState(filters.search);
    const [showFilters, setShowFilters] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const [favorites, setFavorites] = useState<number[]>(() => {
        try {
            const stored = JSON.parse(
                localStorage.getItem(
                    `honey-product-favorites-${auth.user.id}`,
                ) ?? '[]',
            );
            return Array.isArray(stored)
                ? stored.filter(
                      (value): value is number =>
                          typeof value === 'number' && Number.isInteger(value),
                  )
                : [];
        } catch {
            return [];
        }
    });
    const apply = (changes: Partial<CatalogFilters> = {}) => {
        setSelected([]);
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true },
        );
    };
    const favorite = (id: number) => {
        const values = favorites.includes(id)
            ? favorites.filter((value) => value !== id)
            : [...favorites, id];
        setFavorites(values);
        try {
            localStorage.setItem(
                `honey-product-favorites-${auth.user.id}`,
                JSON.stringify(values),
            );
        } catch {
            /* Browser storage may be unavailable. */
        }
    };
    const tiles = [
        {
            title: 'Total Produk',
            value: number(stats.total_products),
            icon: <MenuIcon />,
            tone: 'blue',
            note: (
                <>
                    <span className="product-growth">
                        Aktif: {stats.active}
                    </span>{' '}
                    Nonaktif: {stats.inactive}
                </>
            ),
        },
        {
            title: 'Total Stok',
            value: number(stats.stock),
            icon: <Layers3 />,
            tone: 'green',
            note: <>Nilai Stok: {money(stats.stock_value)}</>,
        },
        {
            title: 'Produk Terjual',
            value: number(stats.sold),
            icon: <ShoppingCart />,
            tone: 'gold',
            note: <>Transaksi produk terbayar valid</>,
        },
        {
            title: 'Produk Terlaris',
            value: stats.bestseller?.name ?? 'Belum ada penjualan',
            icon: <Star />,
            tone: 'pink',
            note: <>{number(stats.bestseller?.sold ?? 0)} terjual</>,
        },
    ];
    const setLayout = (mode: 'grid' | 'list') => {
        setView(mode);
        setSelected([]);
    };
    return (
        <>
            <Head title="Produk" />
            <main className={`product-workspace product-view-${view}`}>
                <header className="product-heading">
                    <div>
                        <MenuTitle icon={MenuIcon} tone="amber">
                            {view === 'list' ? 'Kelola Produk' : 'Produk'}
                        </MenuTitle>
                        <p>
                            {view === 'list'
                                ? 'Tambah, ubah, dan atur produk madu untuk penjualan, paket, dan program MLM.'
                                : 'Kelola data produk madu, harga, stok, paket, dan dukungan program MLM.'}
                        </p>
                    </div>
                    <div className="product-heading-actions">
                        <ImportDialog />
                        <a
                            className="product-secondary"
                            href={exportMethod.url({ query: { ...filters } })}
                        >
                            <Download />
                            Export
                        </a>
                        <ProductDialog
                            label="Tambah Produk"
                            icon={<Plus />}
                            primary
                        >
                            <ProductEditor categories={categories} />
                        </ProductDialog>
                    </div>
                </header>
                <section className="product-kpis" aria-label="Ringkasan produk">
                    {tiles.map((tile) => (
                        <article
                            className={`product-kpi ${tile.tone}`}
                            key={tile.title}
                        >
                            <span className="product-kpi-icon">
                                {tile.icon}
                            </span>
                            <div>
                                <h2>{tile.title}</h2>
                                <strong
                                    className={
                                        tile.tone === 'pink'
                                            ? 'product-kpi-name'
                                            : ''
                                    }
                                >
                                    {tile.value}
                                </strong>
                                <p>{tile.note}</p>
                            </div>
                        </article>
                    ))}
                </section>
                <div className="product-body">
                    {view === 'grid' && (
                        <aside className="product-sidebar">
                            <section>
                                <h2>
                                    <Boxes />
                                    Kategori Produk
                                </h2>
                                <button
                                    className={
                                        !filters.category ? 'selected' : ''
                                    }
                                    onClick={() => apply({ category: '' })}
                                >
                                    <LayoutGrid />
                                    Semua Produk <b>{stats.total_products}</b>
                                </button>
                                {categories.map((category, position) => {
                                    const Icon = [Droplet, Leaf, Layers3, Tag][
                                        position % 4
                                    ];
                                    return (
                                        <button
                                            key={category.id}
                                            className={
                                                Number(filters.category) ===
                                                category.id
                                                    ? 'selected'
                                                    : ''
                                            }
                                            onClick={() =>
                                                apply({ category: category.id })
                                            }
                                        >
                                            <Icon />
                                            {category.name}
                                            <b>{category.product_count}</b>
                                        </button>
                                    );
                                })}
                                <RecordDialog
                                    kind="add"
                                    label="Kelola kategori"
                                    title="Kategori produk"
                                >
                                    <CategoryEditor />
                                    <DataTable
                                        label="Kategori produk"
                                        rows={categories}
                                        rowKey={(category) => category.id}
                                        columns={[
                                            {
                                                header: 'Nama',
                                                render: (category) =>
                                                    category.name,
                                            },
                                            {
                                                header: 'Slug',
                                                render: (category) =>
                                                    category.slug,
                                            },
                                            {
                                                header: 'Aksi',
                                                render: (category) => (
                                                    <RecordDialog
                                                        kind="edit"
                                                        title={`Edit ${category.name}`}
                                                    >
                                                        <CategoryEditor
                                                            category={category}
                                                        />
                                                    </RecordDialog>
                                                ),
                                            },
                                        ]}
                                    />
                                </RecordDialog>
                            </section>
                            <section>
                                <h2>
                                    <Filter />
                                    Filter Cepat
                                </h2>
                                <h3>Status</h3>
                                {[
                                    ['active', 'Aktif', stats.active],
                                    ['inactive', 'Nonaktif', stats.inactive],
                                    ['draft', 'Draf', stats.draft],
                                ].map(([value, label, count]) => (
                                    <label key={value}>
                                        <input
                                            type="checkbox"
                                            checked={filters.status === value}
                                            onChange={() =>
                                                apply({
                                                    status:
                                                        filters.status === value
                                                            ? ''
                                                            : String(value),
                                                })
                                            }
                                        />
                                        {label}
                                        <span>({count})</span>
                                    </label>
                                ))}
                                <h3>Label</h3>
                                {[
                                    ['bestseller', 'Bestseller'],
                                    ['promo', 'Promo'],
                                    ['bundle', 'Paket'],
                                    ['mlm', 'MLM'],
                                ].map(([value, label]) => (
                                    <label key={value}>
                                        <input
                                            type="checkbox"
                                            checked={filters.label === value}
                                            onChange={() =>
                                                apply({
                                                    label:
                                                        filters.label === value
                                                            ? ''
                                                            : value,
                                                })
                                            }
                                        />
                                        {label}
                                    </label>
                                ))}
                            </section>
                        </aside>
                    )}
                    <section className="product-content">
                        <div className="product-toolbar">
                            {view === 'list' && (
                                <div className="product-list-tabs">
                                    {[
                                        [
                                            '',
                                            '',
                                            `Semua Produk (${stats.total_products})`,
                                        ],
                                        [
                                            'active',
                                            '',
                                            `Produk Aktif (${stats.active})`,
                                        ],
                                        [
                                            'inactive',
                                            '',
                                            `Nonaktif (${stats.inactive})`,
                                        ],
                                        ['', 'bundle', 'Paket'],
                                        ['', 'bestseller', 'Bestseller'],
                                    ].map(([status, label, text]) => (
                                        <button
                                            key={text}
                                            className={
                                                filters.status === status &&
                                                filters.label === label
                                                    ? 'selected'
                                                    : ''
                                            }
                                            onClick={() =>
                                                apply({ status, label })
                                            }
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                            )}
                            <div className="product-toolbar-row">
                                <div className="product-layout-toggle">
                                    <button
                                        className={
                                            view === 'grid' ? 'selected' : ''
                                        }
                                        onClick={() => setLayout('grid')}
                                    >
                                        <LayoutGrid />
                                        Grid
                                    </button>
                                    <button
                                        className={
                                            view === 'list' ? 'selected' : ''
                                        }
                                        onClick={() => setLayout('list')}
                                    >
                                        <List />
                                        List
                                    </button>
                                </div>
                                <form
                                    className="product-search"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        apply({ search });
                                    }}
                                >
                                    <Search />
                                    <input
                                        aria-label="Cari produk atau SKU"
                                        placeholder="Cari nama produk atau SKU..."
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                    />
                                    <button className="sr-only" type="submit">
                                        Cari
                                    </button>
                                </form>
                                {view === 'list' && (
                                    <label className="product-select-label">
                                        Kategori
                                        <select
                                            value={filters.category}
                                            onChange={(event) =>
                                                apply({
                                                    category:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Semua Kategori
                                            </option>
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                )}
                                <label className="product-select-label">
                                    <span
                                        className={
                                            view === 'grid' ? 'sr-only' : ''
                                        }
                                    >
                                        Urutkan
                                    </span>
                                    <select
                                        aria-label="Urutkan produk"
                                        value={filters.sort}
                                        onChange={(event) =>
                                            apply({ sort: event.target.value })
                                        }
                                    >
                                        <option value="newest">Terbaru</option>
                                        <option value="oldest">Terlama</option>
                                        <option value="name">Nama A–Z</option>
                                        <option value="price_low">
                                            Harga Terendah
                                        </option>
                                        <option value="price_high">
                                            Harga Tertinggi
                                        </option>
                                        <option value="stock">
                                            Stok Terbanyak
                                        </option>
                                        <option value="sold">Terlaris</option>
                                    </select>
                                </label>
                                <button
                                    className="product-secondary"
                                    aria-expanded={showFilters}
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter />
                                    Filter
                                </button>
                            </div>
                            {showFilters && (
                                <div className="product-filter-extra">
                                    <label>
                                        Status
                                        <select
                                            value={filters.status}
                                            onChange={(event) =>
                                                apply({
                                                    status: event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Semua Status
                                            </option>
                                            <option value="active">
                                                Aktif
                                            </option>
                                            <option value="inactive">
                                                Nonaktif
                                            </option>
                                            <option value="draft">Draf</option>
                                        </select>
                                    </label>
                                    <label>
                                        Label
                                        <select
                                            value={filters.label}
                                            onChange={(event) =>
                                                apply({
                                                    label: event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Semua Label
                                            </option>
                                            <option value="mlm">MLM</option>
                                            <option value="promo">Promo</option>
                                            <option value="bestseller">
                                                Bestseller
                                            </option>
                                            <option value="bundle">
                                                Paket
                                            </option>
                                        </select>
                                    </label>
                                    <button
                                        className="product-secondary"
                                        onClick={() => {
                                            setSearch('');
                                            apply({
                                                search: '',
                                                status: '',
                                                label: '',
                                                category: '',
                                                sort: 'newest',
                                            });
                                        }}
                                    >
                                        Reset filter
                                    </button>
                                </div>
                            )}
                        </div>
                        {products.data.length === 0 ? (
                            <div className="product-empty">
                                <MenuIcon />
                                <h2>Produk tidak ditemukan</h2>
                                <p>
                                    Sesuaikan pencarian atau tambahkan produk
                                    baru.
                                </p>
                            </div>
                        ) : view === 'grid' ? (
                            <div className="product-grid">
                                {products.data.map((product) => {
                                    const variant = [...product.variants].sort(
                                        (a, b) =>
                                            a.price -
                                            a.discount_amount -
                                            (b.price - b.discount_amount),
                                    )[0];
                                    return (
                                        <article
                                            className="product-card"
                                            key={product.id}
                                        >
                                            <div className="product-image">
                                                <ProductPhotoPreview
                                                    src={
                                                        product.image_url ??
                                                        '/honey-product.jpg'
                                                    }
                                                    name={product.name}
                                                />
                                                <ProductBadges
                                                    product={product}
                                                />
                                                <button
                                                    className={`product-favorite ${favorites.includes(product.id) ? 'selected' : ''}`}
                                                    aria-label={`Favorit ${product.name}`}
                                                    aria-pressed={favorites.includes(
                                                        product.id,
                                                    )}
                                                    title="Favorit tersimpan di browser ini"
                                                    onClick={() =>
                                                        favorite(product.id)
                                                    }
                                                >
                                                    <Heart />
                                                </button>
                                            </div>
                                            <div className="product-card-info">
                                                <h2>{product.name}</h2>
                                                <p className="product-sku">
                                                    SKU: {product.sku}
                                                </p>
                                                <strong className="product-price">
                                                    {variant
                                                        ? money(
                                                              variant.price -
                                                                  variant.discount_amount,
                                                          )
                                                        : 'Belum ada varian'}
                                                </strong>
                                                {variant &&
                                                    variant.discount_amount >
                                                        0 && (
                                                        <div className="product-discount">
                                                            <del>
                                                                {money(
                                                                    variant.price,
                                                                )}
                                                            </del>
                                                            <span>
                                                                -
                                                                {Math.round(
                                                                    (variant.discount_amount *
                                                                        100) /
                                                                        variant.price,
                                                                )}
                                                                %
                                                            </span>
                                                        </div>
                                                    )}
                                                <p className="product-stock">
                                                    Stok:{' '}
                                                    {number(
                                                        Number(product.stock),
                                                    )}
                                                </p>
                                                <ProductActions
                                                    product={product}
                                                    categories={categories}
                                                />
                                            </div>
                                        </article>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="product-table-scroll">
                                <table className="product-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input
                                                    type="checkbox"
                                                    aria-label="Pilih semua produk pada halaman ini"
                                                    checked={
                                                        products.data.length >
                                                            0 &&
                                                        products.data.every(
                                                            (product) =>
                                                                selected.includes(
                                                                    product.id,
                                                                ),
                                                        )
                                                    }
                                                    onChange={(event) =>
                                                        setSelected(
                                                            event.target.checked
                                                                ? products.data.map(
                                                                      (
                                                                          product,
                                                                      ) =>
                                                                          product.id,
                                                                  )
                                                                : [],
                                                        )
                                                    }
                                                />
                                            </th>
                                            {[
                                                'Produk',
                                                'SKU',
                                                'Kategori',
                                                'Harga Member',
                                                'Harga Retail',
                                                'Stok',
                                                'Status',
                                                'Aksi',
                                            ].map((title) => (
                                                <th key={title}>{title}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {products.data.map((product) => {
                                            const variant = product.variants[0];
                                            return (
                                                <tr key={product.id}>
                                                    <td>
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`Pilih ${product.name}`}
                                                            checked={selected.includes(
                                                                product.id,
                                                            )}
                                                            onChange={(event) =>
                                                                setSelected(
                                                                    event.target
                                                                        .checked
                                                                        ? [
                                                                              ...selected,
                                                                              product.id,
                                                                          ]
                                                                        : selected.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  product.id,
                                                                          ),
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td>
                                                        <div className="product-table-name">
                                                            <ProductPhotoPreview
                                                                src={
                                                                    product.image_url ??
                                                                    '/honey-product.jpg'
                                                                }
                                                                name={
                                                                    product.name
                                                                }
                                                            />
                                                            <div>
                                                                <strong>
                                                                    {
                                                                        product.name
                                                                    }
                                                                </strong>
                                                                <ProductBadges
                                                                    product={
                                                                        product
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{product.sku}</td>
                                                    <td>
                                                        {categories.find(
                                                            (category) =>
                                                                category.id ===
                                                                product.category_id,
                                                        )?.name ?? '—'}
                                                    </td>
                                                    <td>
                                                        {variant?.member_price
                                                            ? money(
                                                                  variant.member_price,
                                                              )
                                                            : 'Belum diatur'}
                                                    </td>
                                                    <td>
                                                        {variant
                                                            ? money(
                                                                  variant.price,
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td>
                                                        <span className="product-stock-pill">
                                                            {number(
                                                                Number(
                                                                    product.stock,
                                                                ),
                                                            )}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <StatusBadge
                                                            status={
                                                                product.status
                                                            }
                                                        />
                                                    </td>
                                                    <td>
                                                        <ProductActions
                                                            product={product}
                                                            categories={
                                                                categories
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                                {selected.length > 0 && (
                                    <div className="product-selection">
                                        {selected.length} produk dipilih{' '}
                                        <button
                                            className="product-secondary"
                                            onClick={() => {
                                                const rows =
                                                    products.data.filter(
                                                        (product) =>
                                                            selected.includes(
                                                                product.id,
                                                            ),
                                                    );
                                                const file = new Blob(
                                                    [
                                                        '\uFEFF' +
                                                            [
                                                                'SKU,Nama',
                                                                ...rows.map(
                                                                    (product) =>
                                                                        [
                                                                            product.sku,
                                                                            product.name,
                                                                        ]
                                                                            .map(
                                                                                (
                                                                                    value,
                                                                                ) =>
                                                                                    '"' +
                                                                                    (/^[\s]*[=+@-]/.test(
                                                                                        value,
                                                                                    )
                                                                                        ? "'"
                                                                                        : '') +
                                                                                    value.replaceAll(
                                                                                        '"',
                                                                                        '""',
                                                                                    ) +
                                                                                    '"',
                                                                            )
                                                                            .join(
                                                                                ',',
                                                                            ),
                                                                ),
                                                            ].join('\r\n'),
                                                    ],
                                                    {
                                                        type: 'text/csv;charset=utf-8',
                                                    },
                                                );
                                                const url =
                                                    URL.createObjectURL(file);
                                                const anchor =
                                                    document.createElement('a');
                                                anchor.href = url;
                                                anchor.download =
                                                    'produk-terpilih.csv';
                                                anchor.click();
                                                URL.revokeObjectURL(url);
                                            }}
                                        >
                                            <Download />
                                            Export pilihan
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}
                        <footer className="product-pagination">
                            <p>
                                Menampilkan {products.from ?? 0} –{' '}
                                {products.to ?? 0} dari {number(products.total)}{' '}
                                produk
                            </p>
                            <label>
                                Tampilkan
                                <select
                                    aria-label="Jumlah produk per halaman"
                                    value={filters.per_page}
                                    onChange={(event) =>
                                        apply({
                                            per_page: Number(
                                                event.target.value,
                                            ),
                                        })
                                    }
                                >
                                    {[8, 10, 16, 24, 48].map((size) => (
                                        <option key={size}>{size}</option>
                                    ))}
                                </select>
                                per halaman
                            </label>
                            <nav aria-label="Halaman produk">
                                <button
                                    disabled={products.current_page === 1}
                                    onClick={() =>
                                        router.get(
                                            index.url(),
                                            {
                                                ...filters,
                                                page: products.current_page - 1,
                                            },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                    aria-label="Halaman sebelumnya"
                                >
                                    <ChevronLeft />
                                </button>
                                {Array.from(
                                    { length: Math.min(5, products.last_page) },
                                    (_, offset) =>
                                        Math.max(
                                            1,
                                            Math.min(
                                                products.current_page - 2,
                                                products.last_page - 4,
                                            ),
                                        ) + offset,
                                ).map((page) => (
                                    <Link
                                        key={page}
                                        className={
                                            page === products.current_page
                                                ? 'selected'
                                                : ''
                                        }
                                        aria-current={
                                            page === products.current_page
                                                ? 'page'
                                                : undefined
                                        }
                                        href={index.url({
                                            query: { ...filters, page },
                                        })}
                                        preserveState
                                        preserveScroll
                                    >
                                        {page}
                                    </Link>
                                ))}
                                <button
                                    disabled={
                                        products.current_page ===
                                        products.last_page
                                    }
                                    onClick={() =>
                                        router.get(
                                            index.url(),
                                            {
                                                ...filters,
                                                page: products.current_page + 1,
                                            },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                    aria-label="Halaman berikutnya"
                                >
                                    <ChevronRight />
                                </button>
                            </nav>
                        </footer>
                    </section>
                </div>
                {view === 'list' && (
                    <details className="product-category-manager">
                        <summary>Kelola kategori produk</summary>
                        <CategoryEditor />
                        {categories.map((category) => (
                            <CategoryEditor
                                key={category.id}
                                category={category}
                            />
                        ))}
                    </details>
                )}
            </main>
        </>
    );
}
