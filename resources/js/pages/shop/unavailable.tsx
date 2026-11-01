import { Head, Link } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import { login } from '@/routes';

export default function Unavailable({ brandName }: { brandName: string }) {
    return (
        <main className="grid min-h-screen place-items-center bg-slate-50 p-6 text-slate-900">
            <Head title="Pemeliharaan" />
            <section className="max-w-lg rounded-3xl border bg-white p-10 text-center shadow-sm">
                <Wrench className="mx-auto mb-6 size-12 text-amber-500" />
                <h1 className="text-2xl font-bold">Kami segera kembali</h1>
                <p className="mt-4 text-slate-600">
                    {brandName} sedang dalam pemeliharaan. Silakan coba kembali
                    beberapa saat lagi.
                </p>
                <Link
                    href={login()}
                    className="mt-8 inline-block text-sm font-semibold text-blue-600"
                >
                    Masuk sebagai administrator
                </Link>
            </section>
        </main>
    );
}
