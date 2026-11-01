import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { HoneyAdminLayout } from '@/components/honey-admin-layout';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const page = usePage();
    const hasAdminAccess = page.props.auth.permissions.some((permission) =>
        [
            'orders.manage',
            'payments.verify',
            'catalog.manage',
            'partners.manage',
            'payouts.review',
            'reports.view',
            'users.manage-access',
            'audit.view',
            'inventory.manage',
            'refunds.manage',
            'commission-rules.manage',
        ].includes(permission),
    );
    if (hasAdminAccess) {
        return (
            <HoneyAdminLayout breadcrumbs={breadcrumbs}>
                {children}
            </HoneyAdminLayout>
        );
    }
    if (
        page.props.auth.permissions.includes('referrals.view-own') ||
        page.component.startsWith('partner/')
    ) {
        return (
            <HoneyAdminLayout portal="partner" breadcrumbs={breadcrumbs}>
                {children}
            </HoneyAdminLayout>
        );
    }
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="dashboard-surface min-w-0 overflow-x-clip"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
