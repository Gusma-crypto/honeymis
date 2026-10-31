import { useState } from 'react';
import { inputClass } from '@/components/commerce-ui';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Boxes,
    ClipboardList,
    Coins,
    LayoutDashboard,
    Package,
    Settings2,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { commerceSettings } from '@/routes/admin';
import { index as audit } from '@/routes/admin/audit';
import { index as products } from '@/routes/admin/catalog';
import { index as rules } from '@/routes/admin/commission-rules';
import { index as customers } from '@/routes/admin/customers';
import { index as inventory } from '@/routes/admin/inventory';
import { index as orders } from '@/routes/admin/orders';
import { index as partners } from '@/routes/admin/partners';
import { index as payouts } from '@/routes/admin/payouts';
import { index as refunds } from '@/routes/admin/refunds';
import { index as reports } from '@/routes/admin/reports';
import { index as access } from '@/routes/admin/users';
import { index as cart } from '@/routes/cart';
import { index as catalog } from '@/routes/catalog';
import { index as ownOrders } from '@/routes/orders';
import { dashboard as partner } from '@/routes/partner';
import { index as ownPayouts } from '@/routes/payouts';
import { index as wallet } from '@/routes/wallet';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const [menuSearch, setMenuSearch] = useState('');
    const [menuSection, setMenuSection] = useState('');
    const matching = (items: NavItem[]) =>
        items.filter((item) =>
            item.title
                .toLocaleLowerCase('id-ID')
                .includes(menuSearch.trim().toLocaleLowerCase('id-ID')),
        );

    const { auth } = usePage().props;
    const can = (permission: string) => auth.permissions.includes(permission);
    const operational: NavItem[] = [];
    const finance: NavItem[] = [];
    const administration: NavItem[] = [];
    if (can('catalog.manage'))
        operational.push({
            title: 'Katalog produk',
            href: products(),
            icon: Package,
        });
    if (can('inventory.manage'))
        operational.push({
            title: 'Persediaan',
            href: inventory(),
            icon: Boxes,
        });
    if (can('orders.manage') || can('payments.verify'))
        operational.push({
            title: 'Pesanan',
            href: orders(),
            icon: ShoppingBag,
        });
    if (can('orders.manage'))
        operational.push({
            title: 'Pelanggan',
            href: customers(),
            icon: Users,
        });
    if (can('partners.manage'))
        operational.push({ title: 'Mitra', href: partners(), icon: Users });
    if (can('commission-rules.manage'))
        finance.push({ title: 'Aturan komisi', href: rules(), icon: Coins });
    if (can('payouts.review'))
        finance.push({ title: 'Payout mitra', href: payouts(), icon: Wallet });
    if (can('refunds.manage'))
        finance.push({ title: 'Refund', href: refunds(), icon: ArrowUpRight });
    if (can('reports.view'))
        finance.push({
            title: 'Laporan',
            href: reports(),
            icon: ClipboardList,
        });
    if (can('users.manage-access'))
        administration.push({
            title: 'Akses pengguna',
            href: access(),
            icon: ShieldCheck,
        });
    if (can('audit.view'))
        administration.push({
            title: 'Audit log',
            href: audit(),
            icon: ClipboardList,
        });
    if (can('catalog.manage') || can('commission-rules.manage'))
        administration.push({
            title: 'Konfigurasi toko',
            href: commerceSettings(),
            icon: Settings2,
        });
    const hasAdminNavigation =
        operational.length + finance.length + administration.length > 0;
    const personal: NavItem[] = [
        { title: 'Belanja', href: catalog(), icon: ShoppingBag },
        { title: 'Keranjang', href: cart(), icon: ShoppingCart },
        { title: 'Pesanan saya', href: ownOrders(), icon: Package },
        { title: 'Portal mitra', href: partner(), icon: Users },
    ];
    if (can('wallets.view-own'))
        personal.push(
            { title: 'Dompet komisi', href: wallet(), icon: Wallet },
            { title: 'Payout saya', href: ownPayouts(), icon: Coins },
        );
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border/70 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="gap-4 py-4">
                <div className="grid gap-2 px-3 group-data-[collapsible=icon]:hidden">
                    <input
                        type="search"
                        aria-label="Cari menu navigasi"
                        placeholder="Cari menu..."
                        className={`${inputClass} bg-sidebar text-sidebar-foreground`}
                        value={menuSearch}
                        onChange={(e) => setMenuSearch(e.target.value)}
                    />
                    {hasAdminNavigation && (
                        <select
                            aria-label="Filter kelompok navigasi"
                            className={`${inputClass} bg-sidebar text-sidebar-foreground`}
                            value={menuSection}
                            onChange={(e) => setMenuSection(e.target.value)}
                        >
                            <option value="">Semua kelompok</option>
                            <option value="operations">Operasional</option>
                            <option value="finance">Keuangan</option>
                            <option value="administration">Administrasi</option>
                        </select>
                    )}
                    {menuSearch &&
                        matching([
                            ...operational,
                            ...finance,
                            ...administration,
                            ...personal,
                        ]).length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                Menu tidak ditemukan.
                            </p>
                        )}
                </div>
                <NavMain
                    title="Ringkasan"
                    items={[
                        {
                            title: 'Dashboard',
                            href: dashboard(),
                            icon: LayoutDashboard,
                        },
                    ]}
                />
                {hasAdminNavigation ? (
                    <>
                        {(!menuSection || menuSection === 'operations') &&
                            matching(operational).length > 0 && (
                                <NavMain
                                    title="Operasional"
                                    items={matching(operational)}
                                />
                            )}
                        {(!menuSection || menuSection === 'finance') &&
                            matching(finance).length > 0 && (
                                <NavMain
                                    title="Keuangan"
                                    items={matching(finance)}
                                />
                            )}
                        {(!menuSection || menuSection === 'administration') &&
                            matching(administration).length > 0 && (
                                <NavMain
                                    title="Administrasi"
                                    items={matching(administration)}
                                />
                            )}
                    </>
                ) : (
                    <NavMain title="Akun saya" items={matching(personal)} />
                )}
            </SidebarContent>
            <SidebarFooter className="gap-2 border-t border-sidebar-border/70 p-3">
                {hasAdminNavigation && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Lihat toko">
                                <Link href={catalog()}>
                                    <ShoppingBag />
                                    <span>Lihat toko</span>
                                    <ArrowUpRight className="ml-auto size-3.5" />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
