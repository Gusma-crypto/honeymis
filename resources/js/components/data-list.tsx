import { Link, router } from '@inertiajs/react';
import {
    CircleCheck,
    Clock3,
    CircleX,
    Eye,
    Pencil,
    Plus,
    Rows3,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { Key, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function DataTable<T>({
    rows,
    columns,
    rowKey,
    label = 'Daftar data',
    empty = 'Belum ada data.',
    searchable = false,
    localFilter,
}: {
    rows: T[];
    columns: {
        header: string;
        render: (row: T, index: number) => ReactNode;
        className?: string;
    }[];
    rowKey: (row: T, index: number) => Key;
    label?: string;
    empty?: string;
    searchable?: boolean;
    localFilter?: {
        label: string;
        options: Record<string, string>;
        value: (row: T) => string;
    };
}) {
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('');
    const term = search.trim().toLocaleLowerCase('id-ID');
    const visibleRows = rows
        .map((row, index) => ({ row, index }))
        .filter(
            ({ row }) =>
                (!term ||
                    JSON.stringify(row)
                        .toLocaleLowerCase('id-ID')
                        .includes(term)) &&
                (!filter || localFilter?.value(row) === filter),
        );
    return (
        <div className="min-w-0 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            {(searchable || localFilter) && (
                <div className="flex flex-wrap gap-3 border-b bg-muted/20 p-3">
                    {searchable && (
                        <label className="relative min-w-48 flex-1">
                            <span className="sr-only">
                                Cari {label} pada data yang ditampilkan
                            </span>
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <input
                                type="search"
                                className="w-full rounded-md border bg-background py-2 pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder="Cari pada data yang ditampilkan..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </label>
                    )}
                    {localFilter && (
                        <label>
                            <span className="sr-only">{localFilter.label}</span>
                            <select
                                className="rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                value={filter}
                                onChange={(e) => setFilter(e.target.value)}
                            >
                                <option value="">
                                    Semua{' '}
                                    {localFilter.label.toLocaleLowerCase(
                                        'id-ID',
                                    )}
                                </option>
                                {Object.entries(localFilter.options).map(
                                    ([value, text]) => (
                                        <option value={value} key={value}>
                                            {text}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                    )}
                    {(search || filter) && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                setFilter('');
                            }}
                        >
                            Reset
                        </Button>
                    )}
                </div>
            )}
            <div className="overflow-x-auto">
                <table
                    aria-label={label}
                    className="w-full min-w-[600px] text-left text-sm"
                >
                    <thead className="border-b border-primary/15 bg-accent/60 text-xs text-accent-foreground">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.header}
                                    scope="col"
                                    className={`px-4 py-3 font-semibold ${column.className ?? ''}`}
                                >
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {visibleRows.map(({ row, index }) => (
                            <tr
                                key={rowKey(row, index)}
                                className="transition-colors even:bg-muted/20 hover:bg-accent/35"
                            >
                                {columns.map((column) => (
                                    <td
                                        className={`px-4 py-3 align-middle ${column.className ?? ''}`}
                                        key={column.header}
                                    >
                                        {column.render(row, index)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {visibleRows.length === 0 && (
                <div className="flex flex-col items-center gap-3 px-5 py-12 text-center text-sm text-muted-foreground">
                    <Rows3 className="size-7 opacity-50" />
                    <p>
                        {rows.length === 0
                            ? empty
                            : 'Tidak ada hasil yang sesuai. Ubah pencarian atau filter.'}
                    </p>
                </div>
            )}
            <div className="border-t bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                {visibleRows.length} dari {rows.length} data pada halaman ini
            </div>
        </div>
    );
}
export function RecordDialog({
    title,
    description,
    label,
    kind = 'view',
    disabled = false,
    children,
}: {
    title: string;
    description?: string;
    label?: string;
    kind?: 'view' | 'edit' | 'add';
    disabled?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    useEffect(() => {
        if (!open) return;
        return router.on('success', () => setOpen(false));
    }, [open]);
    const Icon = kind === 'add' ? Plus : kind === 'edit' ? Pencil : Eye;
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={kind === 'add' ? 'default' : 'outline'}
                    size="sm"
                    className={
                        kind === 'view'
                            ? 'border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100 hover:text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-900/50 dark:hover:text-sky-200'
                            : kind === 'edit'
                              ? 'border-primary/30 bg-accent/50 text-accent-foreground hover:bg-accent hover:text-accent-foreground'
                              : undefined
                    }
                    disabled={disabled}
                >
                    <Icon className="size-3.5" />
                    {label ??
                        (kind === 'add'
                            ? 'Tambah'
                            : kind === 'edit'
                              ? 'Edit'
                              : 'Detail')}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[88dvh] overflow-y-auto border-t-4 border-t-primary sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="text-primary">{title}</DialogTitle>
                    <DialogDescription>
                        {description ??
                            (kind === 'view'
                                ? 'Informasi lengkap data yang dipilih.'
                                : 'Lengkapi form, kemudian simpan perubahan.')}
                    </DialogDescription>
                </DialogHeader>
                {open && children}
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Tutup
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
export function DetailFields({ rows }: { rows: [string, ReactNode][] }) {
    return (
        <dl className="grid gap-4 rounded-xl border bg-muted/25 p-4 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <div className="min-w-0" key={label}>
                    <dt className="text-xs text-muted-foreground">{label}</dt>
                    <dd className="mt-1 text-sm font-medium break-words">
                        {value === null || value === undefined || value === ''
                            ? '-'
                            : value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
export function RowActions({ children }: { children: ReactNode }) {
    return <div className="flex flex-wrap items-center gap-2">{children}</div>;
}
export function DetailLink({
    href,
    label = 'Detail',
}: {
    href: string;
    label?: string;
}) {
    return (
        <Button
            variant="outline"
            size="sm"
            className="border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100 hover:text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-900/50 dark:hover:text-sky-200"
            asChild
        >
            <Link href={href}>
                <Eye className="size-3.5" />
                {label}
            </Link>
        </Button>
    );
}
export function StatusBadge({ status }: { status: string }) {
    const positive = [
        'active',
        'published',
        'verified',
        'paid',
        'delivered',
        'completed',
        'available',
    ];
    const negative = [
        'inactive',
        'suspended',
        'rejected',
        'cancelled',
        'failed',
        'refunded',
        'reversed',
    ];
    const labels: Record<string, string> = {
        active: 'Aktif',
        inactive: 'Nonaktif',
        draft: 'Draft',
        pending: 'Menunggu',
        submitted: 'Menunggu verifikasi',
        requested: 'Diajukan',
        approved: 'Disetujui',
        published: 'Dipublikasikan',
        retired: 'Dihentikan',
        suspended: 'Ditangguhkan',
        rejected: 'Ditolak',
        paid: 'Dibayar',
        pending_payment: 'Menunggu bayar',
        processing: 'Diproses',
        shipped: 'Dikirim',
        delivered: 'Diterima',
        completed: 'Selesai',
        cancelled: 'Dibatalkan',
        refunded: 'Refund',
        available: 'Tersedia',
        reserved: 'Direservasi',
        paid_out: 'Dicairkan',
        reversed: 'Dibalik',
        failed: 'Gagal',
        verified: 'Terverifikasi',
        processed: 'Diproses',
    };
    const Icon = positive.includes(status)
        ? CircleCheck
        : negative.includes(status)
          ? CircleX
          : Clock3;
    const color =
        status === 'completed'
            ? 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-200'
            : ['processing', 'submitted', 'approved', 'reserved'].includes(
                    status,
                )
              ? 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200'
              : positive.includes(status)
                ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                : negative.includes(status)
                  ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-200'
                  : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-medium whitespace-nowrap ${color}`}
        >
            <Icon className="size-3" aria-hidden="true" />
            {labels[status] ?? status}
        </span>
    );
}
