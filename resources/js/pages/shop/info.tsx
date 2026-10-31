import { Head } from '@inertiajs/react';
import { ShopHeader, ShopFooter } from '@/components/commerce-ui';
export default function Info({ title, body }: { title: string; body: string }) {
    return (
        <>
            <Head title={title} />
            <ShopHeader />
            <main className="mx-auto min-h-[55vh] max-w-3xl px-5 py-12">
                <h1 className="mb-6 text-3xl font-bold">{title}</h1>
                <p className="leading-8">{body}</p>
            </main>
            <ShopFooter />
        </>
    );
}
