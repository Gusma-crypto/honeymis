import { MemberAvatar } from '@/components/member-avatar';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowUp,
    CalendarDays,
    ChartNoAxesCombined,
    Coins,
    Network,
    Package,
    ShoppingCart,
    Trophy,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { money } from '@/components/commerce-ui';
import type { PartnerNetworkData } from '@/components/partner-network-summary';
import { index as catalog } from '@/routes/admin/catalog';
import { index as orders } from '@/routes/admin/orders';
import { index as partners } from '@/routes/admin/partners';

type Period = {
    period: string;
    label: string;
    sales: number;
    orders: number;
    members: number;
    commissions: number;
};
export type AdminOverview = {
    orders?: {
        counts: Record<string, number>;
        reviewPayments: number;
        recent: {
            id: number;
            number: string;
            customer?: string;
            product?: string;
            status: string;
            total: number;
            placedAt: string;
        }[];
    };
    members?: {
        total: number;
        recent: {
            id: number;
            name: string;
            status: string;
            joinedAt: string | null;
            sponsorId: number | null;
        }[];
    };
    analytics?: {
        year: number;
        series: Period[];
        topProducts: {
            id: number;
            name: string;
            sold: number;
            price: number;
        }[];
        commissionBreakdown: Record<string, number>;
    };
    payouts?: { requested: number; approved: number; reserved: number };
    pendingPartners?: number;
    refundRequests?: number;
};
const number = (value: number) => value.toLocaleString('id-ID');
const date = (value: string) =>
    new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    });
const labels: Record<string, string> = {
    paid: 'Dibayar',
    processing: 'Diproses',
    shipped: 'Dikirim',
    delivered: 'Diterima',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    refunded: 'Refund',
    pending_payment: 'Menunggu',
};
function Face({
    name,
    small = false,
    gender,
    photo,
}: {
    name: string;
    small?: boolean;
    gender?: string | null;
    photo?: string | null;
}) {
    return (
        <span className={`honey-face ${small ? 'honey-face-small' : ''}`}>
            <MemberAvatar name={name} gender={gender} photo={photo} />
        </span>
    );
}
function PanelTitle({
    icon: Icon,
    title,
    href,
    warm = false,
}: {
    icon: LucideIcon;
    title: string;
    href?: string;
    warm?: boolean;
}) {
    return (
        <header className="honey-panel-title">
            <h2>
                <Icon
                    className={warm ? 'honey-orange' : 'honey-blue'}
                    size={21}
                />
                {title}
            </h2>
            {href && <Link href={href}>Lihat Semua</Link>}
        </header>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="honey-empty">
            <Package size={26} strokeWidth={1.3} />
            <span>{text}</span>
        </div>
    );
}
function SalesChart({
    series,
    metric,
}: {
    series: Period[];
    metric: 'sales' | 'members' | 'commissions';
}) {
    const max = Math.max(1, ...series.map((item) => item[metric]));
    const maxMembers = Math.max(1, ...series.map((item) => item.members));
    const x = (index: number) =>
        52 + index * (550 / Math.max(1, series.length - 1));
    const y = (value: number) => 206 - (value / max) * 160;
    const points = series
        .map((item, index) => `${x(index)},${y(item[metric])}`)
        .join(' ');
    const compact = (value: number) =>
        metric === 'members'
            ? number(Math.round(value))
            : value >= 1000000
              ? `${Number((value / 1000000).toFixed(1))} jt`
              : value >= 1000
                ? `${Number((value / 1000).toFixed(1))} rb`
                : number(Math.round(value));
    return (
        <div className="honey-chart">
            <svg
                viewBox="0 0 640 254"
                role="img"
                aria-label={`Grafik ${metric === 'sales' ? 'penjualan produk' : metric === 'members' ? 'member baru' : 'komisi'} bulanan`}
            >
                <defs>
                    <linearGradient id="sales-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="#2865ff"
                            stopOpacity=".17"
                        />
                        <stop
                            offset="100%"
                            stopColor="#2865ff"
                            stopOpacity=".015"
                        />
                    </linearGradient>
                </defs>
                {[0, 1, 2, 3, 4].map((tick) => (
                    <g key={tick}>
                        <line
                            x1="42"
                            x2="620"
                            y1={206 - tick * 40}
                            y2={206 - tick * 40}
                            stroke="#e6ebf4"
                        />
                        <text
                            x="30"
                            y={210 - tick * 40}
                            textAnchor="end"
                            fill="#77829e"
                            fontSize="11"
                        >
                            {compact((max * tick) / 4)}
                        </text>
                    </g>
                ))}
                {series.map((item, index) => (
                    <g key={item.period}>
                        <line
                            x1={x(index)}
                            x2={x(index)}
                            y1="46"
                            y2="206"
                            stroke="#eef1f7"
                        />
                        <rect
                            x={x(index) - 9}
                            y={206 - (item.members / maxMembers) * 90}
                            width="18"
                            height={(item.members / maxMembers) * 90}
                            fill="#6bd98f"
                            opacity=".92"
                        />
                        <text
                            x={x(index)}
                            y="232"
                            textAnchor="middle"
                            fill="#727c96"
                            fontSize="11"
                        >
                            {item.label}
                        </text>
                    </g>
                ))}
                {series.length > 0 && (
                    <>
                        <polygon
                            points={`52,206 ${points} ${x(series.length - 1)},206`}
                            fill="url(#sales-fill)"
                        />
                        <polyline
                            points={points}
                            stroke="#2861ff"
                            strokeWidth="2.3"
                            fill="none"
                        />
                        {series.map((item, index) => (
                            <circle
                                key={item.period}
                                cx={x(index)}
                                cy={y(item[metric])}
                                r="3.4"
                                fill="#2861ff"
                            >
                                <title>
                                    {item.label}:{' '}
                                    {metric === 'members'
                                        ? number(item[metric])
                                        : money(item[metric])}
                                </title>
                            </circle>
                        ))}
                    </>
                )}
            </svg>
            <div className="honey-chart-key">
                <span>
                    <i />
                    {metric === 'sales'
                        ? 'Penjualan'
                        : metric === 'members'
                          ? 'Member Baru'
                          : 'Komisi'}
                </span>
                <span>
                    <i className="green" />
                    Member Baru (skala relatif)
                </span>
            </div>
            {series.every((item) => item[metric] === 0) && (
                <p className="honey-chart-empty">
                    Belum ada data pada periode ini
                </p>
            )}
        </div>
    );
}
export function HoneyAdminDashboard({
    admin,
    network,
}: {
    admin: AdminOverview;
    network: PartnerNetworkData | null;
}) {
    const { auth } = usePage().props;
    const [metric, setMetric] = useState<'sales' | 'members' | 'commissions'>(
        'sales',
    );
    const currentMonth = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        timeZone: 'Asia/Jakarta',
    })
        .format(new Date())
        .replace('/', '-');
    const series = admin.analytics?.series ?? [];
    const [selectedPeriod, setSelectedPeriod] = useState(
        series.find((item) => item.period === currentMonth)?.period ??
            series.at(-1)?.period ??
            '',
    );
    const selectedIndex = series.findIndex(
        (item) => item.period === selectedPeriod,
    );
    const selected = series[selectedIndex];
    const previous = series[selectedIndex - 1];
    const can = (permission: string) => auth.permissions.includes(permission);
    const breakdown = admin.analytics?.commissionBreakdown ?? {};
    const commissionTotal = Object.values(breakdown).reduce(
        (sum, amount) => sum + amount,
        0,
    );
    const cards = [
        {
            label: 'Total Member',
            value: admin.members ? number(admin.members.total) : '—',
            current: selected?.members,
            previous: previous?.members,
            icon: Users,
            color: 'blue',
        },
        {
            label: 'Total Pesanan',
            value: selected
                ? number(selected.orders)
                : admin.orders
                  ? number(
                        Object.values(admin.orders.counts).reduce(
                            (sum, count) => sum + count,
                            0,
                        ),
                    )
                  : '—',
            current: selected?.orders,
            previous: previous?.orders,
            icon: ShoppingCart,
            color: 'green',
        },
        {
            label: 'Total Penjualan',
            value: selected ? money(selected.sales) : '—',
            current: selected?.sales,
            previous: previous?.sales,
            icon: Coins,
            color: 'amber',
        },
        {
            label: 'Total Komisi',
            value: selected ? money(selected.commissions) : '—',
            current: selected?.commissions,
            previous: previous?.commissions,
            icon: Wallet,
            color: 'coral',
        },
    ];
    const branches = network?.legs.slice(0, 3) ?? [];
    return (
        <>
            <Head title="Admin Dashboard" />
            <main className="honey-main">
                <section className="honey-welcome">
                    <div>
                        <h1>
                            Selamat Datang, {auth.user.name.split(' ')[0]}{' '}
                            <span>👋</span>
                        </h1>
                        <p>
                            Kelola bisnis MLM madu Anda dengan mudah dan
                            profesional.
                        </p>
                    </div>
                    <label className="honey-period">
                        <CalendarDays size={20} />
                        <select
                            aria-label="Periode ringkasan dashboard"
                            value={selectedPeriod}
                            onChange={(event) =>
                                setSelectedPeriod(event.target.value)
                            }
                        >
                            {series.length ? (
                                series.map((item) => (
                                    <option
                                        key={item.period}
                                        value={item.period}
                                    >
                                        {item.label} {admin.analytics?.year}
                                    </option>
                                ))
                            ) : (
                                <option value="">Bulan ini</option>
                            )}
                        </select>
                    </label>
                </section>
                <section
                    className="honey-metrics"
                    aria-label="Ringkasan bisnis"
                >
                    {cards.map(
                        ({
                            label,
                            value,
                            current,
                            previous: prior,
                            icon: Icon,
                            color,
                        }) => (
                            <article
                                className={`honey-metric honey-metric-${color}`}
                                key={label}
                            >
                                <span className="honey-metric-icon">
                                    <Icon size={34} strokeWidth={2.2} />
                                </span>
                                <div>
                                    <p>{label}</p>
                                    <strong>{value}</strong>
                                    <div className="honey-trend">
                                        {current !== undefined &&
                                        prior !== undefined &&
                                        prior > 0 ? (
                                            <>
                                                <span>
                                                    <ArrowUp size={13} />
                                                    {Math.round(
                                                        ((current - prior) /
                                                            prior) *
                                                            100,
                                                    )}
                                                    %
                                                </span>
                                                <small>dari bulan lalu</small>
                                            </>
                                        ) : (
                                            <small>
                                                {label === 'Total Member'
                                                    ? 'Seluruh mitra terdaftar'
                                                    : 'Data transaksi aktual'}
                                            </small>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ),
                    )}
                </section>
                <div className="honey-middle">
                    <section className="honey-panel honey-sales">
                        <div className="honey-chart-header">
                            <PanelTitle
                                icon={ChartNoAxesCombined}
                                title="Grafik Penjualan"
                            />
                            <div className="honey-tabs">
                                {(
                                    ['sales', 'members', 'commissions'] as const
                                ).map((tab) => (
                                    <button
                                        key={tab}
                                        onClick={() => setMetric(tab)}
                                        className={
                                            metric === tab ? 'active' : ''
                                        }
                                    >
                                        {tab === 'sales'
                                            ? 'Penjualan'
                                            : tab === 'members'
                                              ? 'Member Baru'
                                              : 'Komisi'}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <SalesChart
                            series={series.filter(
                                (_, index) =>
                                    index <= Math.max(0, selectedIndex),
                            )}
                            metric={metric}
                        />
                    </section>
                    <section className="honey-panel honey-bonuses">
                        <PanelTitle icon={Coins} title="Bonus/Komisi" warm />
                        <span className="honey-subtitle">
                            Akumulasi bersih setelah reversal
                        </span>
                        {[
                            ['direct', 'Komisi Penjualan'],
                            ['referral', 'Bonus Referral'],
                            ['network', 'Bonus Generasi'],
                        ].map(([type, label]) => (
                            <div className="honey-bonus-row" key={type}>
                                <span className="honey-bonus-icon">
                                    <Coins size={18} />
                                </span>
                                <span>{label}</span>
                                <strong>{money(breakdown[type] ?? 0)}</strong>
                            </div>
                        ))}
                        <div className="honey-bonus-row">
                            <span className="honey-bonus-icon">
                                <Wallet size={18} />
                            </span>
                            <span>Payout diproses</span>
                            <strong>
                                {money(admin.payouts?.reserved ?? 0)}
                            </strong>
                        </div>
                        <div className="honey-bonus-total">
                            <strong>Total Komisi</strong>
                            <b>{money(commissionTotal)}</b>
                        </div>
                    </section>
                    <section className="honey-panel honey-network">
                        <PanelTitle
                            icon={Network}
                            title="Jaringan (Downline)"
                            href={
                                can('partners.manage')
                                    ? partners.url()
                                    : undefined
                            }
                        />
                        {network ? (
                            <div className="honey-tree">
                                <Face name="HoneyCommerce" />
                                <strong>HoneyCommerce</strong>
                                <small>
                                    Total {number(network.total_members)} Member
                                </small>
                                <div className="honey-tree-stem" />
                                <div className="honey-tree-branches">
                                    {branches.map((branch) => (
                                        <Link
                                            href={partners({
                                                query: {
                                                    search: branch.name,
                                                },
                                            })}
                                            key={branch.id}
                                        >
                                            <Face name={branch.name} />
                                            <strong>
                                                {branch.name.split(' ')[0]}
                                            </strong>
                                            <small>
                                                {number(branch.members)} Member
                                            </small>
                                            <div className="honey-tree-leaves">
                                                {network.nodes
                                                    .filter(
                                                        (node) =>
                                                            node.parent_id ===
                                                            branch.id,
                                                    )
                                                    .slice(0, 3)
                                                    .map((child) => (
                                                        <span key={child.id}>
                                                            <Face
                                                                small
                                                                name={
                                                                    child.name
                                                                }
                                                            />
                                                            <small>
                                                                {
                                                                    child.name.split(
                                                                        ' ',
                                                                    )[0]
                                                                }
                                                            </small>
                                                        </span>
                                                    ))}
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                                {branches.length === 0 && (
                                    <p className="honey-subtitle">
                                        Belum ada jaringan mitra
                                    </p>
                                )}
                            </div>
                        ) : (
                            <Empty text="Data jaringan sesuai akses admin" />
                        )}
                    </section>
                </div>
                <div className="honey-bottom">
                    <section className="honey-panel honey-products">
                        <PanelTitle
                            icon={Trophy}
                            title="Produk Terlaris"
                            href={
                                can('catalog.manage')
                                    ? catalog.url()
                                    : undefined
                            }
                            warm
                        />
                        {admin.analytics?.topProducts.length ? (
                            admin.analytics.topProducts.map(
                                (product, index) => (
                                    <Link
                                        href={catalog({
                                            query: { search: product.name },
                                        })}
                                        className="honey-product"
                                        key={product.id}
                                    >
                                        <span
                                            className={`honey-rank honey-rank-${index}`}
                                        >
                                            {index + 1}
                                        </span>
                                        <img src="/honey-product.jpg" alt="" />
                                        <span className="honey-product-name">
                                            <strong>{product.name}</strong>
                                            <small>
                                                {number(product.sold)} terjual
                                            </small>
                                        </span>
                                        <b>{money(product.price)}</b>
                                    </Link>
                                ),
                            )
                        ) : (
                            <Empty text="Belum ada penjualan produk" />
                        )}
                    </section>
                    <section className="honey-panel honey-orders">
                        <PanelTitle
                            icon={ShoppingCart}
                            title="Pesanan Terbaru"
                            href={admin.orders ? orders.url() : undefined}
                        />
                        <div className="honey-table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No. Order</th>
                                        <th>Pelanggan</th>
                                        <th>Produk</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {admin.orders?.recent
                                        .slice(0, 5)
                                        .map((order) => (
                                            <tr key={order.id}>
                                                <td>
                                                    <Link
                                                        href={orders({
                                                            query: {
                                                                search: order.number,
                                                            },
                                                        })}
                                                        title={order.number}
                                                    >
                                                        #
                                                        {order.number.slice(-9)}
                                                    </Link>
                                                </td>
                                                <td>{order.customer ?? '—'}</td>
                                                <td>{order.product ?? '—'}</td>
                                                <td>{money(order.total)}</td>
                                                <td>
                                                    <span
                                                        className={`honey-status honey-status-${order.status}`}
                                                    >
                                                        {labels[order.status] ??
                                                            order.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                </tbody>
                            </table>
                        </div>
                        {!admin.orders?.recent.length && (
                            <Empty text="Belum ada pesanan" />
                        )}
                    </section>
                    <section className="honey-panel honey-members">
                        <PanelTitle
                            icon={Users}
                            title="Member Baru"
                            href={admin.members ? partners.url() : undefined}
                        />
                        {admin.members?.recent.length ? (
                            admin.members.recent.map((member) => (
                                <Link
                                    className="honey-member"
                                    href={partners({
                                        query: { search: member.name },
                                    })}
                                    key={member.id}
                                >
                                    <Face name={member.name} />
                                    <span>
                                        <strong>{member.name}</strong>
                                        <small>
                                            {member.joinedAt
                                                ? date(member.joinedAt)
                                                : '—'}
                                        </small>
                                    </span>
                                    <b
                                        className={`honey-status honey-status-${member.status === 'active' ? 'paid' : 'processing'}`}
                                    >
                                        {member.status === 'active'
                                            ? 'Aktif'
                                            : member.status === 'pending'
                                              ? 'Menunggu'
                                              : member.status}
                                    </b>
                                </Link>
                            ))
                        ) : (
                            <Empty text="Belum ada member baru" />
                        )}
                    </section>
                </div>
                <footer className="honey-footer">
                    <span>
                        © {new Date().getFullYear()} HoneyCommerce. Madu sehat,
                        bisnis hebat.
                    </span>
                    <span>
                        Data diperbarui setiap 15 detik saat halaman dimuat
                        ulang.
                    </span>
                </footer>
            </main>
        </>
    );
}
