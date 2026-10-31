import { confirmAction } from '@/lib/sweet-alert';
import { DataTable, StatusBadge } from '@/components/data-list';
import { useForm } from '@inertiajs/react';
import {
    Errors,
    Field,
    inputClass,
    money,
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { refund } from '@/routes/orders';
import { store } from '@/routes/admin/refunds';
import { hold } from '@/routes/admin/orders';
export function OrderFinanceControls({
    orderId,
    items,
    canRequest,
    finance,
    onHold,
}: {
    orderId: number;
    items: { id: number; name: string }[];
    canRequest: boolean;
    finance: boolean;
    onHold: boolean;
}) {
    const key = useRequestKey();
    const holdKey = useRequestKey();
    const f = useForm({
        order_item_id: '',
        amount: 0,
        reason: '',
        idempotency_key: '',
        confirmed_refund: false,
    });
    const h = useForm({ hold: false, idempotency_key: '' });
    return (
        <section className="grid gap-4 rounded-xl border p-5">
            {finance && (
                <>
                    <p>
                        Status peninjauan fraud: {onHold ? 'Ditahan' : 'Normal'}
                    </p>
                    <Button
                        variant="outline"
                        disabled={h.processing}
                        onClick={async () => {
                            if (
                                !(await confirmAction(
                                    onHold
                                        ? 'Lepas penahanan pesanan?'
                                        : 'Tahan pesanan dan komisi?',
                                    `Ubah status peninjauan pesanan #${orderId}.`,
                                    !onHold,
                                ))
                            )
                                return;
                            h.transform((d) => ({
                                ...d,
                                hold: !onHold,
                                idempotency_key: holdKey.get(),
                            }));
                            h.patch(hold.url(orderId), {
                                onSuccess: holdKey.clear,
                            });
                        }}
                    >
                        {onHold ? 'Lepas hold' : 'Tahan order & komisi'}
                    </Button>
                    <Errors errors={h.errors} />
                </>
            )}
            {(canRequest || finance) && (
                <form
                    className="grid gap-4"
                    onSubmit={async (e) => {
                        e.preventDefault();
                        if (
                            !(await confirmAction(
                                finance
                                    ? 'Catat refund manual dan reversal?'
                                    : 'Ajukan refund?',
                                `Pesanan #${orderId} sebesar ${money(f.data.amount)}.`,
                                true,
                            ))
                        )
                            return;
                        f.transform((d) => ({
                            ...d,
                            idempotency_key: key.get(),
                        }));
                        f.post(
                            finance ? store.url(orderId) : refund.url(orderId),
                            {
                                onSuccess: () => {
                                    key.clear();
                                    f.reset();
                                },
                            },
                        );
                    }}
                >
                    <h2 className="text-xl font-semibold">
                        {finance ? 'Catat refund manual' : 'Ajukan refund'}
                    </h2>
                    <Field label="Komponen refund">
                        <select
                            className={inputClass}
                            value={f.data.order_item_id}
                            onChange={(e) =>
                                f.setData('order_item_id', e.target.value)
                            }
                        >
                            <option value="">Ongkir / pajak</option>
                            {items.map((i) => (
                                <option key={i.id} value={i.id}>
                                    {i.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Nominal IDR">
                        <input
                            className={inputClass}
                            type="number"
                            required
                            min="1"
                            step="1"
                            value={f.data.amount}
                            onChange={(e) =>
                                f.setData('amount', Number(e.target.value))
                            }
                        />
                    </Field>
                    <Field label="Alasan">
                        <textarea
                            className={inputClass}
                            required
                            value={f.data.reason}
                            onChange={(e) =>
                                f.setData('reason', e.target.value)
                            }
                        />
                    </Field>
                    {finance && (
                        <label className="flex gap-3">
                            <input
                                type="checkbox"
                                checked={f.data.confirmed_refund}
                                onChange={(e) =>
                                    f.setData(
                                        'confirmed_refund',
                                        e.target.checked,
                                    )
                                }
                            />
                            Dana sudah dikembalikan manual dan telah diperiksa.
                        </label>
                    )}
                    <Errors errors={f.errors} />
                    <Button
                        disabled={
                            f.processing ||
                            (finance && !f.data.confirmed_refund)
                        }
                    >
                        {finance
                            ? 'Catat refund & reversal'
                            : 'Kirim permintaan'}
                    </Button>
                </form>
            )}
        </section>
    );
}
export function RefundHistory({
    refunds,
}: {
    refunds: { id: number; amount: number; status: string; reason: string }[];
}) {
    return (
        refunds.length > 0 && (
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">Riwayat refund</h2>
                <DataTable
                    label="Riwayat refund"
                    rows={refunds}
                    rowKey={(r) => r.id}
                    columns={[
                        { header: 'Nomor', render: (r) => `#${r.id}` },
                        { header: 'Nominal', render: (r) => money(r.amount) },
                        {
                            header: 'Status',
                            render: (r) => <StatusBadge status={r.status} />,
                        },
                        { header: 'Alasan', render: (r) => r.reason },
                    ]}
                />
            </section>
        )
    );
}
