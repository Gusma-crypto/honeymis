import { createInertiaApp } from '@inertiajs/react';
import { FlashNotifications } from '@/components/flash-notifications';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ShopLayout from '@/layouts/shop-layout';

const appName = import.meta.env.VITE_APP_NAME || 'HoneyCommerce';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('shop/') ||
                name === 'commerce/cart' ||
                name === 'commerce/checkout':
                return ShopLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <FlashNotifications />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#2563eb',
    },
});

// This will set light / dark mode on load...
initializeTheme();
