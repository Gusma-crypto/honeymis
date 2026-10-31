import { MenuTitle } from '@/components/menu-title';
import { Wallet as MenuIcon } from 'lucide-react';
import { ListFilters } from '@/components/list-filters';
import { DataTable, DetailFields, RecordDialog } from '@/components/data-list';
import { Head, Link } from '@inertiajs/react';
import { money, Pager } from '@/components/commerce-ui';
import { index as payouts } from '@/routes/payouts';
import type { Paginated } from '@/types/commerce';
export default function Wallet({
    balances,
    withdrawable,
    entries,
}: {
    balances: Record<string, number>;
    withdrawable: number;
    entries: Paginated<{
        id: number;
        bucket: string;
        entry_type: string;
        amount_signed: number;
        created_at: string;
    }>;
}) {
    return (
        <>
            <Head title="Dompet komisi" />
            <main className="grid gap-6 p-6">
                <MenuTitle icon={MenuIcon} tone="green">
                    Dompet komisi
                </MenuTitle>
                <div className="grid gap-4 sm:grid-cols-3">
                    {Object.entries(balances).map(([k, v]) => (
                        <article key={k} className="rounded-xl border p-5">
                            <p>{k}</p>
                            <strong className="text-2xl">{money(v)}</strong>
                        </article>
                    ))}
                </div>
                <p>
                    Dapat ditarik: <strong>{money(withdrawable)}</strong>
                </p>
                <p>
                    Receivable adalah piutang akibat refund setelah dana
                    diselesaikan. Penghasilan berikutnya dipakai untuk pelunasan
                    sebelum dapat ditarik.
                </p>
                <Link className="underline" href={payouts()}>
                    Ajukan payout
                </Link>
                <ListFilters
                    placeholder="Nomor mutasi atau jenis transaksi"
                    statuses={{
                        pending: 'Menunggu',
                        available: 'Tersedia',
                        reserved: 'Direservasi',
                        paid: 'Dicairkan',
                        receivable: 'Piutang',
                    }}
                />
                <DataTable
                    label="Mutasi dompet"
                    rows={entries.data}
                    rowKey={(e) => e.id}
                    columns={[
                        {
                            header: 'Waktu',
                            render: (e) =>
                                new Date(e.created_at).toLocaleString('id-ID'),
                        },
                        { header: 'Jenis', render: (e) => e.entry_type },
                        { header: 'Bucket', render: (e) => e.bucket },
                        {
                            header: 'Nominal',
                            render: (e) => money(e.amount_signed),
                        },
                        {
                            header: 'Aksi',
                            render: (e) => (
                                <RecordDialog title={`Detail mutasi #${e.id}`}>
                                    <DetailFields
                                        rows={[
                                            ['ID', e.id],
                                            [
                                                'Waktu',
                                                new Date(
                                                    e.created_at,
                                                ).toLocaleString('id-ID'),
                                            ],
                                            ['Jenis', e.entry_type],
                                            ['Bucket', e.bucket],
                                            ['Nominal', money(e.amount_signed)],
                                        ]}
                                    />
                                </RecordDialog>
                            ),
                        },
                    ]}
                />
                <Pager page={entries} />
            </main>
        </>
    );
}
