import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ChartNoAxesCombined,
    ShoppingCart,
    Coins,
    Users,
    Network,
    Download,
    Trophy,
    FileText,
    Package,
    Wallet,
    ChevronRight,
    ArrowUp,
    ArrowDown,
    CalendarDays,
    Activity,
    SlidersHorizontal,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { MemberAvatar } from '@/components/member-avatar';
import { DataTable, StatusBadge } from '@/components/data-list';
import { money, Field, inputClass, Errors } from '@/components/commerce-ui';
import { index, exportMethod } from '@/routes/admin/reports';

type Tab =
    | 'summary'
    | 'orders'
    | 'commissions'
    | 'network'
    | 'members'
    | 'products'
    | 'payouts'
    | 'activity';
type Filters = {
    period: string;
    from: string;
    until: string;
    tab: Tab;
    trend: '6' | '12' | 'year';
    top_period: 'selected' | 'previous' | 'year';
};
type Point = {
    period: string;
    label: string;
    sales: number;
    commissions?: number;
    orders: number;
};
type Product = { id: number; name: string; sales: number; quantity: number };
type Performer = {
    id: number;
    name: string;
    code: string;
    gender: string | null;
    avatar: string | null;
    rank: string;
    personal_sales: number;
    team_sales: number;
    commissions?: number;
};
type Row = Record<string, string | number | null>;
type Details = {
    data: Row[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Workspace = {
    stats: {
        sales: number;
        commissions?: number;
        members: number;
        downline: number;
    };
    changes: Record<string, number | null>;
    trend: Point[];
    distribution: Product[];
    performers: Performer[];
    details: Details | null;
};
type Report = {
    orders: number;
    paidOrders: number;
    pendingOrders: number;
    gross: number;
    refunds: number;
    net: number;
    finance?: {
        estimatedMargin: number;
        payoutPaid: number;
        payoutReserved: number;
    };
};
const tabs: { key: Tab; label: string; icon: LucideIcon }[] = [
    { key: 'summary', label: 'Ringkasan', icon: ChartNoAxesCombined },
    { key: 'orders', label: 'Penjualan', icon: ShoppingCart },
    { key: 'commissions', label: 'Komisi', icon: Coins },
    { key: 'network', label: 'Jaringan MLM', icon: Network },
    { key: 'members', label: 'Member', icon: Users },
    { key: 'products', label: 'Produk', icon: Package },
    { key: 'payouts', label: 'Payout', icon: Wallet },
    { key: 'activity', label: 'Aktivitas', icon: Activity },
];
const colors = [
    '#8757ff',
    '#ff598a',
    '#ffca34',
    '#44df52',
    '#67b8ff',
    '#c5cad5',
];
const count = (value: number) => new Intl.NumberFormat('id-ID').format(value);
const compact = (value: number) =>
    value >= 1_000_000
        ? `${Math.round(value / 100_000) / 10} jt`
        : value >= 1000
          ? `${Math.round(value / 100) / 10} rb`
          : count(value);

function TrendChart({
    points,
    financialAccess,
}: {
    points: Point[];
    financialAccess: boolean;
}) {
    const [visible, setVisible] = useState({
        sales: true,
        commissions: true,
        orders: true,
    });
    const maximum = Math.max(
        1,
        ...points.flatMap((p) => [
            visible.sales ? p.sales : 0,
            visible.commissions ? (p.commissions ?? 0) : 0,
        ]),
    );
    const upper = maximum > 1 ? Math.ceil(maximum / 5) * 5 : 5;
    const orderMaximum = Math.max(5, ...points.map((p) => p.orders));
    const group = 530 / Math.max(1, points.length);
    const bar = Math.min(38, group * 0.37);
    const y = (value: number) => 220 - (value / upper) * 190;
    return (
        <>
            <svg
                className="reports-trend-svg"
                viewBox="0 0 650 270"
                role="img"
                aria-label="Grafik penjualan dan komisi dalam Rupiah serta jumlah pesanan pada sumbu kanan"
            >
                <defs>
                    <linearGradient
                        id="report-sales"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stopColor="#8aceff" />
                        <stop offset="100%" stopColor="#32a3fa" />
                    </linearGradient>
                    <linearGradient
                        id="report-commissions"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stopColor="#87ee79" />
                        <stop offset="100%" stopColor="#24d434" />
                    </linearGradient>
                </defs>
                {[0, 1, 2, 3, 4, 5].map((step) => (
                    <g key={step}>
                        <line
                            x1="62"
                            x2="592"
                            y1={220 - step * 38}
                            y2={220 - step * 38}
                            stroke="#e0e7fa"
                        />
                        <text
                            x="53"
                            y={225 - step * 38}
                            textAnchor="end"
                            fill="#304164"
                            fontSize="12"
                        >
                            {compact((upper * step) / 5)}
                        </text>
                        <text
                            x="605"
                            y={225 - step * 38}
                            fill="#67789d"
                            fontSize="11"
                        >
                            {Math.round((orderMaximum * step) / 5)}
                        </text>
                    </g>
                ))}
                <text x="62" y="15" fill="#67789d" fontSize="10">
                    Rupiah
                </text>
                <text x="585" y="15" fill="#67789d" fontSize="10">
                    Pesanan
                </text>
                {points.map((point, i) => (
                    <g key={point.period}>
                        <text
                            x={62 + group * (i + 0.5)}
                            y="241"
                            textAnchor="middle"
                            fontSize="12"
                        >
                            {point.label}
                        </text>
                        {visible.sales && (
                            <rect
                                x={62 + group * (i + 0.5) - bar}
                                y={y(Math.max(0, point.sales))}
                                width={bar}
                                height={220 - y(Math.max(0, point.sales))}
                                rx="4"
                                fill="url(#report-sales)"
                            >
                                <title>
                                    {point.label}: penjualan{' '}
                                    {money(point.sales)}
                                </title>
                            </rect>
                        )}
                        {financialAccess && visible.commissions && (
                            <rect
                                x={62 + group * (i + 0.5)}
                                y={y(Math.max(0, point.commissions ?? 0))}
                                width={bar}
                                height={
                                    220 - y(Math.max(0, point.commissions ?? 0))
                                }
                                rx="4"
                                fill="url(#report-commissions)"
                            >
                                <title>
                                    {point.label}: komisi{' '}
                                    {money(point.commissions ?? 0)}
                                </title>
                            </rect>
                        )}
                    </g>
                ))}
                {visible.orders && (
                    <>
                        <polyline
                            points={points
                                .map(
                                    (p, i) =>
                                        `${62 + group * (i + 0.5)},${220 - (p.orders / orderMaximum) * 190}`,
                                )
                                .join(' ')}
                            fill="none"
                            stroke="#0756ff"
                            strokeWidth="2.5"
                        />
                        {points.map((point, i) => (
                            <circle
                                key={point.period}
                                cx={62 + group * (i + 0.5)}
                                cy={220 - (point.orders / orderMaximum) * 190}
                                r="5"
                                fill="white"
                                stroke="#0756ff"
                                strokeWidth="2"
                            >
                                <title>
                                    {point.label}: {point.orders} pesanan
                                </title>
                            </circle>
                        ))}
                    </>
                )}
            </svg>
            <div className="reports-chart-legend">
                {(
                    [
                        {
                            key: 'sales',
                            label: 'Penjualan (Rp)',
                            color: '#4db4ff',
                        },
                        ...(financialAccess
                            ? [
                                  {
                                      key: 'commissions',
                                      label: 'Komisi (Rp)',
                                      color: '#3de34a',
                                  },
                              ]
                            : []),
                        {
                            key: 'orders',
                            label: 'Jumlah Pesanan',
                            color: '#0756ff',
                        },
                    ] as {
                        key: keyof typeof visible;
                        label: string;
                        color: string;
                    }[]
                ).map((item) => (
                    <button
                        type="button"
                        key={item.key}
                        aria-pressed={visible[item.key]}
                        onClick={() =>
                            setVisible({
                                ...visible,
                                [item.key]: !visible[item.key],
                            })
                        }
                        className={visible[item.key] ? '' : 'is-muted'}
                    >
                        <i style={{ background: item.color }} />
                        {item.label}
                    </button>
                ))}
            </div>
        </>
    );
}
function ProductDistribution({ products }: { products: Product[] }) {
    const total = products.reduce((sum, p) => sum + Math.max(0, p.sales), 0);
    let offset = 0;
    const segments = products.map((p, i) => {
        const start = offset;
        offset += total ? (Math.max(0, p.sales) / total) * 100 : 0;
        return `${colors[i % colors.length]} ${start}% ${offset}%`;
    });
    return (
        <div className="reports-product-distribution">
            <div
                className="reports-donut"
                role="img"
                aria-label={`Distribusi penjualan produk, total ${money(total)}`}
                style={{
                    background: total
                        ? `conic-gradient(${segments.join(',')})`
                        : '#edf0f7',
                }}
            >
                <div>
                    <span>Rp</span>
                    <strong>{count(total)}</strong>
                </div>
            </div>
            <div className="reports-product-legend">
                {products.length ? (
                    products.map((product, i) => (
                        <div key={product.id}>
                            <i
                                style={{
                                    background: colors[i % colors.length],
                                }}
                            />
                            <span title={product.name}>{product.name}</span>
                            <strong>
                                {total
                                    ? (
                                          (product.sales / total) *
                                          100
                                      ).toLocaleString('id-ID', {
                                          maximumFractionDigits: 1,
                                      })
                                    : 0}
                                %
                            </strong>
                            <b>{money(product.sales)}</b>
                        </div>
                    ))
                ) : (
                    <p className="reports-empty">
                        Belum ada penjualan produk pada periode ini.
                    </p>
                )}
            </div>
        </div>
    );
}
const columns: Record<Exclude<Tab, 'summary'>, [string, string][]> = {
    orders: [
        ['order_number', 'Nomor Pesanan'],
        ['status', 'Status'],
        ['grand_total', 'Total Penjualan'],
        ['placed_at', 'Tanggal'],
    ],
    commissions: [
        ['name', 'Nama Member'],
        ['commission_type', 'Jenis'],
        ['level', 'Level'],
        ['amount', 'Komisi'],
        ['reversed_amount', 'Pembalikan'],
        ['status', 'Status'],
    ],
    network: [
        ['name', 'Nama Member'],
        ['referral_code', 'Kode Referral'],
        ['sponsor_partner_id', 'ID Sponsor'],
        ['status', 'Status'],
        ['created_at', 'Bergabung'],
    ],
    members: [
        ['name', 'Nama Member'],
        ['referral_code', 'Kode Referral'],
        ['status', 'Status'],
        ['created_at', 'Bergabung'],
    ],
    products: [
        ['name', 'Produk'],
        ['quantity', 'Unit Terjual'],
        ['sales', 'Penjualan Produk Net'],
    ],
    payouts: [
        ['id', 'ID Payout'],
        ['partner_id', 'ID Mitra'],
        ['amount', 'Jumlah'],
        ['status', 'Status'],
        ['requested_at', 'Tanggal Pengajuan'],
    ],
    activity: [
        ['name', 'Pelaku'],
        ['action', 'Aktivitas'],
        ['subject_type', 'Objek'],
        ['subject_id', 'ID Objek'],
        ['created_at', 'Waktu'],
    ],
};
const date = (value: string) =>
    new Date(
        /(?:Z|[+-]\d{2}:\d{2})$/.test(value)
            ? value.replace(' ', 'T')
            : `${value.replace(' ', 'T')}Z`,
    ).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        dateStyle: 'medium',
        timeStyle: 'short',
    });

export default function Reports({
    workspace: w,
    filters,
    report,
    financialAccess,
    auditAccess,
}: {
    workspace: Workspace;
    filters: Filters;
    report: Report;
    financialAccess: boolean;
    auditAccess: boolean;
}) {
    const [rangeOpen, setRangeOpen] = useState(false);
    const range = useForm({ from: filters.from, until: filters.until });
    const visit = (changes: Record<string, string | number>) =>
        router.get(
            index.url(),
            { ...filters, ...changes, page: changes.page ?? 1 },
            { preserveState: true, preserveScroll: true },
        );
    const permitted = (key: Tab) =>
        (!['commissions', 'payouts'].includes(key) || financialAccess) &&
        (key !== 'activity' || auditAccess);
    const cards: {
        key: keyof Workspace['stats'];
        label: string;
        icon: LucideIcon;
        tone: string;
        monetary?: boolean;
    }[] = [
        {
            key: 'sales',
            label: 'Total Penjualan',
            icon: ShoppingCart,
            tone: 'blue',
            monetary: true,
        },
        ...(financialAccess
            ? [
                  {
                      key: 'commissions' as const,
                      label: 'Total Komisi',
                      icon: Coins,
                      tone: 'green',
                      monetary: true,
                  },
              ]
            : []),
        { key: 'members', label: 'Member Aktif', icon: Users, tone: 'amber' },
        {
            key: 'downline',
            label: 'Total Downline',
            icon: Network,
            tone: 'pink',
        },
    ];
    return (
        <main className="reports-page">
            <Head title="Laporan" />
            <header className="reports-heading">
                <div className="reports-heading-title">
                    <ChartNoAxesCombined size={46} />
                    <div>
                        <h1>Laporan</h1>
                        <p>
                            Analisis lengkap penjualan, komisi, jaringan,
                            member, dan performa bisnis MLM.
                        </p>
                    </div>
                </div>
                <div className="reports-heading-actions">
                    <label>
                        <span>Periode Laporan</span>
                        <div>
                            <CalendarDays size={18} />
                            <input
                                type="month"
                                aria-label="Periode laporan"
                                value={filters.period}
                                onChange={(event) => {
                                    if (event.target.value)
                                        visit({
                                            period: event.target.value,
                                            from: '',
                                            until: '',
                                        });
                                }}
                            />
                        </div>
                    </label>
                    <a
                        className="reports-export"
                        href={exportMethod.url({
                            query: {
                                type: filters.tab,
                                from: filters.from,
                                until: filters.until,
                            },
                        })}
                    >
                        <Download size={19} />
                        Export Laporan
                    </a>
                </div>
            </header>
            <nav className="reports-tabs" aria-label="Jenis laporan">
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        disabled={!permitted(tab.key)}
                        title={
                            !permitted(tab.key)
                                ? 'Memerlukan izin tambahan'
                                : tab.label
                        }
                        aria-pressed={filters.tab === tab.key}
                        className={filters.tab === tab.key ? 'active' : ''}
                        onClick={() => visit({ tab: tab.key })}
                    >
                        {tab.label}
                    </button>
                ))}
                <button
                    type="button"
                    className="reports-range-button"
                    aria-label="Pilih rentang tanggal khusus"
                    aria-expanded={rangeOpen}
                    onClick={() => setRangeOpen(!rangeOpen)}
                >
                    <SlidersHorizontal size={17} />
                </button>
            </nav>
            {rangeOpen && (
                <form
                    className="reports-custom-range"
                    onSubmit={(event) => {
                        event.preventDefault();
                        range.get(
                            index.url({
                                query: {
                                    tab: filters.tab,
                                    trend: filters.trend,
                                    top_period: filters.top_period,
                                },
                            }),
                            { preserveState: true },
                        );
                    }}
                >
                    <Field label="Dari (WIB)">
                        <input
                            className={inputClass}
                            type="date"
                            value={range.data.from}
                            onChange={(e) =>
                                range.setData('from', e.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field label="Sampai (WIB)">
                        <input
                            className={inputClass}
                            type="date"
                            value={range.data.until}
                            onChange={(e) =>
                                range.setData('until', e.target.value)
                            }
                            required
                        />
                    </Field>
                    <button
                        type="submit"
                        className="reports-export"
                        disabled={range.processing}
                    >
                        Terapkan
                    </button>
                    <Errors errors={range.errors} />
                </form>
            )}
            <section
                className="reports-stats"
                style={{
                    gridTemplateColumns: `repeat(${cards.length},minmax(0,1fr))`,
                }}
            >
                {cards.map(({ key, label, icon: Icon, tone, monetary }) => {
                    const change = w.changes[key];
                    return (
                        <article className={`reports-stat ${tone}`} key={key}>
                            <span className="reports-stat-icon">
                                <Icon size={34} />
                            </span>
                            <div>
                                <h2>{label}</h2>
                                <strong>
                                    {monetary
                                        ? money(w.stats[key] ?? 0)
                                        : count(w.stats[key] ?? 0)}
                                </strong>
                                <p>
                                    <span
                                        className={`reports-growth ${change !== null && change < 0 ? 'negative' : ''}`}
                                    >
                                        {change !== null && change < 0 ? (
                                            <ArrowDown size={12} />
                                        ) : (
                                            <ArrowUp size={12} />
                                        )}
                                        {change === null
                                            ? 'Baru'
                                            : `${change > 0 ? '+' : ''}${change}%`}
                                    </span>{' '}
                                    dari periode lalu
                                </p>
                            </div>
                        </article>
                    );
                })}
            </section>
            {filters.tab === 'summary' ? (
                <>
                    <section className="reports-charts">
                        <article className="reports-panel">
                            <header className="reports-panel-heading">
                                <h2>
                                    <ChartNoAxesCombined />
                                    Tren Penjualan & Komisi
                                </h2>
                                <div className="reports-segments">
                                    {[
                                        ['6', '6 Bulan'],
                                        ['12', '12 Bulan'],
                                        ['year', 'Tahun Ini'],
                                    ].map(([value, label]) => (
                                        <button
                                            type="button"
                                            key={value}
                                            className={
                                                filters.trend === value
                                                    ? 'active'
                                                    : ''
                                            }
                                            aria-pressed={
                                                filters.trend === value
                                            }
                                            onClick={() =>
                                                visit({ trend: value })
                                            }
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </header>
                            <TrendChart
                                points={w.trend}
                                financialAccess={financialAccess}
                            />
                        </article>
                        <article className="reports-panel">
                            <header className="reports-panel-heading">
                                <h2>
                                    <Network />
                                    Distribusi Penjualan Produk
                                </h2>
                            </header>
                            <ProductDistribution products={w.distribution} />
                        </article>
                    </section>
                    <section className="reports-bottom">
                        <article className="reports-panel reports-quick">
                            <header className="reports-panel-heading">
                                <h2>
                                    <FileText />
                                    Laporan Cepat
                                </h2>
                            </header>
                            {(
                                [
                                    {
                                        key: 'orders',
                                        title: 'Laporan Penjualan',
                                        description:
                                            'Detail penjualan per periode dan pesanan',
                                        icon: ShoppingCart,
                                        tone: 'blue',
                                    },
                                    {
                                        key: 'commissions',
                                        title: 'Laporan Komisi',
                                        description:
                                            'Rekap komisi member per level dan per periode',
                                        icon: Coins,
                                        tone: 'amber',
                                    },
                                    {
                                        key: 'network',
                                        title: 'Laporan Jaringan',
                                        description:
                                            'Pertumbuhan jaringan dan hubungan sponsor',
                                        icon: Network,
                                        tone: 'violet',
                                    },
                                    {
                                        key: 'members',
                                        title: 'Laporan Member',
                                        description:
                                            'Data member, status, dan tanggal keanggotaan',
                                        icon: Users,
                                        tone: 'green',
                                    },
                                    {
                                        key: 'products',
                                        title: 'Laporan Produk',
                                        description:
                                            'Performa penjualan dan produk terlaris',
                                        icon: Package,
                                        tone: 'violet',
                                    },
                                    {
                                        key: 'payouts',
                                        title: 'Laporan Payout',
                                        description:
                                            'Rekap pengajuan dan status pencairan komisi',
                                        icon: FileText,
                                        tone: 'pink',
                                    },
                                ] as {
                                    key: Tab;
                                    title: string;
                                    description: string;
                                    icon: LucideIcon;
                                    tone: string;
                                }[]
                            ).map(
                                ({
                                    key,
                                    title,
                                    description,
                                    icon: Icon,
                                    tone,
                                }) => (
                                    <button
                                        type="button"
                                        key={key}
                                        disabled={!permitted(key)}
                                        onClick={() => visit({ tab: key })}
                                    >
                                        <span
                                            className={`reports-quick-icon ${tone}`}
                                        >
                                            <Icon size={25} />
                                        </span>
                                        <span>
                                            <strong>{title}</strong>
                                            <small>{description}</small>
                                        </span>
                                        <ChevronRight size={18} />
                                    </button>
                                ),
                            )}
                        </article>
                        <article className="reports-panel reports-performers">
                            <header className="reports-panel-heading">
                                <h2>
                                    <Trophy />
                                    Top Performer Member{' '}
                                    <span>(Berdasarkan Omzet Tim)</span>
                                </h2>
                                <select
                                    aria-label="Periode top performer"
                                    value={filters.top_period}
                                    onChange={(event) =>
                                        visit({
                                            top_period: event.target.value,
                                        })
                                    }
                                >
                                    <option value="selected">
                                        Periode Ini
                                    </option>
                                    <option value="previous">
                                        Bulan Sebelumnya
                                    </option>
                                    <option value="year">Tahun Berjalan</option>
                                </select>
                            </header>
                            <div className="reports-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama Member</th>
                                            <th>Rank</th>
                                            <th>Omzet Pribadi</th>
                                            <th>Omzet Tim</th>
                                            {financialAccess && (
                                                <th>Total Komisi</th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {w.performers.map((member, i) => (
                                            <tr key={member.id}>
                                                <td>
                                                    <span
                                                        className={`reports-medal medal-${i + 1}`}
                                                    >
                                                        {i + 1}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="reports-member">
                                                        <MemberAvatar
                                                            name={member.name}
                                                            gender={
                                                                member.gender
                                                            }
                                                            photo={
                                                                member.avatar
                                                            }
                                                        />
                                                        <strong>
                                                            {member.name}
                                                        </strong>
                                                        <small>
                                                            ({member.code})
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        className={`reports-rank rank-${member.rank.toLowerCase()}`}
                                                    >
                                                        {member.rank}
                                                    </span>
                                                </td>
                                                <td>
                                                    {money(
                                                        member.personal_sales,
                                                    )}
                                                </td>
                                                <td>
                                                    {money(member.team_sales)}
                                                </td>
                                                {financialAccess && (
                                                    <td>
                                                        <b>
                                                            {money(
                                                                member.commissions ??
                                                                    0,
                                                            )}
                                                        </b>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                        {!w.performers.length && (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        financialAccess ? 6 : 5
                                                    }
                                                    className="reports-empty"
                                                >
                                                    Belum ada omzet mitra pada
                                                    periode ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                </>
            ) : (
                <section className="reports-panel reports-details">
                    <header className="reports-panel-heading">
                        <h2>
                            {tabs.find((t) => t.key === filters.tab)?.label}
                        </h2>
                        <span>
                            {w.details?.total ?? 0} data • {filters.from} —{' '}
                            {filters.until} WIB
                        </span>
                    </header>
                    {w.details && (
                        <>
                            <DataTable
                                label={`Laporan ${filters.tab}`}
                                rows={w.details.data}
                                rowKey={(row) => String(row.id)}
                                columns={columns[filters.tab].map(
                                    ([key, label]) => ({
                                        header: label,
                                        render: (row: Row) =>
                                            key === 'status' ? (
                                                <StatusBadge
                                                    status={String(row[key])}
                                                />
                                            ) : [
                                                  'sales',
                                                  'grand_total',
                                                  'amount',
                                                  'reversed_amount',
                                              ].includes(key) ? (
                                                money(Number(row[key] ?? 0))
                                            ) : [
                                                  'created_at',
                                                  'placed_at',
                                                  'requested_at',
                                              ].includes(key) && row[key] ? (
                                                date(String(row[key]))
                                            ) : (
                                                String(row[key] ?? '—')
                                            ),
                                    }),
                                )}
                            />
                            <footer className="reports-pagination">
                                <span>
                                    Halaman {w.details.current_page} dari{' '}
                                    {w.details.last_page}
                                </span>
                                {w.details.prev_page_url && (
                                    <Link
                                        href={w.details.prev_page_url}
                                        preserveState
                                        preserveScroll
                                    >
                                        Sebelumnya
                                    </Link>
                                )}
                                {w.details.next_page_url && (
                                    <Link
                                        href={w.details.next_page_url}
                                        preserveState
                                        preserveScroll
                                    >
                                        Berikutnya
                                    </Link>
                                )}
                            </footer>
                        </>
                    )}
                </section>
            )}
            <p className="reports-methodology">
                Penjualan net mengikuti tanggal pesanan (WIB), pembayaran
                terverifikasi, dan refund; pesanan ditahan tidak dihitung.
                Grafik produk dan omzet mitra tidak termasuk ongkir/pajak.
                Status member mengikuti status saat ini, dengan tanggal
                bergabung sebelum akhir periode. Komisi/payout pada tab rincian
                mengikuti tanggal pencatatannya.
            </p>
            <details className="reports-reconciliation" id="reports-guide">
                <summary>Ringkasan transaksi & rekonsiliasi</summary>
                <div>
                    <span>Pesanan: {count(report.orders)}</span>
                    <span>Dibayar: {count(report.paidOrders)}</span>
                    <span>Menunggu: {count(report.pendingOrders)}</span>
                    <span>Refund: {money(report.refunds)}</span>
                    {report.finance && (
                        <>
                            <span>
                                Estimasi margin:{' '}
                                {money(report.finance.estimatedMargin)}
                            </span>
                            <a
                                href={exportMethod.url({
                                    query: {
                                        type: 'wallet',
                                        from: filters.from,
                                        until: filters.until,
                                    },
                                })}
                            >
                                Unduh ledger CSV
                            </a>
                        </>
                    )}
                </div>
            </details>
        </main>
    );
}
