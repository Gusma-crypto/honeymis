import { ListFilters } from '@/components/list-filters';
import { confirmAction } from '@/lib/sweet-alert';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    RowActions,
} from '@/components/data-list';
import { Head, useForm } from '@inertiajs/react';
import {
    Errors,
    Field,
    Pager,
    inputClass,
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/admin/inventory';
import type { Paginated } from '@/types/commerce';
type Variant = {
    id: number;
    sku: string;
    product: { name: string };
    inventory: { on_hand: number; reserved: number } | null;
};
function StockEditor({ variant }: { variant: Variant }) {
    const key = useRequestKey();
    const form = useForm({
        quantity_delta: 0,
        reason: '',
        idempotency_key: '',
    });
    return (
        <form
            className="flex flex-col gap-4 rounded-xl border p-5"
            onSubmit={async (e) => {
                e.preventDefault();
                if (
                    !(await confirmAction(
                        'Simpan penyesuaian stok?',
                        `${variant.sku}: perubahan ${form.data.quantity_delta > 0 ? '+' : ''}${form.data.quantity_delta} unit.`,
                        form.data.quantity_delta < 0,
                    ))
                )
                    return;
                form.transform((data) => ({
                    ...data,
                    idempotency_key: key.get(),
                }));
                form.post(update.url(variant.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        key.clear();
                        form.reset();
                    },
                });
            }}
        >
            <h2 className="font-semibold">
                {variant.product.name} / {variant.sku}
            </h2>
            <p>
                Stok fisik: {variant.inventory?.on_hand ?? 0} / Direservasi:{' '}
                {variant.inventory?.reserved ?? 0}
            </p>
            <div className="flex flex-wrap gap-4">
                <Field label="Perubahan stok (+ / -)">
                    <input
                        className={inputClass}
                        type="number"
                        required
                        value={form.data.quantity_delta}
                        onChange={(e) =>
                            form.setData(
                                'quantity_delta',
                                Number(e.target.value),
                            )
                        }
                    />
                </Field>
                <Field label="Alasan penyesuaian">
                    <input
                        className={inputClass}
                        required
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                    />
                </Field>
            </div>
            <Errors errors={form.errors} />
            <Button disabled={form.processing} className="self-start">
                Catat perubahan stok
            </Button>
        </form>
    );
}
export default function Inventory({
    variants,
}: {
    variants: Paginated<Variant>;
}) {
    return (
        <>
            <Head title="Persediaan" />
            <main className="grid gap-5 p-4 sm:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">Persediaan</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Periksa stok fisik, reservasi, dan ketersediaan per
                        varian.
                    </p>
                </header>
                <ListFilters
                    placeholder="SKU atau nama produk"
                    statuses={{ active: 'Aktif', inactive: 'Nonaktif' }}
                />
                <DataTable
                    label="Daftar persediaan"
                    rows={variants.data}
                    rowKey={(v) => v.id}
                    columns={[
                        { header: 'Produk', render: (v) => v.product.name },
                        { header: 'SKU', render: (v) => v.sku },
                        {
                            header: 'Stok fisik',
                            render: (v) => v.inventory?.on_hand ?? 0,
                        },
                        {
                            header: 'Reservasi',
                            render: (v) => v.inventory?.reserved ?? 0,
                        },
                        {
                            header: 'Tersedia',
                            render: (v) =>
                                (v.inventory?.on_hand ?? 0) -
                                (v.inventory?.reserved ?? 0),
                        },
                        {
                            header: 'Aksi',
                            render: (v) => (
                                <RowActions>
                                    <RecordDialog title="Detail persediaan">
                                        <DetailFields
                                            rows={[
                                                ['Produk', v.product.name],
                                                ['SKU', v.sku],
                                                [
                                                    'Stok fisik',
                                                    v.inventory?.on_hand ?? 0,
                                                ],
                                                [
                                                    'Direservasi',
                                                    v.inventory?.reserved ?? 0,
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                    <RecordDialog
                                        kind="edit"
                                        label="Ubah stok"
                                        title={`Penyesuaian stok ${v.sku}`}
                                    >
                                        <StockEditor variant={v} />
                                    </RecordDialog>
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={variants} />
            </main>
        </>
    );
}
