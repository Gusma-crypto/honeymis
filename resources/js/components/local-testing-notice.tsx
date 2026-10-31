export function LocalTestingNotice({ active }: { active?: boolean }) {
    if (!active) return null;
    return (
        <p
            role="status"
            className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Mode uji coba lokal: Anda dapat langsung belanja tanpa kode
            konfirmasi email atau WhatsApp. Gunakan kata sandi khusus akun
            HoneyCommerce.
        </p>
    );
}
