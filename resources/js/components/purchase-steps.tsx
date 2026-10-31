import { Check } from 'lucide-react';

export function PurchaseSteps({ current }: { current: 1 | 2 | 3 | 4 }) {
    return (
        <ol
            aria-label="Tahap pembelian"
            className="grid grid-cols-2 gap-3 rounded-2xl border bg-card p-4 sm:grid-cols-4"
        >
            {['Keranjang', 'Checkout', 'Pembayaran', 'Verifikasi'].map(
                (label, index) => (
                    <li
                        key={label}
                        aria-current={
                            index + 1 === current ? 'step' : undefined
                        }
                        className={`flex items-center gap-2 text-xs font-medium ${index + 1 <= current ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'}`}
                    >
                        <span
                            className={`flex size-7 shrink-0 items-center justify-center rounded-full ${index + 1 <= current ? 'bg-emerald-100 dark:bg-emerald-900/60' : 'bg-muted'}`}
                        >
                            {index + 1 < current ? (
                                <Check className="size-3.5" />
                            ) : (
                                index + 1
                            )}
                        </span>
                        {label}
                    </li>
                ),
            )}
        </ol>
    );
}
