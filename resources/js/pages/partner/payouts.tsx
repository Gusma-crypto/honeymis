import { ListFilters } from '@/components/list-filters';
import { confirmAction } from '@/lib/sweet-alert';
import {
    DataTable,
    DetailFields,
    RecordDialog,
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
import { store } from '@/routes/payouts';
import type { Paginated } from '@/types/commerce';
export default function Payouts({
    withdrawable,
    minimum,
    canRequest,
    payouts,
}: {
    withdrawable: number;
    minimum: number;
    canRequest: boolean;
    payouts: Paginated<{
        id: number;
        amount: number;
        status: string;
        bank_last4: string;
        reason: string | null;
        proof_url: string | null;
    }>;
}) {
    const key = useRequestKey();
    const form = useForm({
        amount: minimum,
        bank_name: '',
        account_name: '',
        account_number: '',
        idempotency_key: '',
    });
    return (
        <>
            <Head title="Payout mitra" />
            <main className="grid gap-6 p-6">
                <h1 className="text-3xl font-bold">Payout mitra</h1>
                <p>
                    Saldo dapat ditarik {money(withdrawable)} &middot; Minimum{' '}
                    {money(minimum)}
                </p>
                {canRequest && (
                    <RecordDialog
                        kind="add"
                        label="Tambah pengajuan"
                        title="Ajukan payout"
                        disabled={withdrawable < minimum}
                    >
                        <form
                            className="grid max-w-xl gap-4 rounded-xl border p-5"
                            onSubmit={async (e) => {
                                e.preventDefault();
                                if (
                                    !(await confirmAction(
                                        'Kirim pengajuan payout?',
                                        `${money(form.data.amount)} ke ${form.data.bank_name}, rekening atas nama ${form.data.account_name}. Saldo akan direservasi.`,
                                    ))
                                )
                                    return;
                                form.transform((d) => ({
                                    ...d,
                                    idempotency_key: key.get(),
                                }));
                                form.post(store.url(), {
                                    onSuccess: () => {
                                        key.clear();
                                        form.reset();
                                    },
                                });
                            }}
                        >
                            <Field label="Nominal IDR">
                                <input
                                    className={inputClass}
                                    type="number"
                                    min={minimum}
                                    max={withdrawable}
                                    step="1"
                                    required
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'amount',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            {(
                                [
                                    'bank_name',
                                    'account_name',
                                    'account_number',
                                ] as const
                            ).map((field, i) => (
                                <Field
                                    key={field}
                                    label={
                                        [
                                            'Nama bank',
                                            'Nama pemilik rekening',
                                            'Nomor rekening',
                                        ][i]
                                    }
                                >
                                    <input
                                        className={inputClass}
                                        required
                                        autoComplete="off"
                                        inputMode={
                                            field === 'account_number'
                                                ? 'numeric'
                                                : 'text'
                                        }
                                        value={form.data[field]}
                                        onChange={(e) =>
                                            form.setData(field, e.target.value)
                                        }
                                    />
                                </Field>
                            ))}
                            <p className="text-sm">
                                Dana direservasi saat pengajuan. Finance
                                meninjau dan mencatat transfer manual.
                            </p>
                            <Errors errors={form.errors} />
                            <Button
                                disabled={
                                    form.processing || withdrawable < minimum
                                }
                            >
                                Ajukan payout
                            </Button>
                        </form>
                    </RecordDialog>
                )}
                <h2 className="text-xl font-semibold">Riwayat pengajuan</h2>
                <ListFilters
                    placeholder="Nomor payout atau 4 digit rekening"
                    statuses={{
                        requested: 'Diajukan',
                        approved: 'Disetujui',
                        paid: 'Dibayar',
                        rejected: 'Ditolak',
                        failed: 'Gagal',
                    }}
                />
                <DataTable
                    label="Riwayat payout"
                    rows={payouts.data}
                    rowKey={(p) => p.id}
                    columns={[
                        { header: 'ID', render: (p) => `#${p.id}` },
                        { header: 'Nominal', render: (p) => money(p.amount) },
                        {
                            header: 'Rekening',
                            render: (p) => `****${p.bank_last4}`,
                        },
                        {
                            header: 'Status',
                            render: (p) => <StatusBadge status={p.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (p) => (
                                <RecordDialog title={`Detail payout #${p.id}`}>
                                    <DetailFields
                                        rows={[
                                            ['ID', p.id],
                                            ['Nominal', money(p.amount)],
                                            ['Rekening', `****${p.bank_last4}`],
                                            [
                                                'Status',
                                                <StatusBadge
                                                    status={p.status}
                                                />,
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
                            ),
                        },
                    ]}
                />
                <Pager page={payouts} />
            </main>
        </>
    );
}
