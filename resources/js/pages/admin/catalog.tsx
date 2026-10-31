import { ListFilters } from '@/components/list-filters';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    RowActions,
    StatusBadge,
} from '@/components/data-list';
import { Head, useForm } from '@inertiajs/react';
import {
    Errors,
    Field,
    Pager,
    inputClass,
    money,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { store, update, image as uploadImage } from '@/routes/admin/catalog';
import {
    store as createCategory,
    update as updateCategory,
} from '@/routes/admin/categories';
import type { Paginated } from '@/types/commerce';
type Category = { id: number; name: string; slug: string; status: string };
type Variant = {
    id: number | null;
    sku: string;
    attributes?: { label?: string };
    label: string;
    price: number;
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
                        <option key={status}>{status}</option>
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
}: {
    product?: Product;
    categories: Category[];
}) {
    const form = useForm({
        category_id: product?.category_id ?? categories[0]?.id ?? 0,
        sku: product?.sku ?? '',
        name: product?.name ?? '',
        slug: product?.slug ?? '',
        description: product?.description ?? '',
        status: product?.status ?? 'draft',
        weight_g: product?.weight_g ?? 500,
        commission_eligible: product?.commission_eligible ?? false,
        variants: product?.variants.map((variant) => ({
            ...variant,
            label: variant.attributes?.label ?? variant.sku,
        })) ?? [blankVariant()],
    });
    const image = useForm<{ image: File | null }>({ image: null });
    return (
        <section className="flex flex-col gap-5 rounded-xl border p-5">
            <form
                className="flex flex-col gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (product) form.put(update.url(product.id));
                    else
                        form.post(store.url(), {
                            onSuccess: () => form.reset(),
                        });
                }}
            >
                <h3 className="text-lg font-semibold">
                    {product ? product.name : 'Produk baru'}
                </h3>
                <div className="grid gap-4 md:grid-cols-2">
                    {(['name', 'sku', 'slug'] as const).map((field) => (
                        <Field key={field} label={field}>
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
                                <option key={status}>{status}</option>
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
                            <Field key={field} label={field}>
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
                                        <option key={status}>{status}</option>
                                    ),
                                )}
                            </select>
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
                    Produk eligible untuk komisi penjualan direct
                </label>
                <Errors errors={form.errors} />
                <Button
                    className="self-start"
                    disabled={form.processing || categories.length === 0}
                >
                    Simpan produk
                </Button>
            </form>
            {product && (
                <form
                    className="flex flex-col gap-3 border-t pt-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        image.post(uploadImage.url(product.id));
                    }}
                >
                    <Field label="Foto produk (JPG/PNG/WebP, maks. 5 MB)">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            required
                            onChange={(e) =>
                                image.setData(
                                    'image',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </Field>
                    <Errors errors={image.errors} />
                    <Button
                        disabled={image.processing}
                        variant="outline"
                        className="self-start"
                    >
                        Unggah foto
                    </Button>
                </form>
            )}
        </section>
    );
}
export default function CatalogManagement({
    products,
    categories,
}: {
    products: Paginated<Product>;
    categories: Category[];
}) {
    return (
        <>
            <Head title="Kelola katalog" />
            <main className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Katalog produk
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Daftar produk, varian, kategori, dan status
                            penjualan.
                        </p>
                    </div>
                    <RecordDialog
                        title="Tambah produk"
                        label="Tambah produk"
                        kind="add"
                        disabled={!categories.length}
                    >
                        <ProductEditor categories={categories} />
                    </RecordDialog>
                </header>
                <ListFilters
                    placeholder="Nama produk atau SKU"
                    statuses={{ active: 'Aktif', inactive: 'Nonaktif' }}
                />
                <DataTable
                    label="Daftar produk"
                    rows={products.data}
                    rowKey={(p) => p.id}
                    columns={[
                        {
                            header: 'SKU',
                            render: (p) => (
                                <span className="font-mono text-xs">
                                    {p.sku}
                                </span>
                            ),
                        },
                        {
                            header: 'Produk',
                            render: (p) => (
                                <div>
                                    <p className="font-medium">{p.name}</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {categories.find(
                                            (c) => c.id === p.category_id,
                                        )?.name ?? '-'}
                                    </p>
                                </div>
                            ),
                        },
                        { header: 'Varian', render: (p) => p.variants.length },
                        {
                            header: 'Status',
                            render: (p) => <StatusBadge status={p.status} />,
                        },
                        {
                            header: 'Komisi',
                            render: (p) =>
                                p.commission_eligible
                                    ? 'Eligible'
                                    : 'Tidak eligible',
                        },
                        {
                            header: 'Aksi',
                            render: (p) => (
                                <RowActions>
                                    <RecordDialog title={p.name}>
                                        <DetailFields
                                            rows={[
                                                ['SKU', p.sku],
                                                ['Nama', p.name],
                                                [
                                                    'Kategori',
                                                    categories.find(
                                                        (c) =>
                                                            c.id ===
                                                            p.category_id,
                                                    )?.name,
                                                ],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={p.status}
                                                    />,
                                                ],
                                                ['Berat', `${p.weight_g} gram`],
                                                [
                                                    'Komisi',
                                                    p.commission_eligible
                                                        ? 'Eligible'
                                                        : 'Tidak eligible',
                                                ],
                                                ['Deskripsi', p.description],
                                            ]}
                                        />
                                        <DataTable
                                            label="Varian produk"
                                            rows={p.variants}
                                            rowKey={(v) => v.sku}
                                            columns={[
                                                {
                                                    header: 'SKU',
                                                    render: (v) => v.sku,
                                                },
                                                {
                                                    header: 'Ukuran',
                                                    render: (v) =>
                                                        v.attributes?.label ??
                                                        v.label,
                                                },
                                                {
                                                    header: 'Harga',
                                                    render: (v) =>
                                                        money(v.price),
                                                },
                                                {
                                                    header: 'Diskon',
                                                    render: (v) =>
                                                        money(
                                                            v.discount_amount,
                                                        ),
                                                },
                                                {
                                                    header: 'Status',
                                                    render: (v) => (
                                                        <StatusBadge
                                                            status={v.status}
                                                        />
                                                    ),
                                                },
                                            ]}
                                        />
                                    </RecordDialog>
                                    <RecordDialog
                                        kind="edit"
                                        title={`Edit ${p.name}`}
                                    >
                                        <ProductEditor
                                            product={p}
                                            categories={categories}
                                        />
                                    </RecordDialog>
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={products} />
                <p className="text-xs text-muted-foreground">
                    Produk baru dimulai dengan stok nol. Tambahkan stok pada
                    menu Persediaan.
                </p>
                <section className="grid gap-4">
                    <header className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold">
                            Kategori produk
                        </h2>
                        <RecordDialog
                            kind="add"
                            title="Tambah kategori"
                            label="Tambah kategori"
                        >
                            <CategoryEditor />
                        </RecordDialog>
                    </header>
                    <DataTable
                        label="Daftar kategori"
                        searchable
                        localFilter={{
                            label: 'Status kategori',
                            options: { active: 'Aktif', inactive: 'Nonaktif' },
                            value: (row) => row.status,
                        }}
                        rows={categories}
                        rowKey={(c) => c.id}
                        columns={[
                            { header: 'Nama kategori', render: (c) => c.name },
                            { header: 'Slug', render: (c) => c.slug },
                            {
                                header: 'Status',
                                render: (c) => (
                                    <StatusBadge status={c.status} />
                                ),
                            },
                            {
                                header: 'Aksi',
                                render: (c) => (
                                    <RowActions>
                                        <RecordDialog
                                            title={`Detail ${c.name}`}
                                        >
                                            <DetailFields
                                                rows={[
                                                    ['Nama', c.name],
                                                    ['Slug', c.slug],
                                                    [
                                                        'Status',
                                                        <StatusBadge
                                                            status={c.status}
                                                        />,
                                                    ],
                                                ]}
                                            />
                                        </RecordDialog>
                                        <RecordDialog
                                            kind="edit"
                                            title={`Edit ${c.name}`}
                                        >
                                            <CategoryEditor category={c} />
                                        </RecordDialog>
                                    </RowActions>
                                ),
                            },
                        ]}
                    />
                </section>
            </main>
        </>
    );
}
