import { MenuTitle } from '@/components/menu-title';
import { RotateCcw as MenuIcon } from 'lucide-react';
import { ListFilters } from '@/components/list-filters';
import { confirmAction } from '@/lib/sweet-alert';
import {
    DataTable,
    DetailFields,
    DetailLink,
    RecordDialog,
    RowActions,
    StatusBadge,
} from '@/components/data-list';
import { Head, Link, useForm } from '@inertiajs/react';
import { Errors, money, Pager } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { decide } from '@/routes/admin/refunds';
import { show } from '@/routes/orders';
import type { Paginated } from '@/types/commerce';
type Refund = {
    id: number;
    order_id: number;
    order_item_id: number | null;
    amount: number;
    reason: string;
    status: string;
};
function Review({ refund: r }: { refund: Refund }) {
    const f = useForm({ operation: '', confirmed_refund: false });
    return (
        <article className="grid gap-3 rounded-xl border p-5">
            <h2 className="font-semibold">
                Refund #{r.id} &middot; {money(r.amount)} &middot; {r.status}
            </h2>
            <Link className="underline" href={show(r.order_id)}>
                Lihat order #{r.order_id}
            </Link>
            <p>
                {r.order_item_id === null
                    ? 'Ongkir/pajak'
                    : `Item #${r.order_item_id}`}{' '}
                &middot; {r.reason}
            </p>
            {r.status === 'requested' && (
                <>
                    <label className="flex gap-3">
                        <input
                            type="checkbox"
                            checked={f.data.confirmed_refund}
                            onChange={(e) =>
                                f.setData('confirmed_refund', e.target.checked)
                            }
                        />
                        Dana sudah dikembalikan manual dan bukti diperiksa.
                    </label>
                    <div className="flex gap-3">
                        {['process', 'reject'].map((op) => (
                            <Button
                                key={op}
                                disabled={
                                    f.processing ||
                                    (op === 'process' &&
                                        !f.data.confirmed_refund)
                                }
                                onClick={async () => {
                                    if (
                                        !(await confirmAction(
                                            op === 'process'
                                                ? 'Catat refund dan reversal?'
                                                : 'Tolak permintaan refund?',
                                            `Refund #${r.id} sebesar ${money(r.amount)}.`,
                                            true,
                                        ))
                                    )
                                        return;
                                    f.transform((d) => ({
                                        ...d,
                                        operation: op,
                                    }));
                                    f.post(decide.url(r.id));
                                }}
                            >
                                {op === 'process'
                                    ? 'Catat refund & reversal'
                                    : 'Tolak permintaan'}
                            </Button>
                        ))}
                    </div>
                </>
            )}
            <Errors errors={f.errors} />
        </article>
    );
}
export default function Refunds({ refunds }: { refunds: Paginated<Refund> }) {
    return (
        <>
            <Head title="Permintaan refund" />
            <main className="grid gap-5 p-4 sm:p-6">
                <header>
                    <MenuTitle icon={MenuIcon} tone="coral">
                        Permintaan refund
                    </MenuTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Permintaan diajukan pada detail pesanan, kemudian
                        ditinjau Finance.
                    </p>
                </header>
                <ListFilters
                    placeholder="Nomor refund, pesanan, atau alasan"
                    statuses={{
                        requested: 'Diajukan',
                        processed: 'Diproses',
                        rejected: 'Ditolak',
                    }}
                />
                <DataTable
                    label="Daftar refund"
                    rows={refunds.data}
                    rowKey={(r) => r.id}
                    columns={[
                        { header: 'ID', render: (r) => `#${r.id}` },
                        { header: 'Pesanan', render: (r) => `#${r.order_id}` },
                        {
                            header: 'Komponen',
                            render: (r) =>
                                r.order_item_id === null
                                    ? 'Ongkir / pajak'
                                    : `Item #${r.order_item_id}`,
                        },
                        { header: 'Nominal', render: (r) => money(r.amount) },
                        {
                            header: 'Status',
                            render: (r) => <StatusBadge status={r.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (r) => (
                                <RowActions>
                                    <RecordDialog
                                        title={`Detail refund #${r.id}`}
                                    >
                                        <DetailFields
                                            rows={[
                                                ['ID', r.id],
                                                ['Pesanan', r.order_id],
                                                ['Nominal', money(r.amount)],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={r.status}
                                                    />,
                                                ],
                                                ['Alasan', r.reason],
                                            ]}
                                        />
                                        <DetailLink
                                            href={show.url(r.order_id)}
                                            label="Detail pesanan"
                                        />
                                    </RecordDialog>
                                    {r.status === 'requested' && (
                                        <RecordDialog
                                            kind="edit"
                                            label="Proses"
                                            title={`Proses refund #${r.id}`}
                                        >
                                            <Review refund={r} />
                                        </RecordDialog>
                                    )}
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={refunds} />
            </main>
        </>
    );
}
