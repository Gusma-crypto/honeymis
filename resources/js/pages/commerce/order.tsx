import { MenuTitle } from '@/components/menu-title';
import { ReceiptText as MenuIcon } from 'lucide-react';
import {
    ArrowDownToLine,
    ArrowRight,
    Clock3,
    Landmark,
    Upload,
} from 'lucide-react';
import { PurchaseSteps } from '@/components/purchase-steps';
import { confirmAction } from '@/lib/sweet-alert';
import { DataTable, StatusBadge } from '@/components/data-list';
import {
    OrderFinanceControls,
    RefundHistory,
} from '@/components/order-finance-controls';
import { Head, useForm } from '@inertiajs/react';
import {
    Errors,
    Field,
    inputClass,
    money,
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/orders';
import { store as upload } from '@/routes/payments';
type Order = {
    id: number;
    order_number: string;
    status: string;
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    shipping?: {
        provider: string;
        courier?: string;
        service: string;
        weight_g: number;
        etd?: string;
    } | null;
    grand_total: number;
    expires_at: string | null;
    address: Record<string, string>;
    fraud_hold: boolean;
    refunds: { id: number; amount: number; status: string; reason: string }[];
    items: {
        id: number;
        name: string;
        sku: string;
        quantity: number;
        unit_price: number;
        discount: number;
    }[];
    payments: { id: number; status: string; proof_url: string | null }[];
    shipments: { courier: string; tracking_number: string; status: string }[];
};
export default function OrderDetail({
    order,
    can,
    paymentInstructions,
}: {
    order: Order;
    can: {
        refund: boolean;
        financeRefund: boolean;
        cancel: boolean;
        upload: boolean;
        verify: boolean;
        manage: boolean;
    };
    paymentInstructions: string;
}) {
    const operationKey = useRequestKey();
    const proofKey = useRequestKey();
    const operation = useForm({
        operation: '',
        courier: '',
        tracking_number: '',
        idempotency_key: '',
    });
    const proof = useForm<{ proof: File | null; idempotency_key: string }>({
        proof: null,
        idempotency_key: '',
    });
    async function perform(action: string) {
        const labels: Record<string, string> = {
            cancel: 'Batalkan pesanan',
            verify: 'Verifikasi pembayaran',
            reject: 'Tolak bukti pembayaran',
            process: 'Proses pesanan',
            ship: 'Tandai dikirim',
            deliver: 'Konfirmasi diterima',
        };
        if (
            operation.processing ||
            !(await confirmAction(
                `${labels[action] ?? action}?`,
                `Pesanan ${order.order_number} - ${money(order.grand_total)}. Periksa kembali sebelum melanjutkan.`,
                ['cancel', 'reject'].includes(action),
            ))
        )
            return;
        operation.transform((data) => ({
            ...data,
            operation: action,
            idempotency_key: operationKey.get(),
        }));
        operation.patch(update.url(order.id), {
            onSuccess: operationKey.clear,
            preserveScroll: true,
        });
    }
    const latestPayment = order.payments.at(-1);
    const awaitingPayment = order.status === 'pending_payment';
    const canUpload =
        can.upload &&
        awaitingPayment &&
        ['pending', 'rejected'].includes(latestPayment?.status ?? '');
    const submitted = awaitingPayment && latestPayment?.status === 'submitted';
    return (
        <>
            <Head title={order.order_number} />
            <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                {can.upload &&
                    !['cancelled', 'refunded'].includes(order.status) && (
                        <PurchaseSteps
                            current={submitted || !awaitingPayment ? 4 : 3}
                        />
                    )}
                <header>
                    <p className="text-sm text-muted-foreground">
                        Nomor pesanan
                    </p>
                    <MenuTitle icon={MenuIcon} tone="blue">
                        {order.order_number}
                    </MenuTitle>
                    <div className="mt-3">
                        <StatusBadge status={order.status} />
                    </div>
                    {canUpload && (
                        <Button
                            asChild
                            className="mt-4 bg-emerald-700 text-white hover:bg-emerald-800"
                        >
                            <a href="#pembayaran">
                                Lanjut pembayaran
                                <ArrowRight className="size-4" />
                            </a>
                        </Button>
                    )}
                </header>
                <section className="grid gap-5 rounded-xl border bg-card p-5 md:grid-cols-[1fr_2fr]">
                    <div>
                        <h2 className="mb-2 font-semibold">
                            Alamat pengiriman
                        </h2>
                        <p>{Object.values(order.address).join(', ')}</p>
                    </div>
                    <div>
                        <DataTable
                            label="Item pesanan"
                            rows={order.items}
                            rowKey={(item) => item.id}
                            columns={[
                                {
                                    header: 'Produk',
                                    render: (item) => (
                                        <div>
                                            <p className="font-medium">
                                                {item.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.sku}
                                            </p>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Jumlah',
                                    render: (item) => item.quantity,
                                },
                                {
                                    header: 'Harga satuan',
                                    render: (item) => money(item.unit_price),
                                },
                                {
                                    header: 'Total',
                                    render: (item) =>
                                        money(
                                            item.unit_price * item.quantity -
                                                item.discount,
                                        ),
                                },
                            ]}
                        />
                        <p className="mt-3">Subtotal {money(order.subtotal)}</p>
                        <p>Diskon -{money(order.discount_total)}</p>
                        <p>Ongkir {money(order.shipping_total)}</p>
                        {order.shipping && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Layanan: {order.shipping.courier?.toUpperCase()}{' '}
                                {order.shipping.service} ?{' '}
                                {order.shipping.weight_g} gram
                                {order.shipping.etd
                                    ? ` ? Estimasi ${order.shipping.etd}`
                                    : ''}
                            </p>
                        )}
                        <p className="text-xl font-bold">
                            Total {money(order.grand_total)}
                        </p>
                    </div>
                </section>
                <section
                    id="pembayaran"
                    className="flex scroll-mt-5 flex-col gap-4 rounded-2xl border border-emerald-200 bg-card p-6 dark:border-emerald-900"
                >
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Landmark className="size-5 text-emerald-700 dark:text-emerald-300" />
                        Pembayaran transfer manual
                    </h2>
                    <div className="rounded-xl bg-emerald-50 p-5 dark:bg-emerald-950/40">
                        <p className="text-xs font-medium text-emerald-800 dark:text-emerald-200">
                            Total pembayaran pesanan
                        </p>
                        <p className="mt-2 text-3xl font-semibold text-emerald-800 dark:text-emerald-200">
                            {money(order.grand_total)}
                        </p>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Referensi pesanan: {order.order_number}
                        </p>
                    </div>
                    {awaitingPayment && (
                        <ol className="list-inside list-decimal space-y-2 text-sm leading-6 text-muted-foreground">
                            <li>
                                Periksa rekening tujuan pada instruksi resmi di
                                bawah.
                            </li>
                            <li>
                                Transfer sesuai total pesanan, lalu simpan bukti
                                transfer.
                            </li>
                            <li>
                                Unggah bukti pembayaran dan tunggu verifikasi
                                Finance.
                            </li>
                        </ol>
                    )}
                    <div className="rounded-xl border bg-muted/25 p-4">
                        <p className="mb-2 text-xs font-semibold uppercase">
                            Instruksi resmi toko
                        </p>
                        <p className="text-sm leading-7 whitespace-pre-line">
                            {paymentInstructions}
                        </p>
                    </div>
                    {submitted && (
                        <p
                            role="status"
                            className="flex items-start gap-2 rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-950/40 dark:text-blue-200"
                        >
                            <Clock3 className="mt-0.5 size-4 shrink-0" />
                            Bukti pembayaran sudah diterima dan menunggu
                            verifikasi Finance. Anda tidak perlu mengirim ulang
                            selama pemeriksaan.
                        </p>
                    )}
                    {latestPayment?.status === 'rejected' &&
                        awaitingPayment && (
                            <p
                                role="status"
                                className="rounded-xl bg-rose-50 p-4 text-sm text-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
                            >
                                Bukti pembayaran sebelumnya ditolak. Periksa
                                kembali instruksi dan unggah bukti yang sesuai.
                            </p>
                        )}
                    {!awaitingPayment &&
                        !['cancelled', 'refunded'].includes(order.status) && (
                            <p
                                role="status"
                                className="text-sm text-emerald-700 dark:text-emerald-300"
                            >
                                Pembayaran telah diverifikasi. Anda dapat
                                mengikuti status pemrosesan dan pengiriman pada
                                halaman ini.
                            </p>
                        )}
                    {order.expires_at && (
                        <p className="text-sm">
                            Batas unggah bukti:{' '}
                            {new Date(order.expires_at).toLocaleString(
                                'id-ID',
                                { timeZone: 'Asia/Jakarta' },
                            )}{' '}
                            WIB
                        </p>
                    )}
                    {order.payments.map((payment) => (
                        <div key={payment.id} className="flex gap-4">
                            <StatusBadge status={payment.status} />
                            {payment.proof_url && (
                                <a
                                    href={payment.proof_url}
                                    className="underline"
                                >
                                    <ArrowDownToLine className="mr-1 inline size-3.5" />
                                    Unduh bukti transfer
                                </a>
                            )}
                        </div>
                    ))}
                    {canUpload && (
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                proof.transform((data) => ({
                                    ...data,
                                    idempotency_key: proofKey.get(),
                                }));
                                proof.post(upload.url(order.id), {
                                    onSuccess: proofKey.clear,
                                });
                            }}
                        >
                            <Field label="Bukti transfer (JPG/PNG/PDF, maks. 5 MB)">
                                <input
                                    className={inputClass}
                                    type="file"
                                    required
                                    accept="image/jpeg,image/png,application/pdf"
                                    onChange={(e) =>
                                        proof.setData(
                                            'proof',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </Field>
                            <Errors errors={proof.errors} />
                            <Button
                                disabled={proof.processing || !proof.data.proof}
                                className="h-11 self-start bg-emerald-700 text-white hover:bg-emerald-800"
                            >
                                <Upload className="size-4" />
                                {proof.processing
                                    ? 'Mengunggah bukti...'
                                    : 'Unggah bukti pembayaran'}
                            </Button>
                            {proof.progress && (
                                <p
                                    role="status"
                                    className="text-xs text-muted-foreground"
                                >
                                    Mengunggah {proof.progress.percentage}%
                                </p>
                            )}
                            <p className="text-xs leading-5 text-muted-foreground">
                                Unggah bukti setelah transfer dilakukan.
                                Pembayaran belum dianggap selesai sampai
                                diverifikasi Finance.
                            </p>
                        </form>
                    )}
                </section>
                <section
                    id="pengiriman"
                    className="flex scroll-mt-24 flex-col gap-4"
                >
                    <Errors errors={operation.errors} />
                    <div className="flex flex-wrap gap-3">
                        {can.cancel && order.status === 'pending_payment' && (
                            <Button
                                variant="destructive"
                                disabled={operation.processing}
                                onClick={() => perform('cancel')}
                            >
                                Batalkan pesanan
                            </Button>
                        )}
                        {can.verify &&
                            order.status === 'pending_payment' &&
                            latestPayment?.status === 'submitted' && (
                                <>
                                    <Button
                                        disabled={operation.processing}
                                        onClick={() => perform('verify')}
                                    >
                                        Verifikasi pembayaran
                                    </Button>
                                    <Button
                                        variant="outline"
                                        disabled={operation.processing}
                                        onClick={() => perform('reject')}
                                    >
                                        Tolak bukti
                                    </Button>
                                </>
                            )}
                        {can.manage && order.status === 'paid' && (
                            <Button
                                disabled={operation.processing}
                                onClick={() => perform('process')}
                            >
                                Proses pesanan
                            </Button>
                        )}
                        {can.manage && order.status === 'shipped' && (
                            <Button
                                disabled={operation.processing}
                                onClick={() => perform('deliver')}
                            >
                                Konfirmasi diterima
                            </Button>
                        )}
                    </div>
                    {can.manage && order.status === 'processing' && (
                        <form
                            className="flex flex-col gap-3 rounded-xl border p-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                void perform('ship');
                            }}
                        >
                            <Field label="Kurir">
                                <input
                                    required
                                    className={inputClass}
                                    value={operation.data.courier}
                                    onChange={(e) =>
                                        operation.setData(
                                            'courier',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Nomor resi">
                                <input
                                    required
                                    className={inputClass}
                                    value={operation.data.tracking_number}
                                    onChange={(e) =>
                                        operation.setData(
                                            'tracking_number',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Button disabled={operation.processing}>
                                Tandai dikirim
                            </Button>
                        </form>
                    )}
                    {order.shipments.map((shipment) => (
                        <p key={shipment.tracking_number}>
                            {shipment.courier}: {shipment.tracking_number} (
                            {shipment.status})
                        </p>
                    ))}
                </section>
                <RefundHistory refunds={order.refunds} />
                {(can.refund || can.financeRefund) && (
                    <OrderFinanceControls
                        orderId={order.id}
                        items={order.items}
                        canRequest={can.refund}
                        finance={can.financeRefund}
                        onHold={order.fraud_hold}
                    />
                )}
            </main>
        </>
    );
}
