import { MenuTitle } from '@/components/menu-title';
import { ContactRound as MenuIcon } from 'lucide-react';
import { CustomerEditor } from '@/components/customer-editor';
import {
    DataTable,
    DetailFields,
    DetailLink,
    StatusBadge,
    RecordDialog,
} from '@/components/data-list';
import { Head } from '@inertiajs/react';
import { money, Pager } from '@/components/commerce-ui';
import { show } from '@/routes/orders';
import type { Paginated } from '@/types/commerce';
export default function Customer({
    customer,
    orders,
}: {
    customer: { id: number; name: string; email: string; phone: string | null };
    orders: Paginated<{
        id: number;
        order_number: string;
        status: string;
        grand_total: number;
    }>;
}) {
    return (
        <>
            <Head title={customer.name} />
            <main className="grid gap-5 p-4 sm:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <MenuTitle icon={MenuIcon} tone="violet">
                        Detail pelanggan
                    </MenuTitle>
                    <RecordDialog kind="edit" title="Edit kontak pelanggan">
                        <CustomerEditor customer={customer} />
                    </RecordDialog>
                </header>
                <DetailFields
                    rows={[
                        ['Nama', customer.name],
                        ['Email', customer.email],
                        ['Telepon', customer.phone],
                    ]}
                />
                <h2 className="text-lg font-semibold">Riwayat pesanan</h2>
                <DataTable
                    label="Riwayat pesanan pelanggan"
                    searchable
                    localFilter={{
                        label: 'Status',
                        options: {
                            pending_payment: 'Menunggu bayar',
                            paid: 'Dibayar',
                            processing: 'Diproses',
                            shipped: 'Dikirim',
                            delivered: 'Diterima',
                            completed: 'Selesai',
                            cancelled: 'Dibatalkan',
                            refunded: 'Refund',
                        },
                        value: (row) => row.status,
                    }}
                    rows={orders.data}
                    rowKey={(o) => o.id}
                    columns={[
                        {
                            header: 'Nomor pesanan',
                            render: (o) => o.order_number,
                        },
                        {
                            header: 'Status',
                            render: (o) => <StatusBadge status={o.status} />,
                        },
                        {
                            header: 'Total',
                            render: (o) => money(o.grand_total),
                        },
                        {
                            header: 'Aksi',
                            render: (o) => <DetailLink href={show.url(o.id)} />,
                        },
                    ]}
                />
                <Pager page={orders} />
            </main>
        </>
    );
}
