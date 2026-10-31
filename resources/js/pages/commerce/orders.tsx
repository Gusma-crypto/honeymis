import { ListFilters } from '@/components/list-filters';
import {
    DataTable,
    DetailLink,
    RowActions,
    StatusBadge,
} from '@/components/data-list';
import { Head, Link, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index as adminOrders } from '@/routes/admin/orders';
import { Pager, money } from '@/components/commerce-ui';
import { show } from '@/routes/orders';
import type { Paginated } from '@/types/commerce';
type Order = {
    id: number;
    order_number: string;
    status: string;
    grand_total: number;
};
export default function Orders({ orders }: { orders: Paginated<Order> }) {
    const staffList = usePage().url.startsWith(adminOrders.url());
    return (
        <>
            <Head title="Pesanan" />
            <main className="grid gap-5 p-4 sm:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">Daftar pesanan</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Buka detail untuk pembayaran, pengiriman, dan perubahan
                        status.
                    </p>
                </header>
                <ListFilters
                    placeholder="Nomor pesanan"
                    statuses={{
                        pending_payment: 'Menunggu bayar',
                        paid: 'Dibayar',
                        processing: 'Diproses',
                        shipped: 'Dikirim',
                        delivered: 'Diterima',
                        completed: 'Selesai',
                        cancelled: 'Dibatalkan',
                        refunded: 'Refund',
                    }}
                />
                <DataTable
                    label="Daftar pesanan"
                    rows={orders.data}
                    rowKey={(o) => o.id}
                    columns={[
                        {
                            header: 'Nomor pesanan',
                            render: (o) => (
                                <span className="font-medium">
                                    {o.order_number}
                                </span>
                            ),
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
                            render: (o) => (
                                <RowActions>
                                    <DetailLink href={show.url(o.id)} />
                                    {!staffList &&
                                        o.status === 'pending_payment' && (
                                            <Button
                                                asChild
                                                size="sm"
                                                className="bg-emerald-700 text-white hover:bg-emerald-800"
                                            >
                                                <Link
                                                    href={`${show.url(o.id)}#pembayaran`}
                                                >
                                                    <Wallet className="size-3.5" />
                                                    Pembayaran
                                                </Link>
                                            </Button>
                                        )}
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={orders} />
            </main>
        </>
    );
}
