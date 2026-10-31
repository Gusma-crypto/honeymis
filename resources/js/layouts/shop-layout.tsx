import type { PropsWithChildren } from 'react';

export default function ShopLayout({ children }: PropsWithChildren) {
    return (
        <div className="storefront min-h-screen bg-background text-foreground">
            {children}
        </div>
    );
}
