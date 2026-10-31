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
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/admin/payouts';
import type { Paginated } from '@/types/commerce';
const operationLabels: Record<string, string> = {
    approve: 'Setujui',
    paid: 'Catat dibayar',
    reject: 'Tolak',
    fail: 'Tandai gagal',
};
type Payout = {
    id: number;
    partner_name: string;
    amount: number;
    status: string;
    bank: { bank_name: string; account_name: string; account_number: string };
    reason: string | null;
    canReview: boolean;
    proof_url: string | null;
};
function Review({ payout: p }: { payout: Payout }) {
    const key = useRequestKey();
    const f = useForm<{
        operation: string;
        reason: string;
        transfer_reference: string;
        proof: File | null;
        confirmed_transfer: boolean;
        idempotency_key: string;
    }>({
        operation: '',
        reason: '',
        transfer_reference: '',
        proof: null,
        confirmed_transfer: false,
        idempotency_key: '',
    });
    async function perform(op: string) {
        if (
            f.processing ||
            !(await confirmAction(
                `${operationLabels[op]} payout #${p.id}?`,
                `${p.partner_name} ? ${money(p.amount)}. Pastikan data dan bukti transfer sesuai.`,
                ['reject', 'fail'].includes(op),
            ))
        )
            return;
        f.transform((d) => ({
            ...d,
            operation: op,
            idempotency_key: key.get(),
        }));
        f.post(update.url(p.id), {
            forceFormData: true,
            onSuccess: () => {
                key.clear();
                f.reset();
            },
        });
    }
    return (
        <article className="grid gap-4 rounded-xl border p-5">
            <h2 className="text-xl font-semibold">
                #{p.id} {p.partner_name} &middot; {money(p.amount)}
            </h2>
            <p>
                {p.status} &middot; {p.bank.bank_name} &middot;{' '}
                {p.bank.account_name} &middot; {p.bank.account_number}
            </p>
            {p.reason && <p>{p.reason}</p>}
            {p.proof_url && (
                <a className="underline" href={p.proof_url}>
                    Unduh bukti
                </a>
            )}
            {p.canReview && ['requested', 'approved'].includes(p.status) && (
                <>
                    <Field label="Alasan penolakan / gagal">
                        <input
                            className={inputClass}
                            value={f.data.reason}
                            onChange={(e) =>
                                f.setData('reason', e.target.value)
                            }
                        />
                    </Field>
                    {p.status === 'approved' && (
                        <>
                            <Field label="Referensi transfer unik">
                                <input
                                    className={inputClass}
                                    value={f.data.transfer_reference}
                                    onChange={(e) =>
                                        f.setData(
                                            'transfer_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Bukti transfer (JPG, PNG, PDF; maksimal 5 MB)">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,application/pdf"
                                    onChange={(e) =>
                                        f.setData(
                                            'proof',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </Field>
                            <label className="flex gap-3">
                                <input
                                    type="checkbox"
                                    checked={f.data.confirmed_transfer}
                                    onChange={(e) =>
                                        f.setData(
                                            'confirmed_transfer',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Transfer sudah dilakukan dan bukti telah
                                diperiksa.
                            </label>
                        </>
                    )}
                    <div className="flex flex-wrap gap-3">
                        {(p.status === 'requested'
                            ? ['approve', 'reject']
                            : ['paid', 'reject', 'fail']
                        ).map((op) => (
                            <Button
                                key={op}
                                variant={op === 'paid' ? 'default' : 'outline'}
                                disabled={
                                    f.processing ||
                                    (op === 'paid' &&
                                        (!f.data.confirmed_transfer ||
                                            !f.data.proof ||
                                            !f.data.transfer_reference))
                                }
                                onClick={() => perform(op)}
                            >
                                {operationLabels[op] ?? op}
                            </Button>
                        ))}
                    </div>
                </>
            )}
            <Errors errors={f.errors} />
        </article>
    );
}
export default function Payouts({ payouts }: { payouts: Paginated<Payout> }) {
    return (
        <>
            <Head title="Review payout" />
            <main className="grid gap-5 p-4 sm:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">Payout mitra</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pengajuan dibuat oleh mitra. Finance meninjau dan
                        mencatat transfer manual.
                    </p>
                </header>
                <ListFilters
                    placeholder="Nomor payout, mitra, atau referensi"
                    statuses={{
                        requested: 'Diajukan',
                        approved: 'Disetujui',
                        paid: 'Dibayar',
                        rejected: 'Ditolak',
                        failed: 'Gagal',
                    }}
                />
                <DataTable
                    label="Daftar payout"
                    rows={payouts.data}
                    rowKey={(p) => p.id}
                    columns={[
                        { header: 'ID', render: (p) => `#${p.id}` },
                        { header: 'Mitra', render: (p) => p.partner_name },
                        { header: 'Nominal', render: (p) => money(p.amount) },
                        { header: 'Bank', render: (p) => p.bank.bank_name },
                        {
                            header: 'Status',
                            render: (p) => <StatusBadge status={p.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (p) => (
                                <RowActions>
                                    <RecordDialog
                                        title={`Detail payout #${p.id}`}
                                    >
                                        <DetailFields
                                            rows={[
                                                ['Mitra', p.partner_name],
                                                ['Nominal', money(p.amount)],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={p.status}
                                                    />,
                                                ],
                                                ['Bank', p.bank.bank_name],
                                                [
                                                    'Pemilik rekening',
                                                    p.bank.account_name,
                                                ],
                                                [
                                                    'Nomor rekening',
                                                    p.bank.account_number,
                                                ],
                                                ['Alasan', p.reason],
                                            ]}
                                        />
                                        {p.proof_url && (
                                            <a
                                                className="text-sm underline"
                                                href={p.proof_url}
                                            >
                                                Unduh bukti transfer
                                            </a>
                                        )}
                                    </RecordDialog>
                                    {p.canReview &&
                                        ['requested', 'approved'].includes(
                                            p.status,
                                        ) && (
                                            <RecordDialog
                                                kind="edit"
                                                label="Proses"
                                                title={`Proses payout #${p.id}`}
                                            >
                                                <Review payout={p} />
                                            </RecordDialog>
                                        )}
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={payouts} />
            </main>
        </>
    );
}
