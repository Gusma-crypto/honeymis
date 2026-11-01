import { MemberAvatar } from '@/components/member-avatar';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { PropsWithChildren } from 'react';
import {
    ArrowRight,
    Bell,
    ChartNoAxesCombined,
    Check,
    ChevronDown,
    Coins,
    Droplet,
    Gift,
    LayoutDashboard,
    Menu,
    Network,
    Package,
    Search,
    Settings,
    ShieldCheck,
    ScrollText,
    RotateCcw,
    ShoppingCart,
    Users,
    Wallet,
    X,
} from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';
import type { AdminOverview } from '@/components/honey-admin-dashboard';
import { dashboard, logout } from '@/routes';
import { commerceSettings } from '@/routes/admin';
import { index as catalog } from '@/routes/admin/catalog';
import { index as orders } from '@/routes/admin/orders';
import { index as partners } from '@/routes/admin/partners';
import { index as commissions } from '@/routes/admin/commissions';
import { index as marketing } from '@/routes/admin/marketing';
import { index as customers } from '@/routes/admin/customers';
import { index as reports } from '@/routes/admin/reports';
import { index as payouts } from '@/routes/admin/payouts';
import { index as inventory } from '@/routes/admin/inventory';
import { index as refunds } from '@/routes/admin/refunds';
import { index as access } from '@/routes/admin/users';
import { index as audit } from '@/routes/admin/audit';
import { index as storefront } from '@/routes/catalog';
import { edit as profile } from '@/routes/profile';
import {
    dashboard as ownDashboard,
    network as ownNetwork,
} from '@/routes/partner';
import { index as ownWallet } from '@/routes/wallet';
import { index as ownPayouts } from '@/routes/payouts';
import { index as ownOrders } from '@/routes/orders';
import '../../css/honey-admin.css';

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
export function HoneyAdminLayout({
    children,
    breadcrumbs = [],
    portal = 'admin',
}: PropsWithChildren<{
    breadcrumbs?: BreadcrumbItem[];
    portal?: 'admin' | 'partner';
}>) {
    const page = usePage();
    const { auth } = page.props;
    const admin = page.props.admin as AdminOverview | undefined;
    const isDashboard = page.component === 'dashboard';
    const isCatalog = page.component === 'admin/catalog';
    const isOrders = page.component === 'admin/orders';
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [desktopCollapsed, setDesktopCollapsed] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [search, setSearch] = useState('');
    useEffect(
        () =>
            router.on('navigate', () => {
                setSidebarOpen(false);
                setNotificationsOpen(false);
                setProfileOpen(false);
                setSearch('');
            }),
        [],
    );
    const can = (permission: string) => auth.permissions.includes(permission);
    const adminNavigation = [
        {
            title: 'Dashboard',
            icon: LayoutDashboard,
            href: dashboard.url(),
            allowed: true,
        },
        {
            title: 'Produk',
            icon: Package,
            href: catalog.url(),
            allowed: can('catalog.manage'),
        },
        {
            title: 'Pesanan',
            icon: ShoppingCart,
            href: orders.url(),
            allowed: can('orders.manage') || can('payments.verify'),
        },
        {
            title: 'Jaringan MLM',
            icon: Network,
            href: partners.url(),
            allowed: can('partners.manage'),
        },
        {
            title: 'Komisi',
            icon: Coins,
            href: commissions.url(),
            allowed: can('commission-rules.manage'),
        },
        {
            title: 'Pelanggan',
            icon: Users,
            href: customers.url(),
            allowed: can('orders.manage'),
        },
        {
            title: 'Marketing',
            icon: ChartNoAxesCombined,
            href: marketing.url(),
            allowed: can('reports.view'),
        },
        {
            title: 'Persediaan',
            icon: Gift,
            href: inventory.url(),
            allowed: can('inventory.manage'),
        },
        {
            title: 'Laporan',
            icon: ChartNoAxesCombined,
            href: reports.url(),
            allowed: can('reports.view'),
        },
        {
            title: 'Payout Mitra',
            icon: Wallet,
            href: payouts.url(),
            allowed: can('payouts.review'),
        },
        {
            title: 'Refund & Retur',
            icon: RotateCcw,
            href: refunds.url(),
            allowed: can('refunds.manage'),
        },
        {
            title: 'Akses Pengguna',
            icon: ShieldCheck,
            href: access.url(),
            allowed: can('users.manage-access'),
        },
        {
            title: 'Audit Log',
            icon: ScrollText,
            href: audit.url(),
            allowed: can('audit.view'),
        },
        {
            title: 'Pengaturan',
            icon: Settings,
            href: commerceSettings.url(),
            allowed: can('catalog.manage'),
        },
    ].filter((item) => item.allowed);
    const navigation =
        portal === 'partner'
            ? [
                  {
                      title: 'Dashboard Mitra',
                      icon: LayoutDashboard,
                      href: ownDashboard.url(),
                      allowed: true,
                  },
                  {
                      title: 'Produk',
                      icon: Package,
                      href: storefront.url(),
                      allowed: true,
                  },
                  {
                      title: 'Pesanan Saya',
                      icon: ShoppingCart,
                      href: ownOrders.url(),
                      allowed: can('orders.view-own'),
                  },
                  {
                      title: 'Jaringan Saya',
                      icon: Network,
                      href: ownNetwork.url(),
                      allowed: can('referrals.view-own'),
                  },
                  {
                      title: 'Komisi & Dompet',
                      icon: Coins,
                      href: ownWallet.url(),
                      allowed: can('wallets.view-own'),
                  },
                  {
                      title: 'Pencairan Komisi',
                      icon: Wallet,
                      href: ownPayouts.url(),
                      allowed: can('payouts.request-own'),
                  },
                  {
                      title: 'Profil & Keamanan',
                      icon: Settings,
                      href: profile.url(),
                      allowed: true,
                  },
              ].filter((item) => item.allowed)
            : adminNavigation;
    const isNavigationActive = (href: string) => {
        if (
            portal === 'admin' &&
            ((href === orders.url() && page.component === 'commerce/order') ||
                (href === commissions.url() &&
                    page.component === 'admin/commission-rules') ||
                (href === marketing.url() &&
                    page.component === 'admin/mlm-settings'))
        ) {
            return true;
        }
        return portal === 'partner'
            ? page.url.split('?')[0] === href
            : isCurrentOrParentUrl(href);
    };
    const searchResults = navigation.filter((item) =>
        item.title.toLowerCase().includes(search.trim().toLowerCase()),
    );
    const notificationCount =
        (admin?.orders?.reviewPayments ?? 0) +
        (admin?.pendingPartners ?? 0) +
        (admin?.payouts?.requested ?? 0) +
        (admin?.refundRequests ?? 0);
    const notifications = [
        {
            count: admin?.orders?.reviewPayments ?? 0,
            title: 'pembayaran menunggu verifikasi',
            href: orders.url(),
        },
        {
            count: admin?.pendingPartners ?? 0,
            title: 'pengajuan mitra baru',
            href: partners.url(),
        },
        {
            count: admin?.payouts?.requested ?? 0,
            title: 'pengajuan payout',
            href: payouts.url(),
        },
        {
            count: admin?.refundRequests ?? 0,
            title: 'permintaan refund',
            href: refunds.url(),
        },
    ].filter((item) => item.count > 0);
    return (
        <div
            className={`honey-admin ${desktopCollapsed ? 'honey-sidebar-collapsed' : ''}`}
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    setSidebarOpen(false);
                    setNotificationsOpen(false);
                    setProfileOpen(false);
                    setSearch('');
                }
            }}
        >
            {sidebarOpen && (
                <button
                    className="honey-backdrop"
                    aria-label="Tutup navigasi"
                    onClick={() => setSidebarOpen(false)}
                />
            )}
            <aside className={`honey-sidebar ${sidebarOpen ? 'is-open' : ''}`}>
                <Link href={dashboard()} className="honey-brand">
                    <span className="honey-emblem">
                        <Droplet size={30} fill="#ffac13" />
                    </span>
                    <span>
                        <strong>HoneyCommerce</strong>
                        <small>Madu Sehat, Hidup Lebih Baik</small>
                    </span>
                </Link>
                <nav aria-label="Navigasi admin">
                    {navigation.map(({ title, icon: Icon, href }) => (
                        <Link
                            key={title}
                            href={href}
                            className={
                                isNavigationActive(href) ? 'is-active' : ''
                            }
                            aria-current={
                                isNavigationActive(href) ? 'page' : undefined
                            }
                            onClick={() => setSidebarOpen(false)}
                        >
                            <Icon size={21} />
                            <span>{title}</span>
                        </Link>
                    ))}
                </nav>
                <div className="honey-tools">
                    <small>TOOLS</small>
                    <Link href={storefront()}>
                        <Package size={21} />
                        Lihat Toko
                    </Link>
                    <Link
                        href={profile()}
                        className={
                            page.component.startsWith('settings/')
                                ? 'is-active'
                                : ''
                        }
                    >
                        <Users size={21} />
                        Profil & Keamanan
                    </Link>
                </div>
                <div className="honey-promo">
                    <img src="/honey-hero.jpg" alt="Madu alami HoneyCommerce" />
                    <div>
                        <h3>
                            Produk Madu
                            <br />
                            Berkualitas
                            <br />
                            Untuk Keluarga
                        </h3>
                        <Link href={storefront()}>
                            Lihat Produk <ArrowRight size={16} />
                        </Link>
                    </div>
                </div>
            </aside>
            <div className="honey-workspace">
                <header className="honey-topbar">
                    <button
                        className="honey-icon-button"
                        aria-label="Buka navigasi"
                        onClick={() => {
                            if (
                                window.matchMedia('(min-width: 1101px)').matches
                            ) {
                                setDesktopCollapsed(!desktopCollapsed);
                            } else {
                                setSidebarOpen(!sidebarOpen);
                            }
                        }}
                    >
                        <Menu size={22} />
                    </button>
                    <div className="honey-search">
                        <Search size={20} />
                        <input
                            aria-label={
                                isCatalog
                                    ? 'Cari produk, SKU, atau kategori'
                                    : 'Cari menu admin'
                            }
                            placeholder={
                                isCatalog
                                    ? 'Cari produk, SKU, atau kategori...'
                                    : 'Cari menu produk, member, pesanan, atau komisi...'
                            }
                            value={search}
                            maxLength={100}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (
                                    (isCatalog || isOrders) &&
                                    event.key === 'Enter'
                                ) {
                                    event.preventDefault();
                                    router.get(
                                        isCatalog
                                            ? catalog.url()
                                            : orders.url(),
                                        { search },
                                    );
                                }
                            }}
                        />
                        {search && (
                            <div className="honey-popover honey-search-results">
                                {isCatalog || isOrders ? (
                                    <Link
                                        href={(isCatalog
                                            ? catalog
                                            : orders
                                        ).url({
                                            query: { search },
                                        })}
                                    >
                                        Cari {isCatalog ? 'produk' : 'pesanan'}:{' '}
                                        {search}
                                        <ArrowRight size={15} />
                                    </Link>
                                ) : searchResults.length ? (
                                    searchResults.map((item) => (
                                        <Link key={item.title} href={item.href}>
                                            {item.title}
                                            <ArrowRight size={15} />
                                        </Link>
                                    ))
                                ) : (
                                    <p>Menu tidak ditemukan.</p>
                                )}
                            </div>
                        )}
                    </div>
                    <div className="honey-account-actions">
                        <div className="honey-popover-anchor">
                            <button
                                className="honey-icon-button honey-bell"
                                aria-label={`Notifikasi: ${notificationCount} perlu ditinjau`}
                                aria-expanded={notificationsOpen}
                                onClick={() => {
                                    setNotificationsOpen(!notificationsOpen);
                                    setProfileOpen(false);
                                }}
                            >
                                <Bell size={23} />
                                {notificationCount > 0 && (
                                    <span>{notificationCount}</span>
                                )}
                            </button>
                            {notificationsOpen && (
                                <div className="honey-popover">
                                    <strong>Perlu ditinjau</strong>
                                    {!admin ? (
                                        <Link href={dashboard()}>
                                            Lihat ringkasan di Dashboard{' '}
                                            <ArrowRight size={14} />
                                        </Link>
                                    ) : notifications.length ? (
                                        notifications.map((item) => (
                                            <Link
                                                href={item.href}
                                                key={item.title}
                                            >
                                                {item.count} {item.title}
                                                <ArrowRight size={14} />
                                            </Link>
                                        ))
                                    ) : (
                                        <p>
                                            <Check size={16} />
                                            Semua sudah ditinjau.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="honey-popover-anchor">
                            <button
                                className="honey-account"
                                aria-expanded={profileOpen}
                                onClick={() => {
                                    setProfileOpen(!profileOpen);
                                    setNotificationsOpen(false);
                                }}
                            >
                                <Face
                                    name={auth.user.name}
                                    gender={auth.user.gender}
                                    photo={auth.user.avatar}
                                />
                                <span>
                                    <strong>{auth.user.name}</strong>
                                    <small>
                                        {portal === 'partner'
                                            ? 'Mitra HoneyCommerce'
                                            : 'Administrator'}
                                    </small>
                                </span>
                                <ChevronDown size={17} />
                            </button>
                            {profileOpen && (
                                <div className="honey-popover">
                                    <Link href={profile()}>
                                        Pengaturan profil
                                    </Link>
                                    <button
                                        onClick={() =>
                                            router.post(logout.url())
                                        }
                                    >
                                        Keluar
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>
                <div className={isDashboard ? undefined : 'honey-page-content'}>
                    {!isDashboard && breadcrumbs.length > 0 && (
                        <div className="honey-page-breadcrumbs">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    )}
                    {children}
                </div>
            </div>
            <button
                className="honey-mobile-close"
                aria-label="Tutup menu"
                hidden={!sidebarOpen}
                onClick={() => setSidebarOpen(false)}
            >
                <X size={22} />
            </button>
        </div>
    );
}
