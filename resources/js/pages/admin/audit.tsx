import { MenuTitle } from '@/components/menu-title';
import { ScrollText as MenuIcon } from 'lucide-react';
import { ListFilters } from '@/components/list-filters';
import { DataTable, DetailFields, RecordDialog } from '@/components/data-list';
import { Head } from '@inertiajs/react';
import { Pager } from '@/components/commerce-ui';
import type { Paginated } from '@/types/commerce';
export default function Audit({
    logs,
    action,
}: {
    logs: Paginated<{
        id: number;
        actor_id: number | null;
        action: string;
        subject_type: string;
        subject_id: number;
        created_at: string;
        after_json: string | null;
    }>;
    action: string;
}) {
    return (
        <>
            <Head title="Audit log" />
            <main className="grid gap-5 p-6">
                <MenuTitle icon={MenuIcon} tone="violet">
                    Audit log
                </MenuTitle>
                <ListFilters
                    placeholder="Aksi, ID objek, atau ID aktor"
                    filterLabel="Objek"
                    statuses={{
                        orders: 'Pesanan',
                        payout_requests: 'Payout',
                        partners: 'Mitra',
                        users: 'Pengguna',
                        commission_entries: 'Komisi',
                        commission_rules: 'Aturan komisi',
                        products: 'Produk',
                        categories: 'Kategori',
                        product_variants: 'Stok varian',
                        refunds: 'Refund',
                        customers: 'Pelanggan',
                    }}
                    extraField={{
                        name: 'action',
                        label: 'Nama aksi persis',
                        value: action,
                    }}
                />
                <DataTable
                    label="Audit log"
                    rows={logs.data}
                    rowKey={(l) => l.id}
                    columns={[
                        { header: 'Waktu', render: (l) => l.created_at },
                        { header: 'Aksi', render: (l) => l.action },
                        {
                            header: 'Aktor',
                            render: (l) =>
                                l.actor_id === null
                                    ? 'Sistem'
                                    : `#${l.actor_id}`,
                        },
                        {
                            header: 'Objek',
                            render: (l) => `${l.subject_type} #${l.subject_id}`,
                        },
                        {
                            header: 'Detail',
                            render: (l) => (
                                <RecordDialog title={`Audit #${l.id}`}>
                                    <DetailFields
                                        rows={[
                                            ['Waktu', l.created_at],
                                            ['Aksi', l.action],
                                            ['Aktor', l.actor_id ?? 'Sistem'],
                                            [
                                                'Objek',
                                                `${l.subject_type} #${l.subject_id}`,
                                            ],
                                        ]}
                                    />
                                    <h3 className="text-sm font-semibold">
                                        Data perubahan
                                    </h3>
                                    <pre className="max-h-80 overflow-auto rounded-lg bg-muted p-4 text-xs">
                                        {l.after_json
                                            ? JSON.stringify(
                                                  JSON.parse(l.after_json),
                                                  null,
                                                  2,
                                              )
                                            : 'Tidak ada data tambahan.'}
                                    </pre>
                                </RecordDialog>
                            ),
                        },
                    ]}
                />
                <Pager page={logs} />
            </main>
        </>
    );
}
