import { PartnerNetworkSummary } from '@/components/partner-network-summary';
import type { PartnerNetworkData } from '@/components/partner-network-summary';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    StatusBadge,
} from '@/components/data-list';
import { Head, Link, useForm } from '@inertiajs/react';
import { Errors, Field, inputClass, money } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { apply } from '@/routes/partner';
import { index as wallet } from '@/routes/wallet';
import { index as payouts } from '@/routes/payouts';
type Balances = Record<string, number>;
export default function PartnerDashboard({
    partner,
    balances,
    commissions,
    attributedOrders,
    network,
    networkSummary,
}: {
    partner: {
        status: string;
        referral_code: string;
        referral_url: string;
    } | null;
    balances: Balances | null;
    commissions: {
        id: number;
        product: string;
        amount: number;
        reversed_amount: number;
        offset_amount: number;
        status: string;
    }[];
    attributedOrders: number;
    network: { name: string; status: string }[];
    networkSummary: PartnerNetworkData | null;
}) {
    const form = useForm({ sponsor_code: '', accept_terms: false });
    return (
        <>
            <Head title="Portal mitra" />
            <main className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <h1 className="text-3xl font-bold">Portal mitra</h1>
                {networkSummary && (
                    <PartnerNetworkSummary data={networkSummary} />
                )}
                <p>
                    Komisi berasal dari penjualan produk eligible satu level.
                    Pendaftaran dan perekrutan tidak menghasilkan bonus.
                </p>
                {!partner ? (
                    <form
                        className="grid max-w-xl gap-4 rounded-xl border p-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(apply.url());
                        }}
                    >
                        <h2 className="text-xl font-semibold">
                            Ajukan kemitraan
                        </h2>
                        <Field label="Kode sponsor (opsional)">
                            <input
                                className={inputClass}
                                value={form.data.sponsor_code}
                                onChange={(e) =>
                                    form.setData('sponsor_code', e.target.value)
                                }
                            />
                        </Field>
                        <label className="flex gap-3">
                            <input
                                type="checkbox"
                                checked={form.data.accept_terms}
                                onChange={(e) =>
                                    form.setData(
                                        'accept_terms',
                                        e.target.checked,
                                    )
                                }
                            />
                            Saya memahami komisi hanya untuk transaksi eligible,
                            setelah pembayaran dan masa retur.
                        </label>
                        <Errors errors={form.errors} />
                        <Button disabled={form.processing}>
                            Kirim pengajuan
                        </Button>
                    </form>
                ) : (
                    <>
                        <section className="grid gap-3 rounded-xl border p-5">
                            <h2 className="text-xl font-semibold">
                                Status: {partner.status}
                            </h2>
                            <p>
                                Kode referral:{' '}
                                <strong>{partner.referral_code}</strong>
                            </p>
                            {partner.status === 'active' && (
                                <Field label="Tautan referral Anda">
                                    <input
                                        className={inputClass}
                                        readOnly
                                        value={partner.referral_url}
                                        onFocus={(e) => e.target.select()}
                                    />
                                </Field>
                            )}
                            <p>{attributedOrders} pesanan teratribusi</p>
                            <div className="flex gap-5">
                                <Link className="underline" href={wallet()}>
                                    Dompet & mutasi
                                </Link>
                                <Link className="underline" href={payouts()}>
                                    Pengajuan payout
                                </Link>
                            </div>
                        </section>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {Object.entries(balances ?? {}).map(
                                ([label, value]) => (
                                    <article
                                        className="rounded-xl border p-5"
                                        key={label}
                                    >
                                        <p>{label}</p>
                                        <p className="text-2xl font-bold">
                                            {money(value)}
                                        </p>
                                    </article>
                                ),
                            )}
                        </div>
                        <section className="grid gap-3">
                            <h2 className="text-xl font-semibold">
                                50 komisi terakhir
                            </h2>
                            <DataTable
                                label="Daftar komisi"
                                searchable
                                localFilter={{
                                    label: 'Status komisi',
                                    options: {
                                        pending: 'Menunggu',
                                        available: 'Tersedia',
                                        reserved: 'Direservasi',
                                        paid_out: 'Dicairkan',
                                        cancelled: 'Dibatalkan',
                                        reversed: 'Dibalik',
                                    },
                                    value: (row) => row.status,
                                }}
                                rows={commissions}
                                rowKey={(c) => c.id}
                                columns={[
                                    {
                                        header: 'Produk',
                                        render: (c) => c.product,
                                    },
                                    {
                                        header: 'Komisi awal',
                                        render: (c) => money(c.amount),
                                    },
                                    {
                                        header: 'Reversal',
                                        render: (c) => money(c.reversed_amount),
                                    },
                                    {
                                        header: 'Komisi net',
                                        render: (c) =>
                                            money(c.amount - c.reversed_amount),
                                    },
                                    {
                                        header: 'Status',
                                        render: (c) => (
                                            <StatusBadge status={c.status} />
                                        ),
                                    },
                                    {
                                        header: 'Aksi',
                                        render: (c) => (
                                            <RecordDialog
                                                title={`Detail komisi #${c.id}`}
                                            >
                                                <DetailFields
                                                    rows={[
                                                        ['Produk', c.product],
                                                        [
                                                            'Komisi awal',
                                                            money(c.amount),
                                                        ],
                                                        [
                                                            'Reversal',
                                                            money(
                                                                c.reversed_amount,
                                                            ),
                                                        ],
                                                        [
                                                            'Pelunasan piutang',
                                                            money(
                                                                c.offset_amount,
                                                            ),
                                                        ],
                                                        [
                                                            'Status',
                                                            <StatusBadge
                                                                status={
                                                                    c.status
                                                                }
                                                            />,
                                                        ],
                                                    ]}
                                                />
                                            </RecordDialog>
                                        ),
                                    },
                                ]}
                            />
                        </section>
                        <section>
                            <h2 className="text-xl font-semibold">
                                Jaringan sponsor langsung
                            </h2>
                            <p className="my-3 text-sm">
                                Informasi jaringan saja; tanpa bonus perekrutan
                                atau komisi bertingkat.
                            </p>
                            <DataTable
                                label="Jaringan sponsor langsung"
                                searchable
                                localFilter={{
                                    label: 'Status mitra',
                                    options: {
                                        pending: 'Menunggu',
                                        active: 'Aktif',
                                        suspended: 'Ditangguhkan',
                                        rejected: 'Ditolak',
                                    },
                                    value: (row) => row.status,
                                }}
                                rows={network}
                                rowKey={(member, index) =>
                                    `${member.name}-${index}`
                                }
                                columns={[
                                    {
                                        header: 'Nama mitra',
                                        render: (n) => n.name,
                                    },
                                    {
                                        header: 'Status',
                                        render: (n) => (
                                            <StatusBadge status={n.status} />
                                        ),
                                    },
                                    {
                                        header: 'Aksi',
                                        render: (n) => (
                                            <RecordDialog title="Detail mitra langsung">
                                                <DetailFields
                                                    rows={[
                                                        ['Nama', n.name],
                                                        [
                                                            'Status',
                                                            <StatusBadge
                                                                status={
                                                                    n.status
                                                                }
                                                            />,
                                                        ],
                                                    ]}
                                                />
                                            </RecordDialog>
                                        ),
                                    },
                                ]}
                            />
                        </section>
                    </>
                )}
            </main>
        </>
    );
}
