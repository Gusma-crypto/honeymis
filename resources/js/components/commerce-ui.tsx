import { CircleUserRound, LogIn, ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLogoIcon from '@/components/app-logo-icon';
import { about, contact, faq, privacy, terms, returns } from '@/routes/info';
import { Link, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import type { ReactNode } from 'react';
import { index as catalog } from '@/routes/catalog';
import { index as cart } from '@/routes/cart';
import { index as orders } from '@/routes/orders';
import { dashboard as partnerDashboard } from '@/routes/partner';
import { dashboard, login, register } from '@/routes';

export const money = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
export const inputClass =
    'w-full rounded-md border border-input bg-background px-3 py-2 text-sm transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25 disabled:cursor-not-allowed disabled:opacity-50';
export function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        <div
            role="alert"
            className="flex flex-col gap-1 text-sm text-red-600 dark:text-red-400"
        >
            {Object.entries(errors).map(([key, error]) => (
                <p key={key}>{error}</p>
            ))}
        </div>
    );
}
export function Field({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="flex flex-col gap-2 text-sm font-medium">
            {label}
            {children}
        </label>
    );
}
export type PageLinks = {
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export function Pager({ page }: { page: PageLinks }) {
    return (
        <nav aria-label="Navigasi halaman" className="flex gap-4 text-sm">
            {page.prev_page_url && (
                <Link href={page.prev_page_url}>Sebelumnya</Link>
            )}
            <span>
                {page.current_page} / {page.last_page}
            </span>
            {page.next_page_url && (
                <Link href={page.next_page_url}>Berikutnya</Link>
            )}
        </nav>
    );
}
function ShopIconLink({
    href,
    label,
    children,
}: {
    href: string;
    label: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="store-icon-link size-11 rounded-full border border-primary/15 bg-accent/35 text-primary hover:border-primary/40 hover:bg-accent"
                >
                    <Link href={href} aria-label={label} title={label}>
                        {children}
                    </Link>
                </Button>
            </TooltipTrigger>
            <TooltipContent className="bg-emerald-950 text-white [&>svg]:bg-emerald-950 [&>svg]:fill-emerald-950">
                {label}
            </TooltipContent>
        </Tooltip>
    );
}
export function ShopHeader() {
    const { auth } = usePage().props;
    return (
        <header className="border-b border-emerald-200/60 bg-card dark:border-emerald-900/60">
            <nav
                aria-label="Navigasi toko"
                className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-5 gap-y-3 px-5 py-4"
            >
                <Link
                    href={catalog()}
                    className="group flex items-center gap-2.5 text-lg font-bold tracking-tight text-primary sm:text-xl"
                >
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent/60">
                        <AppLogoIcon className="size-7" />
                    </span>
                    HoneyCommerce
                </Link>
                <div className="flex flex-wrap items-center gap-2 text-sm sm:gap-3">
                    <Link
                        href={catalog()}
                        className="rounded-full px-3 py-2 font-medium transition-colors hover:bg-accent hover:text-primary"
                    >
                        Katalog
                    </Link>
                    {auth.user && (
                        <>
                            <Link
                                href={orders()}
                                className="rounded-full px-3 py-2 transition-colors hover:bg-accent hover:text-primary"
                            >
                                Pesanan saya
                            </Link>
                            <Link
                                href={partnerDashboard()}
                                className="rounded-full px-3 py-2 transition-colors hover:bg-accent hover:text-primary"
                            >
                                Mitra
                            </Link>
                        </>
                    )}
                    <span
                        className="mx-1 hidden h-6 w-px bg-border sm:block"
                        aria-hidden="true"
                    />
                    <ShopIconLink href={cart.url()} label="Keranjang belanja">
                        <ShoppingCart aria-hidden="true" className="size-5" />
                    </ShopIconLink>
                    {auth.user ? (
                        <ShopIconLink
                            href={dashboard.url()}
                            label="Akun dan dashboard"
                        >
                            <CircleUserRound
                                aria-hidden="true"
                                className="size-5"
                            />
                        </ShopIconLink>
                    ) : (
                        <>
                            <ShopIconLink
                                href={login.url()}
                                label="Masuk ke akun"
                            >
                                <LogIn aria-hidden="true" className="size-5" />
                            </ShopIconLink>
                            <Button
                                asChild
                                className="store-button rounded-full px-5"
                            >
                                <Link href={register()}>Daftar</Link>
                            </Button>
                        </>
                    )}
                </div>
            </nav>
        </header>
    );
}
export function useRequestKey() {
    const key = useRef<string | null>(null);
    return {
        get: () => {
            key.current ??= crypto.randomUUID();
            return key.current;
        },
        clear: () => {
            key.current = null;
        },
    };
}

export function ShopFooter() {
    return (
        <footer className="mt-12 border-t bg-card px-5 py-8">
            <nav
                aria-label="Informasi toko"
                className="mx-auto flex max-w-6xl flex-wrap gap-5 text-sm"
            >
                {[
                    { title: 'Tentang', href: about() },
                    { title: 'Kontak', href: contact() },
                    { title: 'FAQ', href: faq() },
                    { title: 'Privasi', href: privacy() },
                    { title: 'Ketentuan', href: terms() },
                    { title: 'Retur', href: returns() },
                ].map((l) => (
                    <Link className="underline" key={l.title} href={l.href}>
                        {l.title}
                    </Link>
                ))}
            </nav>
        </footer>
    );
}
