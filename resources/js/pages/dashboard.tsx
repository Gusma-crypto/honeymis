import { PartnerNetworkSummary } from '@/components/partner-network-summary';
import { HoneyAdminDashboard } from '@/components/honey-admin-dashboard';
import type { PartnerNetworkData } from '@/components/partner-network-summary';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDownLeft,
    CalendarDays,
    ChevronRight,
    CircleCheck,
    Clock3,
    Truck,
    ArrowRight,
    ArrowUpRight,
    Boxes,
    ClipboardList,
    Coins,
    Package,
    RefreshCw,
    ShieldCheck,
    ShoppingBag,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { money, inputClass } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as orderLink } from '@/routes/orders';
import { index as catalog } from '@/routes/catalog';
import { dashboard as partner } from '@/routes/partner';
import { index as adminOrders } from '@/routes/admin/orders';
import { index as products } from '@/routes/admin/catalog';
import { index as inventory } from '@/routes/admin/inventory';
import { index as partners } from '@/routes/admin/partners';
import { index as payouts } from '@/routes/admin/payouts';
import { index as refunds } from '@/routes/admin/refunds';
import { index as reports } from '@/routes/admin/reports';
import { index as access } from '@/routes/admin/users';
import { index as rules } from '@/routes/admin/commission-rules';
import { index as audit } from '@/routes/admin/audit';
import { index as customers } from '@/routes/admin/customers';
import type { LucideIcon } from 'lucide-react';

type Admin = {
    orders?: {
        counts: Record<string, number>;
        reviewPayments: number;
        recent: {
            id: number;
            number: string;
            status: string;
            total: number;
            placedAt: string;
        }[];
    };
    activeProducts?: number;
    pendingPartners?: number;
    refundRequests?: number;
    lowStock?: { id: number; name: string; sku: string; available: number }[];
    payouts?: { requested: number; approved: number; reserved: number };
    finance?: { verified: number; refunded: number };
};
const statuses: Record<string, string> = {
    pending_payment: 'Menunggu pembayaran',
    paid: 'Sudah dibayar',
    processing: 'Diproses',
    shipped: 'Dikirim',
    delivered: 'Diterima',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    refunded: 'Refund',
};
const number = (value: number) => new Intl.NumberFormat('id-ID').format(value);
function Metric({
    label,
    value,
    detail,
    icon: Icon,
}: {
    label: string;
    value: string;
    detail: string;
    icon: LucideIcon;
}) {
    return (
        <article className="dashboard-metric rounded-2xl border border-t-2 border-t-primary/60 bg-card p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">{label}</p>
                <Icon className="size-9 rounded-xl bg-accent p-2 text-accent-foreground" />
            </div>
            <p className="text-3xl font-semibold tracking-tight">{value}</p>
            <p className="mt-2 text-xs text-muted-foreground">{detail}</p>
        </article>
    );
}
function LegacyDashboard({
    admin,
    stats,
    wallet,
    networkSummary,
}: {
    admin: Admin | null;
    stats: Record<string, number>;
    wallet: Record<string, number> | null;
    networkSummary: PartnerNetworkData | null;
}) {
    const { auth } = usePage().props;
    const [refreshing, setRefreshing] = useState(false);
    const [menuSearch, setMenuSearch] = useState('');
    const [menuGroup, setMenuGroup] = useState('');
    const can = (permission: string) => auth.permissions.includes(permission);
    const modules = [
        {
            title: 'Pesanan',
            detail: 'Pembayaran, proses & pengiriman',
            href: adminOrders(),
            icon: ShoppingBag,
            allowed: can('orders.manage') || can('payments.verify'),
        },
        {
            title: 'Katalog produk',
            detail: 'Produk, varian & kelayakan komisi',
            href: products(),
            icon: Package,
            allowed: can('catalog.manage'),
        },
        {
            title: 'Persediaan',
            detail: 'Stok tersedia & penyesuaian',
            href: inventory(),
            icon: Boxes,
            allowed: can('inventory.manage'),
        },
        {
            title: 'Mitra',
            detail: 'Pengajuan & persetujuan mitra',
            href: partners(),
            icon: Users,
            allowed: can('partners.manage'),
        },
        {
            title: 'Payout',
            detail: 'Tinjau pengajuan pencairan',
            href: payouts(),
            icon: Wallet,
            allowed: can('payouts.review'),
        },
        {
            title: 'Refund',
            detail: 'Permintaan retur & reversal',
            href: refunds(),
            icon: ArrowDownLeft,
            allowed: can('refunds.manage'),
        },
        {
            title: 'Aturan komisi',
            detail: 'Draft, simulasi & publish versi',
            href: rules(),
            icon: Coins,
            allowed: can('commission-rules.manage'),
        },
        {
            title: 'Laporan',
            detail: 'Penjualan & ekspor CSV',
            href: reports(),
            icon: ClipboardList,
            allowed: can('reports.view'),
        },
        {
            title: 'Pelanggan',
            detail: 'Profil & riwayat pesanan',
            href: customers(),
            icon: Users,
            allowed: can('orders.manage'),
        },
        {
            title: 'Akses pengguna',
            detail: 'Role & status akun',
            href: access(),
            icon: ShieldCheck,
            allowed: can('users.manage-access'),
        },
        {
            title: 'Audit log',
            detail: 'Riwayat perubahan operasional',
            href: audit(),
            icon: ClipboardList,
            allowed: can('audit.view'),
        },
    ].filter((module) => module.allowed);
    const visibleModules = modules.filter((module) => {
        const group = ['Aturan komisi', 'Payout', 'Refund', 'Laporan'].some(
            (title) => module.title.includes(title),
        )
            ? 'finance'
            : ['Akses', 'Audit'].some((title) => module.title.includes(title))
              ? 'access'
              : 'operations';
        return (
            (!menuGroup || group === menuGroup) &&
            `${module.title} ${module.detail}`
                .toLocaleLowerCase('id-ID')
                .includes(menuSearch.trim().toLocaleLowerCase('id-ID'))
        );
    });
    if (admin === null) {
        return (
            <>
                <Head title="Dashboard" />
                <main className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-5 sm:p-8">
                    <header>
                        <p className="text-sm text-muted-foreground">
                            HoneyCommerce
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Selamat datang, {auth.user.name}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            Pantau pesanan dan penghasilan mitra Anda.
                        </p>
                    </header>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {Object.entries(stats).map(([label, value]) => (
                            <Metric
                                key={label}
                                label={label}
                                value={number(value)}
                                detail="Pesanan pada akun Anda"
                                icon={ShoppingBag}
                            />
                        ))}
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={catalog()}>
                                Belanja madu <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={partner()}>Portal mitra</Link>
                        </Button>
                    </div>
                    {networkSummary && (
                        <PartnerNetworkSummary data={networkSummary} />
                    )}
                    {wallet && (
                        <section className="grid gap-4 sm:grid-cols-3">
                            {Object.entries(wallet).map(([label, value]) => (
                                <Metric
                                    key={label}
                                    label={label}
                                    value={money(value)}
                                    detail="Saldo akun mitra Anda"
                                    icon={Wallet}
                                />
                            ))}
                        </section>
                    )}
                </main>
            </>
        );
    }
    const counts = admin.orders?.counts ?? {};
    const totalOrders = Object.values(counts).reduce(
        (total, value) => total + value,
        0,
    );
    const operationalOrders =
        (counts.paid ?? 0) + (counts.processing ?? 0) + (counts.shipped ?? 0);
    const attentionItems = [
        ...(admin.orders
            ? [
                  {
                      label: 'Pembayaran perlu diperiksa',
                      detail: 'Tinjau bukti transfer pelanggan',
                      count: admin.orders.reviewPayments,
                      href: adminOrders(),
                      icon: ClipboardList,
                  },
                  {
                      label: 'Pesanan siap diproses',
                      detail: 'Pembayaran sudah diverifikasi',
                      count: counts.paid ?? 0,
                      href: adminOrders(),
                      icon: Package,
                  },
              ]
            : []),
        ...(admin.pendingPartners !== undefined
            ? [
                  {
                      label: 'Pengajuan mitra',
                      detail: 'Menunggu keputusan Anda',
                      count: admin.pendingPartners,
                      href: partners(),
                      icon: Users,
                  },
              ]
            : []),
        ...(admin.payouts
            ? [
                  {
                      label: 'Pengajuan payout',
                      detail: 'Tinjau pencairan komisi',
                      count: admin.payouts.requested,
                      href: payouts(),
                      icon: Wallet,
                  },
              ]
            : []),
        ...(admin.refundRequests !== undefined
            ? [
                  {
                      label: 'Permintaan refund',
                      detail: 'Periksa pengembalian dana',
                      count: admin.refundRequests,
                      href: refunds(),
                      icon: ArrowDownLeft,
                  },
              ]
            : []),
    ];
    const attentionCount = attentionItems.reduce(
        (total, item) => total + item.count,
        0,
    );
    const distribution = [
        {
            label: 'Menunggu bayar',
            count: counts.pending_payment ?? 0,
            color: 'bg-blue-400',
        },
        {
            label: 'Dalam pemenuhan',
            count: operationalOrders,
            color: 'bg-sky-500',
        },
        {
            label: 'Diterima / selesai',
            count: (counts.delivered ?? 0) + (counts.completed ?? 0),
            color: 'bg-emerald-500',
        },
        {
            label: 'Batal / refund',
            count: (counts.cancelled ?? 0) + (counts.refunded ?? 0),
            color: 'bg-slate-400',
        },
    ];
    const statusTone = (status: string) =>
        ['completed', 'delivered'].includes(status)
            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-950/60 dark:text-emerald-300'
            : ['paid', 'processing', 'shipped'].includes(status)
              ? 'bg-sky-50 text-sky-700 ring-sky-600/15 dark:bg-sky-950/60 dark:text-sky-300'
              : status === 'pending_payment'
                ? 'bg-blue-50 text-blue-800 ring-blue-600/15 dark:bg-blue-950/60 dark:text-blue-300'
                : 'bg-muted text-muted-foreground ring-border';
    return (
        <>
            <Head title="Dashboard administrator" />
            <main className="min-h-full bg-stone-50/80 dark:bg-background">
                <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                    <header className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="mb-1.5 text-xs font-semibold tracking-[0.16em] text-blue-700 uppercase dark:text-blue-400">
                                Pusat kendali toko
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Dashboard administrator
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Selamat datang,{' '}
                                <span className="font-medium text-foreground">
                                    {auth.user.name}
                                </span>
                                . Pantau toko dan selesaikan pekerjaan hari ini.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="mr-1 hidden items-center gap-2 text-xs text-muted-foreground sm:inline-flex">
                                <CalendarDays className="size-4" />
                                {new Date().toLocaleDateString('id-ID', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                    timeZone: 'Asia/Jakarta',
                                })}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                className="bg-card"
                                disabled={refreshing}
                                onClick={() =>
                                    router.reload({
                                        only: ['admin', 'networkSummary'],
                                        onStart: () => setRefreshing(true),
                                        onFinish: () => setRefreshing(false),
                                    })
                                }
                            >
                                <RefreshCw
                                    className={`size-3.5 ${refreshing ? 'animate-spin' : ''}`}
                                />
                                {refreshing ? 'Memuat...' : 'Perbarui'}
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                className="bg-blue-600 text-white hover:bg-blue-500"
                            >
                                <Link href={catalog()}>
                                    Lihat toko{' '}
                                    <ArrowUpRight className="size-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </header>
                    <section className="relative overflow-hidden rounded-2xl border border-blue-800 bg-linear-to-br from-blue-950 via-blue-900 to-blue-700 px-5 py-5 text-blue-50 sm:px-7">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute -top-20 -right-8 size-64 rounded-full border-[35px] border-blue-400/10"
                        />
                        <div className="relative flex flex-wrap items-center justify-between gap-5">
                            <div className="flex items-center gap-4">
                                <span className="hidden rounded-2xl border border-white/10 bg-white/5 p-3 sm:block">
                                    <ShieldCheck className="size-7 text-blue-300" />
                                </span>
                                <div>
                                    <p className="text-xs font-medium tracking-widest text-blue-300 uppercase">
                                        HoneyCommerce
                                    </p>
                                    <h2 className="mt-1.5 text-lg font-semibold sm:text-xl">
                                        Semua aktivitas toko, dalam satu tempat.
                                    </h2>
                                    <p className="mt-1.5 text-sm text-stone-400">
                                        Pesanan, persediaan, mitra, dan komisi
                                        penjualan.
                                    </p>
                                </div>
                            </div>
                            <div className="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                                <p className="text-xs text-stone-400">
                                    Antrean tindak lanjut
                                </p>
                                <p className="mt-1 flex items-baseline gap-2 text-2xl font-semibold text-blue-300">
                                    {number(attentionCount)}
                                    <span className="text-xs font-normal text-stone-300">
                                        item menunggu
                                    </span>
                                </p>
                            </div>
                        </div>
                    </section>
                    <section
                        className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        aria-label="Ringkasan operasional"
                    >
                        {admin.orders && (
                            <>
                                <Metric
                                    label="Total pesanan"
                                    value={number(totalOrders)}
                                    detail="Akumulasi seluruh status"
                                    icon={ShoppingBag}
                                />
                                <Metric
                                    label="Perlu dipenuhi"
                                    value={number(operationalOrders)}
                                    detail="Dibayar, diproses, dan dikirim"
                                    icon={Truck}
                                />
                                <Metric
                                    label="Review pembayaran"
                                    value={number(admin.orders.reviewPayments)}
                                    detail="Bukti transfer menunggu pemeriksaan"
                                    icon={ClipboardList}
                                />
                            </>
                        )}
                        {admin.activeProducts !== undefined ? (
                            <Metric
                                label="Produk aktif"
                                value={number(admin.activeProducts)}
                                detail="Produk tersedia di katalog"
                                icon={Boxes}
                            />
                        ) : (
                            admin.payouts && (
                                <Metric
                                    label="Review payout"
                                    value={number(admin.payouts.requested)}
                                    detail={`${number(admin.payouts.approved)} disetujui, belum ditransfer`}
                                    icon={Wallet}
                                />
                            )
                        )}
                    </section>
                    {admin.finance && (
                        <section
                            className="grid gap-3 md:grid-cols-2"
                            aria-label="Keuangan bulan ini"
                        >
                            <article className="flex items-center gap-4 rounded-xl border border-emerald-200/70 bg-emerald-50/70 px-5 py-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                                <span className="rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <ArrowUpRight className="size-5" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-xs text-emerald-800 dark:text-emerald-300">
                                        Pembayaran terverifikasi bulan ini
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold tracking-tight break-words">
                                        {money(admin.finance.verified)}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Tanggal verifikasi &middot; bulan
                                        kalender WIB &middot; sebelum refund
                                    </p>
                                </div>
                            </article>
                            <article className="flex items-center gap-4 rounded-xl border bg-card px-5 py-4">
                                <span className="rounded-xl bg-rose-50 p-2.5 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
                                    <ArrowDownLeft className="size-5" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">
                                        Refund diproses bulan ini
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold tracking-tight break-words">
                                        {money(admin.finance.refunded)}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Tanggal pemrosesan refund &middot; bulan
                                        kalender WIB
                                    </p>
                                </div>
                            </article>
                        </section>
                    )}
                    <div className="grid items-start gap-5 xl:grid-cols-3">
                        <div className="flex min-w-0 flex-col gap-5 xl:col-span-2">
                            {admin.orders && (
                                <section className="overflow-hidden rounded-2xl border bg-card">
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                                        <div>
                                            <h2 className="font-semibold">
                                                Pesanan terbaru
                                            </h2>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Enam pesanan terakhir dari
                                                pelanggan
                                            </p>
                                        </div>
                                        <Link
                                            href={adminOrders()}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline dark:text-blue-400"
                                        >
                                            Semua pesanan{' '}
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    </div>
                                    {admin.orders.recent.length ? (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead className="bg-muted/40 text-[11px] tracking-wide text-muted-foreground uppercase">
                                                    <tr>
                                                        <th
                                                            scope="col"
                                                            className="px-5 py-3 font-medium"
                                                        >
                                                            Nomor pesanan
                                                        </th>
                                                        <th
                                                            scope="col"
                                                            className="px-4 py-3 font-medium"
                                                        >
                                                            Status
                                                        </th>
                                                        <th
                                                            scope="col"
                                                            className="px-5 py-3 text-right font-medium"
                                                        >
                                                            Total
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {admin.orders.recent.map(
                                                        (order) => (
                                                            <tr
                                                                className="border-t transition-colors hover:bg-muted/30"
                                                                key={order.id}
                                                            >
                                                                <td className="px-5 py-4">
                                                                    <Link
                                                                        href={orderLink(
                                                                            order.id,
                                                                        )}
                                                                        className="block max-w-44 truncate font-medium underline-offset-4 hover:underline sm:max-w-56"
                                                                        title={
                                                                            order.number
                                                                        }
                                                                    >
                                                                        {
                                                                            order.number
                                                                        }
                                                                    </Link>
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {new Date(
                                                                            order.placedAt,
                                                                        ).toLocaleDateString(
                                                                            'id-ID',
                                                                            {
                                                                                day: 'numeric',
                                                                                month: 'short',
                                                                                year: 'numeric',
                                                                                timeZone:
                                                                                    'Asia/Jakarta',
                                                                            },
                                                                        )}
                                                                    </p>
                                                                </td>
                                                                <td className="px-4 py-4">
                                                                    <span
                                                                        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium whitespace-nowrap ring-1 ring-inset ${statusTone(order.status)}`}
                                                                    >
                                                                        <span className="size-1.5 rounded-full bg-current opacity-60" />
                                                                        {statuses[
                                                                            order
                                                                                .status
                                                                        ] ??
                                                                            order.status}
                                                                    </span>
                                                                </td>
                                                                <td className="px-5 py-4 text-right font-medium whitespace-nowrap tabular-nums">
                                                                    {money(
                                                                        order.total,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center px-6 py-14 text-center">
                                            <div className="mb-4 rounded-full border border-dashed bg-muted/40 p-5">
                                                <ShoppingBag className="size-7 text-muted-foreground" />
                                            </div>
                                            <h3 className="text-sm font-semibold">
                                                Belum ada pesanan masuk
                                            </h3>
                                            <p className="mt-2 max-w-xs text-xs leading-5 text-muted-foreground">
                                                Pesanan akan tampil di sini
                                                setelah pelanggan menyelesaikan
                                                checkout.
                                            </p>
                                            {can('catalog.manage') && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-5"
                                                >
                                                    <Link href={products()}>
                                                        Siapkan katalog produk{' '}
                                                        <ArrowRight className="size-3.5" />
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </section>
                            )}
                            {admin.orders && (
                                <section className="rounded-2xl border border-t-2 border-t-primary/60 bg-card p-5 shadow-sm">
                                    <div className="mb-5 flex items-center justify-between">
                                        <h2 className="text-sm font-semibold">
                                            Distribusi status pesanan
                                        </h2>
                                        <span className="text-xs text-muted-foreground">
                                            {number(totalOrders)} total
                                        </span>
                                    </div>
                                    <div
                                        className="mb-5 flex h-2.5 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        {distribution.map(
                                            (item) =>
                                                item.count > 0 && (
                                                    <div
                                                        key={item.label}
                                                        className={item.color}
                                                        style={{
                                                            width: `${totalOrders ? (item.count / totalOrders) * 100 : 0}%`,
                                                        }}
                                                    />
                                                ),
                                        )}
                                    </div>
                                    <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                                        {distribution.map((item) => (
                                            <div key={item.label}>
                                                <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                                    <span
                                                        className={`size-2 shrink-0 rounded-full ${item.color}`}
                                                    />
                                                    {item.label}
                                                </p>
                                                <p className="mt-1.5 text-xl font-semibold tabular-nums">
                                                    {number(item.count)}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                        <aside className="flex min-w-0 flex-col gap-5">
                            <section className="overflow-hidden rounded-2xl border bg-card">
                                <div className="flex items-center justify-between border-b px-5 py-4">
                                    <h2 className="font-semibold">
                                        Perlu ditindaklanjuti
                                    </h2>
                                    <Clock3 className="size-4 text-blue-600" />
                                </div>
                                <div className="divide-y">
                                    {attentionItems.map((item) => (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            className="group flex items-center gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40"
                                        >
                                            <span
                                                className={`rounded-lg p-2 ${item.count > 0 ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-muted/60 text-muted-foreground'}`}
                                            >
                                                <item.icon className="size-4" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-xs font-medium">
                                                    {item.label}
                                                </span>
                                                <span className="mt-1 block text-[11px] text-muted-foreground">
                                                    {item.detail}
                                                </span>
                                            </span>
                                            <span
                                                className={`min-w-6 rounded-md px-1.5 py-1 text-center text-xs font-semibold ${item.count > 0 ? 'bg-blue-100 text-blue-900 dark:bg-blue-900 dark:text-blue-100' : 'bg-muted text-muted-foreground'}`}
                                            >
                                                {number(item.count)}
                                            </span>
                                            <ChevronRight className="size-3.5 text-muted-foreground" />
                                        </Link>
                                    ))}
                                </div>
                                {attentionCount === 0 && (
                                    <p className="flex items-center gap-2 border-t px-5 py-3 text-xs text-emerald-700 dark:text-emerald-400">
                                        <CircleCheck className="size-4" />
                                        Tidak ada antrean tindak lanjut.
                                    </p>
                                )}
                            </section>
                            {admin.lowStock && (
                                <section className="rounded-2xl border border-t-2 border-t-primary/60 bg-card p-5 shadow-sm">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Boxes className="size-4 text-blue-600" />
                                            <h2 className="text-sm font-semibold">
                                                Stok menipis
                                            </h2>
                                        </div>
                                        <Link
                                            href={inventory()}
                                            className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                        >
                                            Kelola
                                        </Link>
                                    </div>
                                    <p className="mt-2 text-[11px] text-muted-foreground">
                                        Varian aktif dengan sisa stok paling
                                        sedikit, maksimal 5 unit.
                                    </p>
                                    {admin.lowStock.length ? (
                                        <ul className="mt-5 divide-y">
                                            {admin.lowStock.map((item) => (
                                                <li
                                                    key={item.id}
                                                    className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-xs font-medium">
                                                            {item.name}
                                                        </p>
                                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                                            {item.sku}
                                                        </p>
                                                    </div>
                                                    <span
                                                        className={`shrink-0 rounded-md px-2 py-1 text-xs font-semibold ${item.available === 0 ? 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200'}`}
                                                    >
                                                        {item.available === 0
                                                            ? 'Habis'
                                                            : `${item.available} unit`}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <div className="mt-5 flex items-center gap-2 rounded-lg bg-muted/40 p-3 text-xs text-muted-foreground">
                                            <CircleCheck className="size-4 shrink-0 text-emerald-600" />
                                            Tidak ada stok rendah pada produk
                                            aktif.
                                        </div>
                                    )}
                                </section>
                            )}
                            {admin.payouts && (
                                <section className="rounded-2xl border border-t-2 border-t-primary/60 bg-card p-5 shadow-sm">
                                    <p className="text-xs text-muted-foreground">
                                        Dana payout direservasi
                                    </p>
                                    <p className="mt-2 text-xl font-semibold tracking-tight">
                                        {money(admin.payouts.reserved)}
                                    </p>
                                    <p className="mt-2 text-[11px] leading-5 text-muted-foreground">
                                        {number(admin.payouts.approved)}{' '}
                                        pengajuan disetujui dan menunggu
                                        pencatatan transfer manual.
                                    </p>
                                    <Link
                                        className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-blue-700 dark:text-blue-400"
                                        href={payouts()}
                                    >
                                        Tinjau payout{' '}
                                        <ArrowRight className="size-3.5" />
                                    </Link>
                                </section>
                            )}
                        </aside>
                    </div>
                    {networkSummary && (
                        <PartnerNetworkSummary data={networkSummary} />
                    )}
                    <section className="rounded-2xl border border-t-2 border-t-primary/60 bg-card p-5 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                            <div>
                                <h2 className="font-semibold">
                                    Akses operasional
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Pilih area kerja yang ingin Anda kelola.
                                </p>
                            </div>
                            <span className="text-[11px] text-muted-foreground">
                                Ditampilkan sesuai hak akses
                            </span>
                        </div>
                        <div className="mb-4 flex flex-wrap gap-3">
                            <input
                                type="search"
                                aria-label="Cari menu dashboard"
                                placeholder="Cari menu dashboard..."
                                className={`${inputClass} min-w-48 flex-1`}
                                value={menuSearch}
                                onChange={(e) => setMenuSearch(e.target.value)}
                            />
                            <select
                                aria-label="Kelompok menu dashboard"
                                className={`${inputClass} w-auto`}
                                value={menuGroup}
                                onChange={(e) => setMenuGroup(e.target.value)}
                            >
                                <option value="">Semua area kerja</option>
                                <option value="operations">Operasional</option>
                                <option value="finance">Keuangan</option>
                                <option value="access">Administrasi</option>
                            </select>
                        </div>
                        {visibleModules.length === 0 && (
                            <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                Menu tidak ditemukan. Ubah kata kunci atau
                                kelompok menu.
                            </p>
                        )}
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {visibleModules.map(
                                ({ title, detail, href, icon: Icon }) => (
                                    <Link
                                        key={title}
                                        href={href}
                                        className="dashboard-menu-card group flex items-center gap-3 rounded-xl border border-transparent bg-muted/30 p-3 transition-colors hover:border-blue-200 hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:hover:border-blue-900 dark:hover:bg-blue-950/30"
                                    >
                                        <span className="rounded-lg border bg-card p-2">
                                            <Icon className="size-4 text-stone-600 dark:text-stone-300" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-xs font-semibold">
                                                {title}
                                            </span>
                                            <span className="mt-1 block truncate text-[10px] text-muted-foreground">
                                                {detail}
                                            </span>
                                        </span>
                                        <ChevronRight className="size-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                    </Link>
                                ),
                            )}
                        </div>
                    </section>
                    <footer className="flex flex-wrap items-center justify-between gap-2 px-1 pb-2 text-[11px] text-muted-foreground">
                        <p>
                            HoneyCommerce &middot; Komisi penjualan direct satu
                            level
                        </p>
                        <p className="inline-flex items-center gap-1.5">
                            <Clock3 className="size-3" />
                            Ringkasan diperbarui dengan jeda maksimal 15 detik.
                        </p>
                    </footer>
                </div>
            </main>
        </>
    );
}

export default function Dashboard(
    props: Parameters<typeof LegacyDashboard>[0],
) {
    return props.admin !== null ? (
        <HoneyAdminDashboard
            admin={props.admin}
            network={props.networkSummary}
        />
    ) : (
        <LegacyDashboard {...props} />
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
