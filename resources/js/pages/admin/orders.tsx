import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ShoppingCart,
    CircleCheck,
    CircleX,
    Truck,
    Download,
    Plus,
    Search,
    Filter,
    CalendarDays,
    Eye,
    MoreVertical,
    Clock3,
    Check,
    X,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { MenuTitle } from '@/components/menu-title';
import { MemberAvatar } from '@/components/member-avatar';
import { RecordDialog } from '@/components/data-list';
import { Button } from '@/components/ui/button';
import {
    Errors,
    Field,
    inputClass,
    money,
    useRequestKey,
} from '@/components/commerce-ui';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { confirmAction } from '@/lib/sweet-alert';
import { index, exportMethod, importTracking } from '@/routes/admin/orders';
import { show, update } from '@/routes/orders';
import { index as catalog } from '@/routes/catalog';
import type { Auth } from '@/types/auth';
import type { Paginated } from '@/types/commerce';

type Order = {
    id: number;
    order_number: string;
    status: string;
    grand_total: number;
    placed_at: string;
    customer: {
        name: string;
        avatar: string | null;
        gender: string | null;
        rank: string | null;
    };
    payment_status: string;
    shipment_status: string | null;
    courier: string | null;
    tracking_number: string | null;
    item_count: number;
    fraud_hold: boolean;
    items: { name: string; quantity: number; image_url: string | null }[];
};
type Filters = {
    search: string;
    status: string;
    period: string;
    payment: string;
    shipping: string;
    per_page: number;
};
type OrdersPage = Paginated<Order> & {
    total: number;
    from: number | null;
    to: number | null;
};
const numeric = (value: number) => new Intl.NumberFormat('id-ID').format(value);
const paymentLabels: Record<string, string> = {
    pending: 'Menunggu',
    submitted: 'Verifikasi',
    verified: 'Lunas',
    rejected: 'Ditolak',
    refunded: 'Dikembalikan',
};
function PaymentBadge({ order }: { order: Order }) {
    const status =
        order.status === 'cancelled' ? 'cancelled' : order.payment_status;
    const Icon =
        status === 'verified'
            ? Check
            : ['cancelled', 'rejected', 'refunded'].includes(status)
              ? X
              : Clock3;
    return (
        <span className={`order-pill order-payment-${status}`}>
            <Icon />
            {status === 'cancelled'
                ? 'Dibatalkan'
                : (paymentLabels[status] ?? status)}
        </span>
    );
}
function ShippingBadge({ order }: { order: Order }) {
    if (!order.shipment_status)
        return <span className="order-no-shipment">—</span>;
    return (
        <span className={`order-pill order-shipping-${order.shipment_status}`}>
            {order.shipment_status === 'delivered' ? <Check /> : <Truck />}
            {order.shipment_status === 'delivered' ? 'Selesai' : 'Dikirim'}
        </span>
    );
}
function TrackingImport() {
    const form = useForm<{ file: File | null }>({ file: null });
    return (
        <RecordDialog
            kind="add"
            label="Import Resi"
            title="Import resi pengiriman"
            description="Unggah resi untuk pesanan berstatus Diproses yang pembayarannya sudah terverifikasi."
        >
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(importTracking.url(), { preserveScroll: true });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    CSV maksimal 2 MB dan 100 baris. Semua baris divalidasi
                    dalam satu transaksi; jika satu gagal, perubahan lainnya
                    dibatalkan. Import ulang resi yang sama tidak menggandakan
                    pengiriman.
                </p>
                <a
                    className="text-primary underline"
                    href={exportMethod.url({ query: { template: 1 } })}
                >
                    Unduh template CSV resi
                </a>
                <input
                    type="file"
                    aria-label="File resi CSV"
                    accept=".csv,.txt"
                    required
                    onChange={(event) =>
                        form.setData('file', event.target.files?.[0] ?? null)
                    }
                />
                <Errors errors={form.errors} />
                <Button disabled={form.processing || !form.data.file}>
                    {form.processing
                        ? 'Memproses...'
                        : 'Validasi dan proses resi'}
                </Button>
            </form>
        </RecordDialog>
    );
}
function ShippingAction({ order }: { order: Order }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const key = useRequestKey();
    const form = useForm({
        operation: order.status === 'paid' ? 'process' : 'ship',
        courier: '',
        tracking_number: '',
        idempotency_key: '',
    });
    if (
        !auth.permissions.includes('orders.manage') ||
        !['paid', 'processing'].includes(order.status)
    )
        return (
            <Link
                className="product-action"
                href={show.url(order.id) + '#pengiriman'}
                title="Detail pengiriman"
                aria-label={`Pengiriman ${order.order_number}`}
            >
                <Truck />
            </Link>
        );
    return (
        <div className="order-shipping-action">
            <RecordDialog
                kind="edit"
                label=""
                title={`Pengiriman ${order.order_number}`}
                description="Gunakan kurir dan nomor resi yang benar untuk mengirim pesanan."
            >
                <form
                    className="grid gap-4"
                    onSubmit={async (event) => {
                        event.preventDefault();
                        if (
                            !(await confirmAction(
                                order.status === 'paid'
                                    ? 'Proses pesanan?'
                                    : 'Kirim pesanan?',
                                order.status === 'paid'
                                    ? 'Pesanan akan ditandai sedang diproses.'
                                    : 'Stok akan dikurangi sesuai pesanan dan resi dicatat.',
                                false,
                            ))
                        )
                            return;
                        form.transform((data) => ({
                            ...data,
                            idempotency_key: key.get(),
                        }));
                        form.patch(update.url(order.id), {
                            onSuccess: key.clear,
                        });
                    }}
                >
                    <p>
                        {order.status === 'paid'
                            ? 'Pembayaran sudah lunas. Mulai proses persiapan pesanan sebelum pengiriman.'
                            : 'Pesanan siap dikirim. Lengkapi kurir dan nomor resi.'}
                    </p>
                    {order.status === 'processing' && (
                        <>
                            <Field label="Kurir">
                                <input
                                    className={inputClass}
                                    required
                                    maxLength={100}
                                    value={form.data.courier}
                                    onChange={(event) =>
                                        form.setData(
                                            'courier',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Nomor resi">
                                <input
                                    className={inputClass}
                                    required
                                    maxLength={100}
                                    value={form.data.tracking_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'tracking_number',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </>
                    )}
                    <Errors errors={form.errors} />
                    <Button disabled={form.processing}>
                        {order.status === 'paid'
                            ? 'Mulai proses pesanan'
                            : 'Simpan dan kirim pesanan'}
                    </Button>
                </form>
            </RecordDialog>
            <Truck className="order-shipping-action-icon" aria-hidden="true" />
        </div>
    );
}
export default function AdminOrders({
    orders,
    filters,
    counts,
    total,
}: {
    orders: OrdersPage;
    filters: Filters;
    counts: Record<string, number>;
    total: number;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [search, setSearch] = useState(filters.search);
    const [period, setPeriod] = useState(filters.period);
    const [payment, setPayment] = useState(filters.payment);
    const [shipping, setShipping] = useState(filters.shipping);
    const apply = (changes: Partial<Filters> = {}) =>
        router.get(
            index.url(),
            { ...filters, search, period, payment, shipping, ...changes },
            { preserveScroll: true, preserveState: true },
        );
    const stats = [
        {
            label: 'Total Pesanan',
            value: total,
            icon: ShoppingCart,
            tone: 'blue',
        },
        {
            label: 'Pesanan Selesai',
            value: counts.completed ?? 0,
            icon: CircleCheck,
            tone: 'green',
        },
        {
            label: 'Dalam Pengiriman',
            value: counts.shipped ?? 0,
            icon: Truck,
            tone: 'gold',
        },
        {
            label: 'Pesanan Dibatalkan',
            value: counts.cancelled ?? 0,
            icon: CircleX,
            tone: 'pink',
        },
    ];
    const tabs = [
        ['', 'Semua Pesanan', total],
        ['pending_payment', 'Menunggu Pembayaran', counts.pending_payment ?? 0],
        ['processing', 'Diproses', counts.processing ?? 0],
        ['shipped', 'Dikirim', counts.shipped ?? 0],
        ['completed', 'Selesai', counts.completed ?? 0],
        ['cancelled', 'Dibatalkan', counts.cancelled ?? 0],
        ['refunded', 'Pengembalian', counts.refunded ?? 0],
    ] as const;
    return (
        <>
            <Head title="Pesanan" />
            <main className="product-workspace order-workspace">
                <header className="product-heading">
                    <div>
                        <MenuTitle icon={ShoppingCart} tone="amber">
                            Pesanan
                        </MenuTitle>
                        <p>
                            Kelola semua pesanan produk madu dari pelanggan dan
                            member MLM.
                        </p>
                    </div>
                    <div className="product-heading-actions">
                        <a
                            className="product-secondary"
                            href={exportMethod.url({ query: { ...filters } })}
                        >
                            <Download />
                            Export
                        </a>
                        {auth.permissions.includes('orders.manage') && (
                            <>
                                <TrackingImport />
                                <Link
                                    className="product-primary"
                                    href={catalog.url()}
                                    title="Pilih produk lalu lanjutkan checkout untuk membuat pesanan baru"
                                >
                                    <Plus />
                                    Buat Pesanan
                                </Link>
                            </>
                        )}
                    </div>
                </header>
                <section
                    className="product-kpis"
                    aria-label="Ringkasan pesanan"
                >
                    {stats.map(({ label, value, icon: Icon, tone }) => (
                        <article className={`product-kpi ${tone}`} key={label}>
                            <span className="product-kpi-icon">
                                <Icon />
                            </span>
                            <div>
                                <h2>{label}</h2>
                                <strong>{numeric(value)}</strong>
                                <p>Data pesanan tersimpan</p>
                            </div>
                        </article>
                    ))}
                </section>
                <section className="order-filters">
                    <nav
                        className="order-status-tabs"
                        aria-label="Status pesanan"
                    >
                        {tabs.map(([value, label, count]) => (
                            <button
                                className={
                                    filters.status === value ? 'selected' : ''
                                }
                                key={value}
                                onClick={() => apply({ status: value })}
                            >
                                {label} ({numeric(count)})
                            </button>
                        ))}
                    </nav>
                    <form
                        className="order-filter-row"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <div className="product-search">
                            <Search />
                            <input
                                type="search"
                                aria-label="Cari pesanan, pelanggan, atau produk"
                                placeholder="Cari kode pesanan, nama member, atau produk..."
                                maxLength={100}
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                        <label>
                            Periode
                            <div className="order-month">
                                <CalendarDays />
                                <input
                                    type="month"
                                    aria-label="Periode pesanan"
                                    value={period}
                                    onChange={(event) =>
                                        setPeriod(event.target.value)
                                    }
                                />
                            </div>
                        </label>
                        <label>
                            Status Pembayaran
                            <select
                                value={payment}
                                onChange={(event) =>
                                    setPayment(event.target.value)
                                }
                            >
                                <option value="">Semua</option>
                                {Object.entries(paymentLabels).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                        <label>
                            Status Pengiriman
                            <select
                                value={shipping}
                                onChange={(event) =>
                                    setShipping(event.target.value)
                                }
                            >
                                <option value="">Semua</option>
                                <option value="unshipped">Belum dikirim</option>
                                <option value="shipped">Dikirim</option>
                                <option value="delivered">Diterima</option>
                            </select>
                        </label>
                        <button type="submit" className="product-primary">
                            <Filter />
                            Filter
                        </button>
                    </form>
                    {(filters.search ||
                        filters.period ||
                        filters.payment ||
                        filters.shipping ||
                        filters.status) && (
                        <button
                            className="order-reset"
                            onClick={() => {
                                setSearch('');
                                setPeriod('');
                                setPayment('');
                                setShipping('');
                                apply({
                                    search: '',
                                    period: '',
                                    payment: '',
                                    shipping: '',
                                    status: '',
                                });
                            }}
                        >
                            Reset semua filter
                        </button>
                    )}
                </section>
                <section className="order-table-panel">
                    <div className="order-table-scroll">
                        <table className="order-table">
                            <thead>
                                <tr>
                                    {[
                                        '#',
                                        'Kode Pesanan',
                                        'Tanggal',
                                        'Pelanggan / Member',
                                        'Produk',
                                        'Total',
                                        'Pembayaran',
                                        'Pengiriman',
                                        'Aksi',
                                    ].map((label) => (
                                        <th key={label}>{label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="order-empty">
                                            <ShoppingCart />
                                            <strong>
                                                Pesanan belum ditemukan
                                            </strong>
                                            <span>
                                                Sesuaikan filter atau buat
                                                pesanan melalui katalog produk.
                                            </span>
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order, index) => (
                                        <tr key={order.id}>
                                            <td>
                                                <span
                                                    className={`order-row-number position-${index + 1}`}
                                                >
                                                    {(orders.from ?? 1) + index}
                                                </span>
                                            </td>
                                            <td>
                                                <Link
                                                    className="order-number"
                                                    href={show.url(order.id)}
                                                >
                                                    {order.order_number}
                                                </Link>
                                                {order.fraud_hold && (
                                                    <small className="order-held">
                                                        Ditahan untuk
                                                        pemeriksaan
                                                    </small>
                                                )}
                                            </td>
                                            <td className="order-date">
                                                {new Date(
                                                    order.placed_at,
                                                ).toLocaleDateString('id-ID', {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric',
                                                    timeZone: 'Asia/Jakarta',
                                                })}
                                                <span>
                                                    {new Date(
                                                        order.placed_at,
                                                    ).toLocaleTimeString(
                                                        'id-ID',
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                            timeZone:
                                                                'Asia/Jakarta',
                                                        },
                                                    )}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="order-customer">
                                                    <MemberAvatar
                                                        name={
                                                            order.customer.name
                                                        }
                                                        gender={
                                                            order.customer
                                                                .gender
                                                        }
                                                        photo={
                                                            order.customer
                                                                .avatar
                                                        }
                                                    />
                                                    <div>
                                                        <strong>
                                                            {
                                                                order.customer
                                                                    .name
                                                            }
                                                        </strong>
                                                        {order.customer.rank ? (
                                                            <span
                                                                className={`network-rank rank-${order.customer.rank}`}
                                                            >
                                                                Member -{' '}
                                                                {
                                                                    order
                                                                        .customer
                                                                        .rank
                                                                }
                                                            </span>
                                                        ) : (
                                                            <small>
                                                                Pelanggan
                                                            </small>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="order-product-images">
                                                    {order.items.map(
                                                        (item, itemIndex) => (
                                                            <Link
                                                                key={itemIndex}
                                                                href={show.url(
                                                                    order.id,
                                                                )}
                                                                title={`${item.name} (${item.quantity})`}
                                                            >
                                                                <img
                                                                    src={
                                                                        item.image_url ??
                                                                        '/honey-product.jpg'
                                                                    }
                                                                    alt={
                                                                        item.name
                                                                    }
                                                                    onError={(
                                                                        event,
                                                                    ) => {
                                                                        if (
                                                                            !event.currentTarget.src.endsWith(
                                                                                '/honey-product.jpg',
                                                                            )
                                                                        )
                                                                            event.currentTarget.src =
                                                                                '/honey-product.jpg';
                                                                    }}
                                                                />
                                                            </Link>
                                                        ),
                                                    )}
                                                    {order.item_count > 2 && (
                                                        <Link
                                                            className="order-item-more"
                                                            href={show.url(
                                                                order.id,
                                                            )}
                                                        >
                                                            +
                                                            {order.item_count -
                                                                2}
                                                        </Link>
                                                    )}
                                                    {order.item_count === 0 && (
                                                        <span>—</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="order-total">
                                                {money(order.grand_total)}
                                            </td>
                                            <td>
                                                <PaymentBadge order={order} />
                                            </td>
                                            <td>
                                                <ShippingBadge order={order} />
                                            </td>
                                            <td>
                                                <div className="order-actions">
                                                    <Link
                                                        className="product-action"
                                                        href={show.url(
                                                            order.id,
                                                        )}
                                                        title="Lihat detail"
                                                        aria-label={`Detail ${order.order_number}`}
                                                    >
                                                        <Eye />
                                                    </Link>
                                                    <ShippingAction
                                                        order={order}
                                                    />
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <button
                                                                className="order-more"
                                                                aria-label={`Menu ${order.order_number}`}
                                                            >
                                                                <MoreVertical />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={show.url(
                                                                        order.id,
                                                                    )}
                                                                >
                                                                    Detail
                                                                    pesanan
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        show.url(
                                                                            order.id,
                                                                        ) +
                                                                        '#pembayaran'
                                                                    }
                                                                >
                                                                    Pembayaran
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        show.url(
                                                                            order.id,
                                                                        ) +
                                                                        '#pengiriman'
                                                                    }
                                                                >
                                                                    Pengiriman
                                                                    dan resi
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <footer className="product-pagination">
                        <p>
                            Menampilkan {orders.from ?? 0} – {orders.to ?? 0}{' '}
                            dari {numeric(orders.total)} pesanan
                        </p>
                        <label>
                            Tampilkan
                            <select
                                aria-label="Jumlah pesanan per halaman"
                                value={filters.per_page}
                                onChange={(event) =>
                                    apply({
                                        per_page: Number(event.target.value),
                                    })
                                }
                            >
                                {[8, 16, 24, 48].map((size) => (
                                    <option key={size}>{size}</option>
                                ))}
                            </select>
                            per halaman
                        </label>
                        <nav aria-label="Halaman pesanan">
                            <button
                                disabled={!orders.prev_page_url}
                                onClick={() =>
                                    router.get(
                                        orders.prev_page_url!,
                                        {},
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                aria-label="Halaman sebelumnya"
                            >
                                <ChevronLeft />
                            </button>
                            {Array.from(
                                { length: Math.min(5, orders.last_page) },
                                (_, offset) =>
                                    Math.max(
                                        1,
                                        Math.min(
                                            orders.current_page - 2,
                                            orders.last_page - 4,
                                        ),
                                    ) + offset,
                            ).map((page) => (
                                <Link
                                    key={page}
                                    className={
                                        page === orders.current_page
                                            ? 'selected'
                                            : ''
                                    }
                                    href={index.url({
                                        query: { ...filters, page },
                                    })}
                                    preserveState
                                    preserveScroll
                                    aria-current={
                                        page === orders.current_page
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {page}
                                </Link>
                            ))}
                            <button
                                disabled={!orders.next_page_url}
                                onClick={() =>
                                    router.get(
                                        orders.next_page_url!,
                                        {},
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                aria-label="Halaman berikutnya"
                            >
                                <ChevronRight />
                            </button>
                        </nav>
                    </footer>
                </section>
            </main>
        </>
    );
}
