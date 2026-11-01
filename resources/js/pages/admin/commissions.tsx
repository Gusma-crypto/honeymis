import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Coins,
    Wallet,
    Layers3,
    Hourglass,
    Banknote,
    Settings,
    Network,
    ChartNoAxesCombined,
    Calculator,
    ArrowRight,
    ShoppingCart,
    CalendarDays,
    CircleHelp,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { MenuTitle } from '@/components/menu-title';
import {
    RecordDialog,
    DetailFields,
    StatusBadge,
} from '@/components/data-list';
import { money } from '@/components/commerce-ui';
import { index } from '@/routes/admin/commissions';
import { index as rules } from '@/routes/admin/commission-rules';
import { index as payouts } from '@/routes/admin/payouts';
import { show as orderDetails } from '@/routes/orders';
import type { Paginated } from '@/types/commerce';
import type { Auth } from '@/types/auth';

type Entry = {
    id: number;
    order_id: number;
    order_number: string;
    created_at: string;
    type: string;
    level: number;
    base: number;
    amount: number;
    original_amount: number;
    reversed_amount: number;
    status: string;
    source: string | null;
    recipient: string;
};
type Filters = {
    period: string;
    type: string;
    status: string;
    level: string | number;
    base: number;
    first_order: boolean;
};
type History = Paginated<Entry> & {
    total: number;
    from: number | null;
    to: number | null;
};
type Level = {
    level: number;
    rate_bps: number;
    members: number;
    amount: number;
};
const typeLabels: Record<string, string> = {
    direct: 'Penjualan Langsung',
    network: 'Komisi Generasi',
    referral: 'Bonus Referral',
};
const colors: Record<string, string> = {
    direct: '#5087ff',
    network: '#7860ff',
    referral: '#ff879f',
};
function TrendChart({
    trend,
}: {
    trend: { month: string; amount: number; transactions: number }[];
}) {
    const max = Math.max(1, ...trend.map((month) => month.amount));
    const maxTransactions = Math.max(
        1,
        ...trend.map((month) => month.transactions),
    );
    const points = trend
        .map(
            (month, index) =>
                `${45 + index * 51},${155 - (month.transactions / maxTransactions) * 120}`,
        )
        .join(' ');
    return (
        <>
            <svg
                viewBox="0 0 330 195"
                role="img"
                aria-label="Tren komisi dan jumlah transaksi enam bulan"
            >
                <title>Tren komisi enam bulan</title>
                {[0, 1, 2, 3, 4].map((step) => (
                    <g key={step}>
                        <line
                            x1="28"
                            x2="320"
                            y1={155 - step * 30}
                            y2={155 - step * 30}
                            stroke="#e6ecfa"
                        />
                        <text
                            x="24"
                            y={159 - step * 30}
                            textAnchor="end"
                            fontSize="9"
                            fill="#6c789b"
                        >
                            {new Intl.NumberFormat('id-ID', {
                                notation: 'compact',
                                maximumFractionDigits: 1,
                            }).format((max * step) / 4)}
                        </text>
                    </g>
                ))}
                {trend.map((month, index) => (
                    <g key={index}>
                        <rect
                            x={35 + index * 51}
                            y={155 - (month.amount / max) * 120}
                            width="22"
                            height={(month.amount / max) * 120}
                            fill="#58d994"
                            rx="2"
                        >
                            <title>
                                {month.month}: {money(month.amount)}
                            </title>
                        </rect>
                        <text
                            x={46 + index * 51}
                            y="177"
                            textAnchor="middle"
                            fontSize="11"
                            fill="#26365f"
                        >
                            {month.month}
                        </text>
                    </g>
                ))}
                <polyline
                    points={points}
                    fill="none"
                    stroke="#2467ff"
                    strokeWidth="2"
                />
                {trend.map((month, index) => (
                    <circle
                        key={index}
                        cx={45 + index * 51}
                        cy={155 - (month.transactions / maxTransactions) * 120}
                        r="3"
                        fill="#fff"
                        stroke="#2467ff"
                        strokeWidth="2"
                    >
                        <title>{month.transactions} transaksi</title>
                    </circle>
                ))}
            </svg>
            <p className="commission-chart-legend">
                <span>
                    <i style={{ background: '#58d994' }} />
                    Komisi (Rp)
                </span>
                <span>
                    <i style={{ background: '#2467ff' }} />
                    Jumlah Transaksi
                </span>
            </p>
            <small className="commission-chart-note">
                Garis memakai skala jumlah transaksi tersendiri.
            </small>
        </>
    );
}
export default function Commissions({
    filters,
    stats,
    history,
    distribution,
    trend,
    levels,
    simulation,
    minimumPayout,
}: {
    filters: Filters;
    stats: {
        total: number;
        month: number;
        pending: number;
        pending_count: number;
        available: number;
    };
    history: History;
    distribution: { type: string; amount: number }[];
    trend: { month: string; amount: number; transactions: number }[];
    levels: Level[];
    simulation: {
        level: number;
        rate_bps: number;
        amount: number;
        type: string;
    }[];
    minimumPayout: number;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [tab, setTab] = useState('Ringkasan');
    const [base, setBase] = useState(filters.base);
    const [firstOrder, setFirstOrder] = useState(filters.first_order);
    const apply = (changes: Partial<Filters> = {}) => {
        const values = { ...filters, ...changes };
        router.get(
            index.url(),
            { ...values, first_order: values.first_order ? 1 : 0 },
            { preserveState: true, preserveScroll: true },
        );
    };
    let cumulative = 0;
    const donut = distribution
        .map((segment) => {
            const start = cumulative;
            cumulative +=
                stats.month > 0 ? (segment.amount / stats.month) * 100 : 0;
            return `${colors[segment.type] ?? '#d2d9ef'} ${start}% ${cumulative}%`;
        })
        .join(',');
    const cards = [
        {
            label: 'Total Komisi',
            value: stats.total,
            icon: Wallet,
            tone: 'blue',
            note: 'Bersih setelah pengembalian',
        },
        {
            label: 'Komisi Bulan Ini',
            value: stats.month,
            icon: Layers3,
            tone: 'green',
            note: 'Sesuai periode yang dipilih',
        },
        {
            label: 'Komisi Pending',
            value: stats.pending,
            icon: Hourglass,
            tone: 'gold',
            note: `${stats.pending_count} transaksi`,
        },
        {
            label: 'Komisi Siap Cair',
            value: stats.available,
            icon: Banknote,
            tone: 'pink',
            note: 'Saldo layak payout seluruh mitra',
        },
    ];
    const historyPanel = (
        <section className="honey-panel commission-history">
            <header>
                <h2>
                    <Network />
                    Riwayat Komisi
                </h2>
                <div className="commission-filter-bar">
                    <label>
                        <CalendarDays />
                        <input
                            aria-label="Periode komisi"
                            type="month"
                            value={filters.period}
                            onChange={(event) =>
                                apply({ period: event.target.value })
                            }
                        />
                    </label>
                    <select
                        aria-label="Jenis komisi"
                        value={filters.type}
                        onChange={(event) =>
                            apply({ type: event.target.value })
                        }
                    >
                        <option value="">Semua Jenis</option>
                        {Object.entries(typeLabels).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Status komisi"
                        value={filters.status}
                        onChange={(event) =>
                            apply({ status: event.target.value })
                        }
                    >
                        <option value="">Semua Status</option>
                        {Object.entries({
                            pending: 'Menunggu',
                            available: 'Tersedia',
                            reserved: 'Direservasi',
                            paid_out: 'Dicairkan',
                            cancelled: 'Dibatalkan',
                            reversed: 'Dikembalikan',
                        }).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                </div>
            </header>
            <div className="commission-table-scroll">
                <table className="commission-table">
                    <thead>
                        <tr>
                            {[
                                'Tanggal',
                                'Sumber',
                                'Jenis Komisi',
                                'Dari Member',
                                'Nominal',
                                'Status',
                            ].map((title) => (
                                <th key={title}>{title}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {history.data.map((entry) => (
                            <tr key={entry.id}>
                                <td>
                                    {new Date(
                                        entry.created_at.replace(' ', 'T') +
                                            (entry.created_at.includes('Z')
                                                ? ''
                                                : 'Z'),
                                    ).toLocaleString('id-ID', {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        timeZone: 'Asia/Jakarta',
                                    })}
                                </td>
                                <td>
                                    <Link
                                        className="commission-order-link"
                                        href={orderDetails.url(entry.order_id)}
                                    >
                                        <ShoppingCart />
                                        {entry.order_number}
                                    </Link>
                                </td>
                                <td>
                                    <span
                                        className={`commission-type commission-type-${entry.type}`}
                                    >
                                        {entry.type === 'network'
                                            ? `Komisi Level ${entry.level}`
                                            : typeLabels[entry.type]}
                                    </span>
                                </td>
                                <td>
                                    <strong>{entry.source ?? '—'}</strong>
                                    <small>Penerima: {entry.recipient}</small>
                                </td>
                                <td>
                                    <RecordDialog
                                        label={money(entry.amount)}
                                        title={`Detail komisi #${entry.id}`}
                                    >
                                        <DetailFields
                                            rows={[
                                                ['Pesanan', entry.order_number],
                                                ['Penerima', entry.recipient],
                                                [
                                                    'Dasar Komisi',
                                                    money(entry.base),
                                                ],
                                                [
                                                    'Komisi Awal',
                                                    money(
                                                        entry.original_amount,
                                                    ),
                                                ],
                                                [
                                                    'Pengembalian',
                                                    money(
                                                        entry.reversed_amount,
                                                    ),
                                                ],
                                                [
                                                    'Komisi Bersih',
                                                    money(entry.amount),
                                                ],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={entry.status}
                                                    />,
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                </td>
                                <td>
                                    <StatusBadge status={entry.status} />
                                </td>
                            </tr>
                        ))}
                        {history.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="commission-empty">
                                    Belum ada komisi pada filter ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <footer className="product-pagination">
                <p>
                    Menampilkan {history.from ?? 0} – {history.to ?? 0} dari{' '}
                    {history.total} data
                </p>
                <nav aria-label="Halaman komisi">
                    <button
                        disabled={!history.prev_page_url}
                        aria-label="Halaman sebelumnya"
                        onClick={() =>
                            router.get(
                                history.prev_page_url!,
                                {},
                                { preserveState: true, preserveScroll: true },
                            )
                        }
                    >
                        <ChevronLeft />
                    </button>
                    <span className="commission-current-page">
                        {history.current_page}
                    </span>
                    <button
                        disabled={!history.next_page_url}
                        aria-label="Halaman berikutnya"
                        onClick={() =>
                            router.get(
                                history.next_page_url!,
                                {},
                                { preserveState: true, preserveScroll: true },
                            )
                        }
                    >
                        <ChevronRight />
                    </button>
                </nav>
            </footer>
        </section>
    );
    const levelPanel = (
        <section className="honey-panel commission-levels">
            <h2>
                <Network />
                Ringkasan Level Komisi
            </h2>
            <table className="commission-table">
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Tarif</th>
                        <th>Jumlah Member</th>
                        <th>Total Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    {levels.map((level) => (
                        <tr key={level.level}>
                            <td>
                                <button
                                    className={`commission-level level-${level.level}`}
                                    onClick={() => {
                                        setTab('Riwayat Komisi');
                                        apply({ level: level.level });
                                    }}
                                >
                                    Level {level.level}
                                </button>
                            </td>
                            <td>{level.rate_bps / 100}%</td>
                            <td>{level.members}</td>
                            <td>{money(level.amount)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="commission-chart-note">
                Tarif di atas adalah konfigurasi saat ini. Nilai historis tetap
                memakai aturan pada transaksi.
            </p>
        </section>
    );
    return (
        <>
            <Head title="Komisi" />
            <main className="product-workspace commission-workspace">
                <header className="product-heading">
                    <div>
                        <MenuTitle icon={Coins} tone="amber">
                            Komisi
                        </MenuTitle>
                        <p>
                            Kelola komisi, lihat detail per transaksi, dan
                            pantau pencairan komisi mitra.
                        </p>
                    </div>
                    {auth.permissions.includes('commission-rules.manage') && (
                        <Link className="product-secondary" href={rules.url()}>
                            <Settings />
                            Pengaturan Komisi
                        </Link>
                    )}
                </header>
                <nav className="commission-tabs" aria-label="Menu komisi">
                    {[
                        'Ringkasan',
                        'Riwayat Komisi',
                        'Komisi Per Level',
                        'Komisi Tim',
                    ].map((name) => (
                        <button
                            key={name}
                            className={tab === name ? 'selected' : ''}
                            onClick={() => {
                                setTab(name);
                                if (name === 'Komisi Tim')
                                    apply({ type: 'network' });
                                else if (name === 'Riwayat Komisi')
                                    apply({ level: '' });
                            }}
                        >
                            {name}
                        </button>
                    ))}
                    <Link href={payouts.url()}>Pencairan (Payout)</Link>
                </nav>
                <section className="product-kpis">
                    {cards.map(({ label, value, icon: Icon, tone, note }) => (
                        <article className={`product-kpi ${tone}`} key={label}>
                            <span className="product-kpi-icon">
                                <Icon />
                            </span>
                            <div>
                                <h2>{label}</h2>
                                <strong>{money(value)}</strong>
                                <p>{note}</p>
                            </div>
                        </article>
                    ))}
                </section>
                <div className="commission-layout">
                    <div className="commission-left">
                        {tab === 'Komisi Per Level' ? levelPanel : historyPanel}
                        {tab === 'Ringkasan' && (
                            <div className="commission-bottom">
                                <section className="honey-panel commission-trend">
                                    <h2>
                                        <ChartNoAxesCombined />
                                        Tren Komisi 6 Bulan Terakhir
                                    </h2>
                                    <TrendChart trend={trend} />
                                </section>
                                {levelPanel}
                            </div>
                        )}
                        {tab === 'Komisi Tim' && (
                            <p className="commission-chart-note">
                                Menampilkan transaksi komisi jaringan seluruh
                                mitra sesuai filter, bukan bonus perekrutan.
                            </p>
                        )}
                    </div>
                    <aside className="commission-right">
                        <section className="honey-panel commission-distribution">
                            <h2>
                                <Coins />
                                Komisi per Jenis (Periode Ini)
                            </h2>
                            <div className="commission-distribution-body">
                                <div
                                    className="commission-donut"
                                    style={{
                                        background:
                                            stats.month > 0
                                                ? `conic-gradient(${donut})`
                                                : '#e9edf8',
                                    }}
                                >
                                    <div>
                                        <small>Rp</small>
                                        <strong>
                                            {new Intl.NumberFormat(
                                                'id-ID',
                                            ).format(stats.month)}
                                        </strong>
                                    </div>
                                </div>
                                <ul>
                                    {distribution.map((segment) => (
                                        <li key={segment.type}>
                                            <i
                                                style={{
                                                    background:
                                                        colors[segment.type],
                                                }}
                                            />
                                            <span>
                                                {typeLabels[segment.type] ??
                                                    segment.type}
                                            </span>
                                            <strong>
                                                {money(segment.amount)}
                                            </strong>
                                            <b>
                                                {stats.month > 0
                                                    ? Math.round(
                                                          (segment.amount /
                                                              stats.month) *
                                                              100,
                                                      )
                                                    : 0}
                                                %
                                            </b>
                                        </li>
                                    ))}
                                    {distribution.length === 0 && (
                                        <li>
                                            Belum ada komisi pada periode ini.
                                        </li>
                                    )}
                                </ul>
                            </div>
                        </section>
                        <section className="honey-panel commission-simulator">
                            <h2>
                                <Calculator />
                                Simulasi Komisi Transaksi
                                <CircleHelp size={16} />
                            </h2>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    apply({ base, first_order: firstOrder });
                                }}
                            >
                                <label>
                                    Dasar Komisi
                                    <input
                                        aria-label="Dasar komisi rupiah"
                                        type="number"
                                        min={1}
                                        max={1000000000}
                                        required
                                        value={base}
                                        onChange={(event) =>
                                            setBase(Number(event.target.value))
                                        }
                                    />
                                </label>
                                <button
                                    className="product-primary"
                                    type="submit"
                                >
                                    Hitung
                                </button>
                                <label className="commission-first-order">
                                    <input
                                        type="checkbox"
                                        checked={firstOrder}
                                        onChange={(event) =>
                                            setFirstOrder(event.target.checked)
                                        }
                                    />
                                    Transaksi pertama pelanggan
                                </label>
                            </form>
                            <div className="commission-simulation-levels">
                                {simulation.map((level) => (
                                    <article key={level.level}>
                                        <strong>L{level.level}</strong>
                                        <b>{level.rate_bps / 100}%</b>
                                        <small>{money(level.amount)}</small>
                                    </article>
                                ))}
                            </div>
                            <p className="commission-potential">
                                <Coins />
                                Total Potensi Komisi Jaringan:{' '}
                                {money(
                                    simulation.reduce(
                                        (sum, level) => sum + level.amount,
                                        0,
                                    ),
                                )}
                            </p>
                            <p className="commission-chart-note">
                                Ilustrasi untuk lima penerima yang memenuhi
                                syarat. Komisi aktual dibatasi margin produk,
                                anggaran, dan status transaksi. Referral pertama
                                menggantikan tarif L1; tidak ditumpuk. Simulasi
                                tidak mencatat saldo.
                            </p>
                        </section>
                        <section className="honey-panel commission-payout">
                            <h2>
                                <Banknote />
                                Pencairan Komisi
                            </h2>
                            <dl>
                                <dt>Saldo Siap Cair Jaringan</dt>
                                <dd>{money(stats.available)}</dd>
                                <dt>Minimal Pencairan</dt>
                                <dd>{money(minimumPayout)}</dd>
                                <dt>Rekening Tujuan</dt>
                                <dd>Diperiksa per pengajuan</dd>
                            </dl>
                            <div>
                                <Link
                                    className="product-primary"
                                    href={payouts.url()}
                                >
                                    <ArrowRight />
                                    Kelola Pencairan
                                </Link>
                                <Link
                                    className="product-secondary"
                                    href={payouts.url({
                                        query: { status: 'paid' },
                                    })}
                                >
                                    Riwayat Pencairan
                                </Link>
                            </div>
                            <p className="commission-chart-note">
                                Pembayaran ditandai selesai setelah transfer
                                dikonfirmasi.
                            </p>
                        </section>
                    </aside>
                </div>
            </main>
        </>
    );
}
