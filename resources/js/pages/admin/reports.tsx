import { DataTable, DetailFields, RecordDialog } from '@/components/data-list';
import { Head, useForm } from '@inertiajs/react';
import { Field, inputClass, money, Errors } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { index, exportMethod } from '@/routes/admin/reports';
type Report = {
    orders: number;
    paidOrders: number;
    pendingOrders: number;
    gross: number;
    refunds: number;
    net: number;
    activePartners: number;
    partnerSales: {
        partner_referral_id: number;
        orders: number;
        total: number;
    }[];
    finance?: {
        commissions: number;
        estimatedMargin: number;
        payoutReserved: number;
        payoutPaid: number;
    };
};
export default function Reports({
    report: r,
    filters,
    financialAccess,
}: {
    report: Report;
    filters: { from: string; until: string };
    financialAccess: boolean;
}) {
    const f = useForm(filters);
    return (
        <>
            <Head title="Laporan" />
            <main className="grid gap-6 p-6">
                <h1 className="text-3xl font-bold">Laporan penjualan</h1>
                <form
                    className="flex flex-wrap items-end gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.get(index.url());
                    }}
                >
                    <Field label="Dari (WIB)">
                        <input
                            className={inputClass}
                            type="date"
                            value={f.data.from}
                            onChange={(e) => f.setData('from', e.target.value)}
                        />
                    </Field>
                    <Field label="Sampai (WIB)">
                        <input
                            className={inputClass}
                            type="date"
                            value={f.data.until}
                            onChange={(e) => f.setData('until', e.target.value)}
                        />
                    </Field>
                    <Button>Terapkan</Button>
                    <Errors errors={f.errors} />
                </form>
                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        ['Pesanan', r.orders],
                        ['Dibayar', r.paidOrders],
                        ['Menunggu pembayaran', r.pendingOrders],
                        ['Mitra aktif', r.activePartners],
                    ].map(([k, v]) => (
                        <article className="rounded-xl border p-5" key={k}>
                            <p>{k}</p>
                            <strong className="text-2xl">{v}</strong>
                        </article>
                    ))}
                    {[
                        ['Omzet bruto', r.gross],
                        ['Refund', r.refunds],
                        ['Penjualan net', r.net],
                        ...(r.finance
                            ? [
                                  ['Komisi net', r.finance.commissions],
                                  [
                                      'Estimasi margin',
                                      r.finance.estimatedMargin,
                                  ],
                              ]
                            : []),
                    ].map(([k, v]) => (
                        <article className="rounded-xl border p-5" key={k}>
                            <p>{k}</p>
                            <strong className="text-2xl">
                                {money(Number(v))}
                            </strong>
                        </article>
                    ))}
                </div>
                <p>
                    Penjualan mengikuti tanggal order dibuat; refund adalah
                    total refund order dalam kelompok tanggal tersebut. Estimasi
                    margin menggunakan snapshot biaya, belum memasukkan biaya
                    operasional.
                </p>
                {r.finance && (
                    <p>
                        Seluruh periode: payout direservasi{' '}
                        {money(r.finance.payoutReserved)} ? payout dibayar{' '}
                        {money(r.finance.payoutPaid)}
                    </p>
                )}
                <section className="grid gap-2">
                    <h2 className="text-xl font-semibold">
                        Penjualan per mitra
                    </h2>
                    <DataTable
                        label="Penjualan per mitra"
                        searchable
                        localFilter={{
                            label: 'Aktivitas',
                            options: {
                                sales: 'Ada penjualan',
                                empty: 'Tanpa penjualan',
                            },
                            value: (row) =>
                                row.orders > 0 ? 'sales' : 'empty',
                        }}
                        rows={r.partnerSales}
                        rowKey={(p) => p.partner_referral_id}
                        columns={[
                            {
                                header: 'Mitra',
                                render: (p) => `#${p.partner_referral_id}`,
                            },
                            {
                                header: 'Jumlah pesanan',
                                render: (p) => p.orders,
                            },
                            {
                                header: 'Penjualan',
                                render: (p) => money(Number(p.total)),
                            },
                            {
                                header: 'Aksi',
                                render: (p) => (
                                    <RecordDialog
                                        title={`Penjualan mitra #${p.partner_referral_id}`}
                                    >
                                        <DetailFields
                                            rows={[
                                                [
                                                    'Mitra',
                                                    p.partner_referral_id,
                                                ],
                                                ['Jumlah pesanan', p.orders],
                                                [
                                                    'Penjualan',
                                                    money(Number(p.total)),
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                ),
                            },
                        ]}
                    />
                </section>
                <div className="flex flex-wrap gap-4">
                    {(financialAccess
                        ? ['orders', 'commissions', 'wallet', 'payouts']
                        : ['orders']
                    ).map((type) => (
                        <a
                            className="underline"
                            key={type}
                            href={exportMethod.url({
                                query: { type, ...filters },
                            })}
                        >
                            CSV {type}
                        </a>
                    ))}
                </div>
            </main>
        </>
    );
}
