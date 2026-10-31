import { ListFilters } from '@/components/list-filters';
import { CustomerEditor } from '@/components/customer-editor';
import {
    DataTable,
    DetailLink,
    RecordDialog,
    RowActions,
} from '@/components/data-list';
import { Head } from '@inertiajs/react';
import { Pager } from '@/components/commerce-ui';
import { show } from '@/routes/admin/customers';
import type { Paginated } from '@/types/commerce';
export default function Customers({
    customers,
}: {
    customers: Paginated<{
        id: number;
        name: string;
        email: string;
        phone: string | null;
    }>;
    search: string;
}) {
    return (
        <>
            <Head title="Pelanggan" />
            <main className="grid gap-5 p-6">
                <h1 className="text-3xl font-bold">Pelanggan</h1>
                <div className="flex justify-end">
                    <RecordDialog
                        kind="add"
                        label="Tambah pelanggan"
                        title="Tambah kontak pelanggan"
                    >
                        <CustomerEditor />
                    </RecordDialog>
                </div>
                <ListFilters
                    placeholder="Nama, email, atau telepon"
                    filterLabel="Jenis kontak"
                    statuses={{
                        linked: 'Terhubung akun',
                        guest: 'Kontak mandiri',
                    }}
                />
                <DataTable
                    label="Daftar pelanggan"
                    rows={customers.data}
                    rowKey={(c) => c.id}
                    columns={[
                        { header: 'Nama', render: (c) => c.name },
                        { header: 'Email', render: (c) => c.email },
                        { header: 'Telepon', render: (c) => c.phone || '-' },
                        {
                            header: 'Aksi',
                            render: (c) => (
                                <RowActions>
                                    <DetailLink href={show.url(c.id)} />
                                    <RecordDialog
                                        kind="edit"
                                        title={`Edit ${c.name}`}
                                    >
                                        <CustomerEditor customer={c} />
                                    </RecordDialog>
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={customers} />
            </main>
        </>
    );
}
