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
import { Errors, Pager, useRequestKey } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/admin/partners';
import type { Paginated } from '@/types/commerce';
const statusActions: Record<string, string> = {
    active: 'Aktifkan',
    suspended: 'Tangguhkan',
    rejected: 'Tolak',
};
type Partner = {
    id: number;
    name: string;
    email: string;
    status: string;
    referral_code: string;
    sponsor_partner_id: number | null;
};
function Editor({ partner: p }: { partner: Partner }) {
    const key = useRequestKey();
    const form = useForm({ status: '', idempotency_key: '' });
    return (
        <article className="grid gap-3 rounded-xl border p-5">
            <h2 className="font-semibold">
                {p.name} &middot; {p.status}
            </h2>
            <p>
                {p.email} &middot; {p.referral_code} &middot; Sponsor #
                {p.sponsor_partner_id ?? '-'}
            </p>
            <div className="flex gap-3">
                {['active', 'suspended', 'rejected']
                    .filter((s) => s !== p.status)
                    .map((s) => (
                        <Button
                            key={s}
                            variant="outline"
                            disabled={form.processing}
                            onClick={async () => {
                                if (
                                    !(await confirmAction(
                                        `${statusActions[s]} mitra?`,
                                        `Status mitra ${p.name} akan diperbarui.`,
                                        s !== 'active',
                                    ))
                                )
                                    return;
                                form.transform((d) => ({
                                    ...d,
                                    status: s,
                                    idempotency_key: key.get(),
                                }));
                                form.patch(update.url(p.id), {
                                    onSuccess: key.clear,
                                });
                            }}
                        >
                            {statusActions[s] ?? s}
                        </Button>
                    ))}
            </div>
            <Errors errors={form.errors} />
        </article>
    );
}
export default function Partners({
    partners,
}: {
    partners: Paginated<Partner>;
}) {
    return (
        <>
            <Head title="Kelola mitra" />
            <main className="grid gap-5 p-4 sm:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">Daftar mitra</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Tinjau pengajuan dan ubah status mitra. Pengajuan baru
                        melalui Portal mitra.
                    </p>
                </header>
                <ListFilters
                    placeholder="Nama, email, atau kode referral"
                    statuses={{
                        pending: 'Menunggu',
                        active: 'Aktif',
                        suspended: 'Ditangguhkan',
                        rejected: 'Ditolak',
                    }}
                />
                <DataTable
                    label="Daftar mitra"
                    rows={partners.data}
                    rowKey={(p) => p.id}
                    columns={[
                        { header: 'Nama', render: (p) => p.name },
                        { header: 'Email', render: (p) => p.email },
                        {
                            header: 'Kode referral',
                            render: (p) => (
                                <span className="font-mono text-xs">
                                    {p.referral_code}
                                </span>
                            ),
                        },
                        {
                            header: 'Status',
                            render: (p) => <StatusBadge status={p.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (p) => (
                                <RowActions>
                                    <RecordDialog title={`Detail ${p.name}`}>
                                        <DetailFields
                                            rows={[
                                                ['Nama', p.name],
                                                ['Email', p.email],
                                                [
                                                    'Kode referral',
                                                    p.referral_code,
                                                ],
                                                [
                                                    'Sponsor',
                                                    p.sponsor_partner_id ?? '-',
                                                ],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={p.status}
                                                    />,
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                    <RecordDialog
                                        kind="edit"
                                        title={`Edit status ${p.name}`}
                                    >
                                        <Editor partner={p} />
                                    </RecordDialog>
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={partners} />
            </main>
        </>
    );
}
