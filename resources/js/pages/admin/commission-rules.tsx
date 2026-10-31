import { MenuTitle } from '@/components/menu-title';
import { Coins as MenuIcon } from 'lucide-react';
import { ListFilters } from '@/components/list-filters';
import { confirmAction } from '@/lib/sweet-alert';
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
    inputClass,
    money,
    Pager,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { store, publish, retire } from '@/routes/admin/commission-rules';
import type { Paginated } from '@/types/commerce';
type Rule = {
    id: number;
    version: number;
    status: string;
    rate_bps: number;
    cap_amount: number;
    valid_from: string;
    preview: {
        checked: number;
        violations: number;
        examples: {
            sku: string;
            base: number;
            commission: number;
            network: number;
            margin: number;
        }[];
    } | null;
};
function RuleCard({ rule: r }: { rule: Rule }) {
    const f = useForm({ acknowledge_terms: false });
    return (
        <article className="grid gap-3 rounded-xl border p-5">
            <h2 className="text-xl font-semibold">
                Versi {r.version} &middot; {r.status}
            </h2>
            <p>
                {r.rate_bps / 100}% dari harga eligible &middot; Plafon{' '}
                {money(r.cap_amount)}
            </p>
            <p>Mulai {new Date(r.valid_from).toLocaleString('id-ID')}</p>
            {r.preview && (
                <>
                    <p>
                        Simulasi {r.preview.checked} varian &middot; Pelanggaran
                        budget: {r.preview.violations}
                    </p>
                    <DataTable
                        label="Simulasi komisi"
                        rows={r.preview.examples}
                        rowKey={(e) => e.sku}
                        columns={[
                            { header: 'SKU', render: (e) => e.sku },
                            {
                                header: 'Basis eligible',
                                render: (e) => money(e.base),
                            },
                            {
                                header: 'Komisi direct',
                                render: (e) => money(e.commission),
                            },
                            {
                                header: 'Komisi jaringan (maks.)',
                                render: (e) => money(e.network),
                            },
                            {
                                header: 'Margin produk',
                                render: (e) => money(e.margin),
                            },
                        ]}
                    />
                    <label className="flex gap-3">
                        <input
                            type="checkbox"
                            checked={f.data.acknowledge_terms}
                            onChange={(e) =>
                                f.setData('acknowledge_terms', e.target.checked)
                            }
                        />
                        Saya menyetujui tarif direct dan simulasi margin MLM.
                    </label>
                    <Button
                        disabled={
                            f.processing ||
                            !f.data.acknowledge_terms ||
                            r.preview.violations > 0
                        }
                        onClick={async () => {
                            if (
                                await confirmAction(
                                    'Publikasikan aturan komisi?',
                                    `Versi ${r.version} akan digunakan untuk transaksi eligible berikutnya. Komisi jaringan mengikuti konfigurasi Program MLM.`,
                                )
                            )
                                f.post(publish.url(r.id));
                        }}
                    >
                        Publish versi
                    </Button>
                </>
            )}
            {r.status === 'published' && (
                <Button
                    variant="outline"
                    disabled={f.processing}
                    onClick={async () => {
                        if (
                            await confirmAction(
                                'Hentikan aturan komisi?',
                                `Versi ${r.version} tidak lagi digunakan untuk pesanan baru.`,
                                true,
                            )
                        )
                            f.post(retire.url(r.id));
                    }}
                >
                    Hentikan untuk order baru
                </Button>
            )}
            <Errors errors={f.errors} />
        </article>
    );
}
export default function Rules({
    rules,
    nextVersion,
    products,
    categories,
}: {
    rules: Paginated<Rule>;
    nextVersion: number;
    products: { id: number; name: string }[];
    categories: { id: number; name: string }[];
}) {
    const form = useForm({
        version: nextVersion,
        product_id: '',
        category_id: '',
        rate_bps: 1500,
        fixed_amount: 0,
        cap_amount: 0,
        valid_from: '',
        valid_until: '',
    });
    return (
        <>
            <Head title="Aturan komisi" />
            <main className="grid gap-6 p-6">
                <MenuTitle icon={MenuIcon} tone="amber">
                    Aturan komisi penjualan direct
                </MenuTitle>
                <p>
                    Tarif direct harus 15–20%; total direct dan jaringan tetap
                    dibatasi margin produk. Tarif generasi dan target rank
                    diatur di Program MLM. Snapshot order lama tetap.
                </p>
                <RecordDialog
                    kind="add"
                    label="Tambah aturan"
                    title="Tambah versi aturan komisi"
                >
                    <form
                        className="grid gap-4 rounded-xl border p-5 sm:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(store.url(), {
                                onSuccess: () => {
                                    form.reset();
                                    form.setData('version', nextVersion + 1);
                                },
                            });
                        }}
                    >
                        {(['version', 'rate_bps', 'cap_amount'] as const).map(
                            (k, i) => (
                                <Field
                                    key={k}
                                    label={
                                        [
                                            'Versi',
                                            'Tarif direct basis point (1.500–2.000 = 15–20%)',
                                            'Plafon per item IDR',
                                        ][i]
                                    }
                                >
                                    <input
                                        type="number"
                                        max={
                                            k === 'rate_bps' ? 2000 : undefined
                                        }
                                        min={k === 'rate_bps' ? 1500 : 0}
                                        step="1"
                                        className={inputClass}
                                        required
                                        value={form.data[k]}
                                        onChange={(e) =>
                                            form.setData(
                                                k,
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </Field>
                            ),
                        )}
                        {(['product_id', 'category_id'] as const).map(
                            (k, i) => (
                                <Field
                                    key={k}
                                    label={
                                        i === 0
                                            ? 'Produk (opsional)'
                                            : 'Kategori (opsional; tanpa produk)'
                                    }
                                >
                                    <select
                                        className={inputClass}
                                        value={form.data[k]}
                                        onChange={(e) =>
                                            form.setData(k, e.target.value)
                                        }
                                    >
                                        <option value="">Semua</option>
                                        {(i === 0 ? products : categories).map(
                                            (p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </Field>
                            ),
                        )}
                        {(['valid_from', 'valid_until'] as const).map(
                            (k, i) => (
                                <Field
                                    key={k}
                                    label={
                                        i === 0
                                            ? 'Efektif mulai (waktu server UTC)'
                                            : 'Berakhir (opsional, UTC)'
                                    }
                                >
                                    <input
                                        type="datetime-local"
                                        className={inputClass}
                                        required={i === 0}
                                        value={form.data[k]}
                                        onChange={(e) =>
                                            form.setData(k, e.target.value)
                                        }
                                    />
                                </Field>
                            ),
                        )}
                        <Errors errors={form.errors} />
                        <Button disabled={form.processing}>
                            Simpan draft untuk simulasi
                        </Button>
                    </form>
                </RecordDialog>
                <ListFilters
                    placeholder="Nomor versi aturan"
                    statuses={{
                        draft: 'Draft',
                        published: 'Dipublikasikan',
                        retired: 'Dihentikan',
                    }}
                />
                <DataTable
                    label="Daftar aturan komisi"
                    rows={rules.data}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            header: 'Versi',
                            render: (r) => `Versi ${r.version}`,
                        },
                        {
                            header: 'Tarif',
                            render: (r) => `${r.rate_bps / 100}%`,
                        },
                        {
                            header: 'Plafon',
                            render: (r) => money(r.cap_amount),
                        },
                        {
                            header: 'Berlaku mulai',
                            render: (r) =>
                                new Date(r.valid_from).toLocaleString('id-ID'),
                        },
                        {
                            header: 'Status',
                            render: (r) => <StatusBadge status={r.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (r) => (
                                <RowActions>
                                    <RecordDialog
                                        title={`Detail aturan versi ${r.version}`}
                                    >
                                        <DetailFields
                                            rows={[
                                                ['Versi', r.version],
                                                [
                                                    'Tarif',
                                                    `${r.rate_bps / 100}%`,
                                                ],
                                                ['Plafon', money(r.cap_amount)],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={r.status}
                                                    />,
                                                ],
                                                [
                                                    'Berlaku mulai',
                                                    new Date(
                                                        r.valid_from,
                                                    ).toLocaleString('id-ID'),
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                    {['draft', 'published'].includes(
                                        r.status,
                                    ) && (
                                        <RecordDialog
                                            kind="edit"
                                            label={
                                                r.status === 'draft'
                                                    ? 'Simulasi / Publish'
                                                    : 'Kelola'
                                            }
                                            title={`Kelola versi ${r.version}`}
                                        >
                                            <RuleCard rule={r} />
                                        </RecordDialog>
                                    )}
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={rules} />
            </main>
        </>
    );
}
