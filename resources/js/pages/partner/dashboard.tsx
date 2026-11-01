import { MenuTitle } from '@/components/menu-title';
import {
    Network as MenuIcon,
    Users,
    ShoppingCart,
    Coins,
    Trophy,
    ArrowRight,
} from 'lucide-react';
import type { PartnerNetworkData } from '@/components/partner-network-summary';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    StatusBadge,
} from '@/components/data-list';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Errors, Field, inputClass, money } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { apply, network as ownNetwork } from '@/routes/partner';
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
    monthlyRank,
    payoutHistory,
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
        level: number;
        commission_type: string;
        amount: number;
        reversed_amount: number;
        offset_amount: number;
        status: string;
    }[];
    attributedOrders: number;
    network: { name: string; status: string }[];
    networkSummary: PartnerNetworkData | null;
    monthlyRank: {
        period_start: string;
        rank: string;
        personal_sales: number;
        team_sales: number;
        next_rank: string | null;
        personal_target: number | null;
        team_target: number | null;
        personal_progress: number;
        team_progress: number;
    } | null;
    payoutHistory: {
        id: number;
        amount: number;
        status: string;
        requested_at: string;
        paid_at: string | null;
    }[];
}) {
    const form = useForm({ sponsor_code: '', accept_terms: false, gender: '' });
    const { auth } = usePage().props;
    const balanceLabels: Record<string, string> = {
        pending: 'Komisi Menunggu',
        available: 'Komisi Tersedia',
        reserved: 'Sedang Dicairkan',
        paid_out: 'Sudah Dicairkan',
        receivable: 'Penyesuaian Refund',
    };
    return (
        <>
            <Head title="Portal mitra" />
            <main className="partner-dashboard product-workspace">
                <header className="product-heading">
                    <div>
                        <MenuTitle icon={MenuIcon} tone="amber">
                            Dashboard Mitra
                        </MenuTitle>
                        <p>
                            Selamat datang, {auth.user.name}. Pantau penjualan,
                            komisi, dan pertumbuhan jaringan Anda.
                        </p>
                    </div>
                    {monthlyRank && (
                        <span
                            className={`partner-current-rank network-rank rank-${monthlyRank.rank}`}
                        >
                            <Trophy />
                            {monthlyRank.rank}
                        </span>
                    )}
                </header>
                {partner && (
                    <section
                        className="product-kpis"
                        aria-label="Ringkasan mitra"
                    >
                        {[
                            {
                                title: 'Omzet Pribadi',
                                value: money(monthlyRank?.personal_sales ?? 0),
                                icon: ShoppingCart,
                                tone: 'blue',
                                detail: 'Penjualan valid bulan ini',
                            },
                            {
                                title: 'Omzet Tim',
                                value: money(monthlyRank?.team_sales ?? 0),
                                icon: Users,
                                tone: 'green',
                                detail: 'Penjualan jaringan downline',
                            },
                            {
                                title: 'Total Downline',
                                value: Math.max(
                                    0,
                                    (networkSummary?.nodes.length ?? 1) - 1,
                                ).toLocaleString('id-ID'),
                                icon: MenuIcon,
                                tone: 'gold',
                                detail: `${networkSummary?.direct_count ?? 0} mitra langsung`,
                            },
                            {
                                title: 'Komisi Tersedia',
                                value: money(balances?.available ?? 0),
                                icon: Coins,
                                tone: 'pink',
                                detail: 'Lihat kelayakan di menu pencairan',
                            },
                        ].map(({ title, value, icon: Icon, tone, detail }) => (
                            <article
                                key={title}
                                className={`product-kpi ${tone}`}
                            >
                                <span className="product-kpi-icon">
                                    <Icon />
                                </span>
                                <div>
                                    <h2>{title}</h2>
                                    <strong className="partner-money-value">
                                        {value}
                                    </strong>
                                    <p>{detail}</p>
                                </div>
                            </article>
                        ))}
                    </section>
                )}
                {networkSummary && (
                    <section className="honey-panel partner-network-link">
                        <MenuIcon />
                        <div>
                            <h2>Jaringan & Downline Saya</h2>
                            <p>
                                Telusuri struktur, generasi, peringkat, dan
                                perkembangan downline Anda.
                            </p>
                        </div>
                        <Link
                            className="product-primary"
                            href={ownNetwork.url()}
                        >
                            Lihat Jaringan
                            <ArrowRight />
                        </Link>
                    </section>
                )}
                <p>
                    Komisi hanya berasal dari penjualan produk yang valid,
                    maksimal lima generasi. Pendaftaran dan perekrutan tidak
                    menghasilkan bonus.
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
                        <Field label="Jenis kelamin">
                            <select
                                className={inputClass}
                                value={form.data.gender}
                                required
                                onChange={(event) =>
                                    form.setData('gender', event.target.value)
                                }
                            >
                                <option value="">Pilih jenis kelamin</option>
                                <option value="male">Laki-laki</option>
                                <option value="female">Perempuan</option>
                            </select>
                        </Field>
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
                        <section
                            id="referral"
                            className="partner-referral-card grid scroll-mt-24 gap-3 rounded-xl border p-5"
                        >
                            <h2 className="text-xl font-semibold">
                                Status Kemitraan:{' '}
                                <StatusBadge status={partner.status} />
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
                            {monthlyRank && (
                                <section
                                    id="rank-targets"
                                    className="partner-rank-progress grid scroll-mt-24 gap-3 rounded-lg border p-4"
                                >
                                    <h3 className="font-semibold">
                                        Peringkat bulan ini: {monthlyRank.rank}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Periode {monthlyRank.period_start}
                                        {monthlyRank.next_rank
                                            ? ` · Target berikutnya: ${monthlyRank.next_rank}`
                                            : ' · Peringkat tertinggi'}
                                    </p>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p>
                                                Omzet pribadi:{' '}
                                                {money(
                                                    monthlyRank.personal_sales,
                                                )}
                                            </p>
                                            {monthlyRank.personal_target !==
                                                null && (
                                                <progress
                                                    className="w-full"
                                                    max="100"
                                                    value={
                                                        monthlyRank.personal_progress
                                                    }
                                                />
                                            )}
                                        </div>
                                        <div>
                                            <p>
                                                Omzet tim:{' '}
                                                {money(monthlyRank.team_sales)}
                                            </p>
                                            {monthlyRank.team_target !==
                                                null && (
                                                <progress
                                                    className="w-full"
                                                    max="100"
                                                    value={
                                                        monthlyRank.team_progress
                                                    }
                                                />
                                            )}
                                        </div>
                                    </div>
                                </section>
                            )}
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
                                        className="partner-balance-card rounded-xl border p-5"
                                        key={label}
                                    >
                                        <p>{balanceLabels[label] ?? label}</p>
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
                                        header: 'Jenis komisi',
                                        render: (c) =>
                                            c.commission_type === 'direct'
                                                ? 'Direct'
                                                : c.commission_type ===
                                                    'referral'
                                                  ? 'Referral pertama'
                                                  : `Generasi ${c.level}`,
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
                        <section className="grid gap-3">
                            <h2 className="text-xl font-semibold">
                                Riwayat payout terbaru
                            </h2>
                            <DataTable
                                label="Riwayat payout terbaru"
                                rows={payoutHistory}
                                rowKey={(payout) => payout.id}
                                columns={[
                                    {
                                        header: 'Pengajuan',
                                        render: (payout) =>
                                            new Date(
                                                payout.requested_at,
                                            ).toLocaleDateString('id-ID'),
                                    },
                                    {
                                        header: 'Jumlah',
                                        render: (payout) =>
                                            money(payout.amount),
                                    },
                                    {
                                        header: 'Status',
                                        render: (payout) => (
                                            <StatusBadge
                                                status={payout.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Dibayar',
                                        render: (payout) =>
                                            payout.paid_at === null
                                                ? '—'
                                                : new Date(
                                                      payout.paid_at,
                                                  ).toLocaleDateString('id-ID'),
                                    },
                                ]}
                            />
                        </section>
                        <section>
                            <h2 className="text-xl font-semibold">
                                Jaringan sponsor langsung
                            </h2>
                            <p className="my-3 text-sm">
                                Komisi generasi dihitung dari transaksi produk
                                yang valid. Perekrutan atau biaya pendaftaran
                                tidak menghasilkan bonus.
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
