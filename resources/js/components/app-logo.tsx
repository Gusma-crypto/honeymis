import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, branding } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {branding?.logo_url ? (
                    <img
                        src={branding.logo_url}
                        alt=""
                        className="size-7 object-contain"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-sidebar-primary-foreground" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {branding?.brand_name || name}
                </span>
            </div>
        </>
    );
}
