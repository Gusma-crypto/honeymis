import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Megaphone,
    Users,
    ShoppingCart,
    Target,
    Link as LinkIcon,
    Copy,
    Share2,
    Download,
    Image,
    FileText,
    Palette,
    Trophy,
    Clock3,
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Gift,
    Star,
    BadgeCheck,
    ChartNoAxesCombined,
    Eye,
    Settings,
} from 'lucide-react';
import { MenuTitle } from '@/components/menu-title';
import { MemberAvatar } from '@/components/member-avatar';
import { RecordDialog, DataTable, StatusBadge } from '@/components/data-list';
import { Button } from '@/components/ui/button';
import { Errors, Field, inputClass, money } from '@/components/commerce-ui';
import { index, demo, qr, broadcast } from '@/routes/admin/marketing';
import {
    store as createCampaign,
    update as updateCampaign,
} from '@/routes/admin/marketing/campaigns';
import {
    store as uploadAsset,
    show as assetFile,
} from '@/routes/admin/marketing/assets';
import { visit } from '@/routes/marketing';
import { index as mlmSettings } from '@/routes/admin/mlm-settings';
import type { Auth } from '@/types/auth';
import type { Paginated } from '@/types/commerce';

type Campaign = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    product_id: number | null;
    product_name: string | null;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
};
type CampaignPage = Paginated<Campaign> & { total: number };
type MarketingAsset = {
    id: number;
    title: string;
    kind: string;
    mime_type: string;
    created_at: string;
};
type Draft = { id: number; title: string; message: string; audience: string };
type Marketer = {
    name: string;
    gender: string | null;
    avatar: string | null;
    orders: number;
    revenue: number;
};
type Trend = {
    label: string;
    visits: number;
    registrations: number;
    orders: number;
};
const samples = [
    { key: 'catalog', title: 'Katalog Produk', icon: FileText },
    { key: 'flyer', title: 'Flyer Promosi', icon: Image },
    { key: 'banner', title: 'Banner Digital', icon: Image },
    { key: 'post', title: 'Template Post', icon: Palette },
    { key: 'photo', title: 'Foto Produk', icon: Image },
] as const;
const dateInput = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('sv-SE', {
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              timeZone: 'Asia/Jakarta',
              hourCycle: 'h23',
          })
              .format(new Date(value.replace(' ', 'T') + 'Z'))
              .replace(' ', 'T')
        : '';
function CampaignForm({
    campaign,
    products,
}: {
    campaign?: Campaign;
    products: { id: number; name: string }[];
}) {
    const form = useForm({
        name: campaign?.name ?? '',
        description: campaign?.description ?? '',
        product_id: campaign?.product_id ?? (null as number | null),
        status: campaign?.status ?? 'draft',
        starts_at: dateInput(campaign?.starts_at ?? null),
        ends_at: dateInput(campaign?.ends_at ?? null),
    });
    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                if (campaign) form.put(updateCampaign.url(campaign.id));
                else form.post(createCampaign.url());
            }}
        >
            <Field label="Nama kampanye">
                <input
                    className={inputClass}
                    required
                    maxLength={150}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </Field>
            <Field label="Deskripsi promosi">
                <textarea
                    className={inputClass}
                    maxLength={1500}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Produk tujuan">
                    <select
                        className={inputClass}
                        value={form.data.product_id ?? ''}
                        onChange={(event) =>
                            form.setData(
                                'product_id',
                                event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            )
                        }
                    >
                        <option value="">Semua produk</option>
                        {products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Status kampanye">
                    <select
                        className={inputClass}
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData('status', event.target.value)
                        }
                    >
                        {Object.entries({
                            draft: 'Draf',
                            active: 'Aktif',
                            paused: 'Dijeda',
                            completed: 'Selesai',
                        }).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Mulai (WIB, opsional)">
                    <input
                        className={inputClass}
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                </Field>
                <Field label="Berakhir (WIB, opsional)">
                    <input
                        className={inputClass}
                        type="datetime-local"
                        value={form.data.ends_at}
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                    />
                </Field>
            </div>
            <p className="text-sm text-muted-foreground">
                Harga dan diskon tetap mengikuti katalog produk. Gunakan materi
                yang sesuai dengan penawaran produk yang berlaku.
            </p>
            <Errors errors={form.errors} />
            <Button disabled={form.processing}>Simpan kampanye</Button>
        </form>
    );
}
function AssetForm() {
    const form = useForm<{ title: string; file: File | null }>({
        title: '',
        file: null,
    });
    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(uploadAsset.url(), { forceFormData: true });
            }}
        >
            <Field label="Judul materi">
                <input
                    className={inputClass}
                    required
                    maxLength={150}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </Field>
            <Field label="File gambar, PDF, atau video MP4 (maks. 10 MB)">
                <input
                    required
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf,video/mp4"
                    onChange={(event) =>
                        form.setData('file', event.target.files?.[0] ?? null)
                    }
                />
            </Field>
            <Errors errors={form.errors} />
            <Button disabled={form.processing || !form.data.file}>
                Unggah materi
            </Button>
        </form>
    );
}
function BroadcastForm({ onCopy }: { onCopy: (value: string) => void }) {
    const form = useForm({ title: '', message: '', audience: 'partners' });
    return (
        <form
            className="marketing-broadcast-form"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(broadcast.url());
            }}
        >
            <h2>
                <Megaphone />
                Siapkan Pesan Broadcast
            </h2>
            <Field label="Judul">
                <input
                    required
                    className={inputClass}
                    maxLength={150}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </Field>
            <Field label="Audiens">
                <select
                    className={inputClass}
                    value={form.data.audience}
                    onChange={(event) =>
                        form.setData('audience', event.target.value)
                    }
                >
                    <option value="partners">Mitra</option>
                    <option value="customers">Pelanggan</option>
                    <option value="all">Mitra & pelanggan</option>
                </select>
            </Field>
            <Field label="Isi pesan">
                <textarea
                    required
                    className={inputClass}
                    rows={5}
                    maxLength={5000}
                    value={form.data.message}
                    onChange={(event) =>
                        form.setData('message', event.target.value)
                    }
                />
            </Field>
            <p className="text-sm text-muted-foreground">
                Simpan sebagai draf, lalu salin untuk dibagikan melalui kanal
                komunikasi Anda.
            </p>
            <Errors errors={form.errors} />
            <div className="flex gap-3">
                <Button disabled={form.processing}>Simpan Draf</Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!form.data.message}
                    onClick={() => onCopy(form.data.message)}
                >
                    <Copy />
                    Salin Pesan
                </Button>
            </div>
        </form>
    );
}
function CampaignChart({ trend }: { trend: Trend[] }) {
    const max = Math.max(
        1,
        ...trend.flatMap((point) => [
            point.visits,
            point.registrations,
            point.orders,
        ]),
    );
    const series = [
        { key: 'visits' as const, color: '#2180ff', name: 'Kunjungan' },
        {
            key: 'registrations' as const,
            color: '#ffad17',
            name: 'Pendaftaran',
        },
        { key: 'orders' as const, color: '#9252ff', name: 'Pembelian' },
    ];
    return (
        <div className="marketing-chart">
            <svg
                viewBox="0 0 500 180"
                role="img"
                aria-label="Kunjungan, pendaftaran, dan pembelian kampanye"
            >
                <title>Performa kampanye pada periode terpilih</title>
                {[0, 1, 2, 3, 4].map((step) => (
                    <g key={step}>
                        <line
                            x1="32"
                            x2="470"
                            y1={140 - step * 28}
                            y2={140 - step * 28}
                            stroke="#e3eafa"
                        />
                        <text
                            x="26"
                            y={144 - step * 28}
                            textAnchor="end"
                            fontSize="10"
                            fill="#596a91"
                        >
                            {Math.round((max * step) / 4)}
                        </text>
                    </g>
                ))}
                {trend.map((point, index) => (
                    <text
                        key={index}
                        x={40 + (index * 420) / Math.max(1, trend.length - 1)}
                        y="163"
                        textAnchor="middle"
                        fontSize="10"
                        fill="#26375e"
                    >
                        {point.label}
                    </text>
                ))}
                {series.map((series) => (
                    <g key={series.key}>
                        <polyline
                            points={trend
                                .map(
                                    (point, index) =>
                                        `${40 + (index * 420) / Math.max(1, trend.length - 1)},${140 - (point[series.key] / max) * 110}`,
                                )
                                .join(' ')}
                            fill="none"
                            stroke={series.color}
                            strokeWidth="2"
                        />
                        {trend.map((point, index) => (
                            <circle
                                key={index}
                                cx={
                                    40 +
                                    (index * 420) /
                                        Math.max(1, trend.length - 1)
                                }
                                cy={140 - (point[series.key] / max) * 110}
                                r="3"
                                fill="white"
                                stroke={series.color}
                                strokeWidth="2"
                            >
                                <title>
                                    {series.name}: {point[series.key]}
                                </title>
                            </circle>
                        ))}
                    </g>
                ))}
            </svg>
            <div>
                {series.map((series) => (
                    <span key={series.key}>
                        <i style={{ background: series.color }} />
                        {series.name}
                    </span>
                ))}
            </div>
        </div>
    );
}
export default function Marketing({
    period,
    stats,
    campaigns,
    featuredProducts,
    products,
    referralUrl,
    referralCode,
    storeUrl,
    assets,
    drafts,
    activity,
    topMarketers,
    trend,
    rankRequirements,
    referralBps,
}: {
    period: string;
    stats: {
        registrations: number;
        revenue: number;
        orders: number;
        visits: number;
        conversion_bps: number;
        active_campaigns: number;
    };
    campaigns: CampaignPage;
    featuredProducts: {
        id: number;
        name: string;
        slug: string;
        price: number | null;
    }[];
    products: { id: number; name: string }[];
    referralUrl: string | null;
    referralCode: string | null;
    storeUrl: string;
    assets: MarketingAsset[];
    drafts: Draft[];
    activity: { action: string; subject_id: number; created_at: string }[];
    topMarketers: Marketer[];
    trend: Trend[];
    rankRequirements: Record<
        string,
        { personal_sales: number; team_sales: number }
    >;
    referralBps: number;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManage =
        auth.permissions.includes('catalog.manage') ||
        auth.permissions.includes('commission-rules.manage');
    const [tab, setTab] = useState('Overview');
    const [slide, setSlide] = useState(0);
    const [notice, setNotice] = useState('');
    const link = referralUrl ?? storeUrl;
    const copy = async (value: string) => {
        try {
            await navigator.clipboard.writeText(value);
            setNotice('Berhasil disalin.');
        } catch {
            setNotice(
                'Salin teks melalui kolom tautan atau pesan yang tersedia.',
            );
        }
    };
    const share = async (value: string) => {
        try {
            if (navigator.share)
                await navigator.share({
                    title: 'HoneyCommerce',
                    text: 'Temukan produk madu pilihan di HoneyCommerce.',
                    url: value,
                });
            else await copy(value);
        } catch {
            setNotice('Tautan siap disalin dan dibagikan.');
        }
    };
    const current =
        featuredProducts[slide % Math.max(1, featuredProducts.length)];
    const campaignLink = (campaign: Campaign) =>
        visit.url(campaign.code, {
            query: referralCode ? { ref: referralCode } : {},
        });
    const materials = (
        <section className="honey-panel marketing-materials">
            <header>
                <div>
                    <h2>
                        <Image />
                        Materi Promosi Siap Pakai
                    </h2>
                    <p>Gunakan materi berikut untuk promosi di media sosial.</p>
                </div>
                <button onClick={() => setTab('Materi Promosi')}>
                    Lihat Semua
                    <ArrowRight />
                </button>
            </header>
            <div className="marketing-material-grid">
                {samples.map(({ key, title, icon: Icon }) => (
                    <RecordDialog
                        key={key}
                        label={title}
                        title={title}
                        triggerClassName={`marketing-material-card material-${key}`}
                        trigger={
                            <>
                                <img src={demo.url(key)} alt="" />
                                <span>
                                    <Icon />
                                </span>
                                <strong>{title}</strong>
                            </>
                        }
                    >
                        <img
                            src={demo.url(key)}
                            alt={`Contoh ${title}`}
                            className="w-full rounded-xl"
                        />
                        <p className="my-3 text-sm text-muted-foreground">
                            Contoh materi dengan gambar madu katalog. Unduh SVG
                            untuk disesuaikan dengan penawaran Anda.
                        </p>
                        <a
                            className="product-primary"
                            href={demo.url(key, { query: { download: 1 } })}
                        >
                            <Download />
                            Unduh materi SVG
                        </a>
                    </RecordDialog>
                ))}
            </div>
        </section>
    );
    const referral = (
        <section className="honey-panel marketing-referral">
            <div>
                <h2>
                    <LinkIcon />
                    {referralUrl ? 'Link Referral Anda' : 'Link Katalog Produk'}
                </h2>
                <p>
                    {referralUrl
                        ? 'Bagikan link untuk memperkenalkan produk madu pilihan.'
                        : 'Akun ini belum memiliki kode mitra aktif. Gunakan link katalog untuk promosi umum.'}
                </p>
                <div className="marketing-link-input">
                    <input
                        readOnly
                        aria-label="Tautan pemasaran"
                        value={link}
                        onFocus={(event) => event.target.select()}
                    />
                    <button
                        className="product-primary"
                        aria-label="Salin link"
                        onClick={() => copy(link)}
                    >
                        <Copy />
                    </button>
                </div>
                <div className="marketing-referral-actions">
                    <button
                        className="product-primary"
                        onClick={() => copy(link)}
                    >
                        <Copy />
                        Salin Link
                    </button>
                    <button
                        className="product-secondary"
                        onClick={() => share(link)}
                    >
                        <Share2 />
                        Bagikan
                    </button>
                </div>
            </div>
            <div className="marketing-qr">
                <img src={qr.url()} alt="QR tautan produk atau referral Anda" />
                <a href={qr.url({ query: { download: 1 } })}>
                    <Download />
                    Unduh QR
                </a>
            </div>
        </section>
    );
    const reward = (
        <section className="honey-panel marketing-rewards">
            <h2>
                <BadgeCheck />
                Program Marketing & Reward
            </h2>
            <div>
                <article>
                    <ShoppingCart />
                    <strong>Penjualan Produk</strong>
                    <p>Komisi langsung sesuai aturan produk.</p>
                    <b>15–20%</b>
                    <small>Transaksi terbayar valid</small>
                </article>
                <article>
                    <Gift />
                    <strong>Referral Pelanggan</strong>
                    <p>Transaksi pertama pelanggan.</p>
                    <b>{referralBps / 100}%</b>
                    <small>Menggantikan komisi generasi 1</small>
                </article>
                <article>
                    <Star />
                    <strong>Target Peringkat</strong>
                    <p>Capai target omzet pribadi dan tim.</p>
                    <b>5 Rank</b>
                    <small>Bonus tambahan belum diaktifkan</small>
                </article>
            </div>
            <p className="marketing-fine-print">
                Komisi berasal dari penjualan produk. Pendaftaran dan perekrutan
                anggota tidak menghasilkan bonus.
            </p>
        </section>
    );
    return (
        <>
            <Head title="Marketing" />
            <main className="product-workspace marketing-workspace">
                <header className="product-heading">
                    <div>
                        <MenuTitle icon={Megaphone} tone="amber">
                            Marketing
                        </MenuTitle>
                        <p>
                            Kelola materi promosi, link referral, kampanye, dan
                            performa pemasaran Anda.
                        </p>
                    </div>
                    {canManage && (
                        <RecordDialog
                            kind="add"
                            label="Buat Kampanye"
                            title="Buat kampanye pemasaran"
                        >
                            <CampaignForm products={products} />
                        </RecordDialog>
                    )}
                </header>
                <nav className="commission-tabs" aria-label="Menu marketing">
                    {[
                        'Overview',
                        'Materi Promosi',
                        'Kampanye',
                        'Link Referral',
                        'Broadcast',
                        'Program & Reward',
                    ].map((name) => (
                        <button
                            key={name}
                            className={tab === name ? 'selected' : ''}
                            onClick={() => setTab(name)}
                        >
                            {name === 'Overview' ? 'Overview' : name}
                        </button>
                    ))}
                </nav>
                {notice && (
                    <p className="marketing-notice" role="status">
                        {notice}
                        <button
                            aria-label="Tutup pemberitahuan"
                            onClick={() => setNotice('')}
                        >
                            ×
                        </button>
                    </p>
                )}
                <section className="product-kpis marketing-kpis">
                    {[
                        {
                            title: 'Pendaftaran dari Marketing',
                            value: stats.registrations.toLocaleString('id-ID'),
                            icon: Users,
                            tone: 'blue',
                            detail: 'Akun terverifikasi dari kampanye',
                        },
                        {
                            title: 'Penjualan dari Marketing',
                            value: money(stats.revenue),
                            icon: ShoppingCart,
                            tone: 'green',
                            detail: 'Penjualan produk valid, bersih refund',
                        },
                        {
                            title: 'Konversi (Daftar → Beli)',
                            value: `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(stats.conversion_bps / 100)}%`,
                            icon: Target,
                            tone: 'gold',
                            detail: 'Pendaftar terverifikasi yang membeli',
                        },
                        {
                            title: 'Kampanye Aktif',
                            value: stats.active_campaigns.toLocaleString(
                                'id-ID',
                            ),
                            icon: Megaphone,
                            tone: 'pink',
                            detail: 'Lihat Detail',
                        },
                    ].map(({ title, value, icon: Icon, tone, detail }) => (
                        <article className={`product-kpi ${tone}`} key={title}>
                            <span className="product-kpi-icon">
                                <Icon />
                            </span>
                            <div>
                                <h2>{title}</h2>
                                <strong>{value}</strong>
                                {tone === 'pink' ? (
                                    <button onClick={() => setTab('Kampanye')}>
                                        {detail}
                                        <ArrowRight />
                                    </button>
                                ) : (
                                    <p>{detail}</p>
                                )}
                            </div>
                        </article>
                    ))}
                </section>
                {tab === 'Overview' ? (
                    <>
                        <div className="marketing-top-row">
                            {referral}
                            {materials}
                        </div>
                        <div className="marketing-middle-row">
                            <section className="marketing-campaign-hero">
                                <img
                                    src="/honey-hero.jpg"
                                    alt="Contoh visual kampanye madu HoneyCommerce"
                                />
                                <div>
                                    <span>
                                        {stats.active_campaigns > 0
                                            ? 'Promosi Produk'
                                            : 'Contoh Materi Promosi'}
                                    </span>
                                    <h2>
                                        {current?.name ??
                                            'Madu Pilihan Keluarga'}
                                    </h2>
                                    <p>
                                        {current?.price
                                            ? `Mulai ${money(current.price)}. Temukan pilihan madu untuk keluarga Anda.`
                                            : 'Perkenalkan produk madu pilihan kepada pelanggan Anda.'}
                                    </p>
                                    <button onClick={() => share(link)}>
                                        Bagikan Sekarang
                                        <ArrowRight />
                                    </button>
                                </div>
                                <button
                                    className="marketing-slide-prev"
                                    aria-label="Produk sebelumnya"
                                    onClick={() =>
                                        setSlide(
                                            (slide +
                                                Math.max(
                                                    1,
                                                    featuredProducts.length,
                                                ) -
                                                1) %
                                                Math.max(
                                                    1,
                                                    featuredProducts.length,
                                                ),
                                        )
                                    }
                                >
                                    <ChevronLeft />
                                </button>
                                <button
                                    className="marketing-slide-next"
                                    aria-label="Produk berikutnya"
                                    onClick={() =>
                                        setSlide(
                                            (slide + 1) %
                                                Math.max(
                                                    1,
                                                    featuredProducts.length,
                                                ),
                                        )
                                    }
                                >
                                    <ChevronRight />
                                </button>
                                <div className="marketing-slide-dots">
                                    {featuredProducts.map(
                                        (product, position) => (
                                            <button
                                                key={product.id}
                                                className={
                                                    position === slide
                                                        ? 'selected'
                                                        : ''
                                                }
                                                aria-label={`Tampilkan ${product.name}`}
                                                onClick={() =>
                                                    setSlide(position)
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            </section>
                            <section className="honey-panel marketing-performance">
                                <header>
                                    <h2>
                                        <ChartNoAxesCombined />
                                        Performa Kampanye
                                    </h2>
                                    <input
                                        type="month"
                                        aria-label="Periode marketing"
                                        value={period}
                                        onChange={(event) =>
                                            router.get(
                                                index.url(),
                                                { period: event.target.value },
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                    />
                                </header>
                                <div className="marketing-performance-stats">
                                    <article>
                                        <Eye />
                                        <strong>
                                            {stats.visits.toLocaleString(
                                                'id-ID',
                                            )}
                                        </strong>
                                        <small>Kunjungan</small>
                                    </article>
                                    <article>
                                        <Users />
                                        <strong>
                                            {stats.registrations.toLocaleString(
                                                'id-ID',
                                            )}
                                        </strong>
                                        <small>Pendaftaran</small>
                                    </article>
                                    <article>
                                        <ShoppingCart />
                                        <strong>
                                            {stats.orders.toLocaleString(
                                                'id-ID',
                                            )}
                                        </strong>
                                        <small>Pembelian</small>
                                    </article>
                                </div>
                                <CampaignChart trend={trend} />
                            </section>
                        </div>
                        <div className="marketing-bottom-row">
                            {reward}
                            <section className="honey-panel marketing-top-marketers">
                                <h2>
                                    <Trophy />
                                    Top Marketer (Periode Ini)
                                </h2>
                                {topMarketers.length ? (
                                    topMarketers.map((marketer, position) => (
                                        <article key={position}>
                                            <span
                                                className={`order-row-number position-${position + 1}`}
                                            >
                                                {position + 1}
                                            </span>
                                            <span className="marketing-marketer-avatar">
                                                <MemberAvatar
                                                    name={marketer.name}
                                                    gender={marketer.gender}
                                                    photo={marketer.avatar}
                                                />
                                            </span>
                                            <div>
                                                <strong>{marketer.name}</strong>
                                                <p>
                                                    {marketer.orders} pesanan |{' '}
                                                    {money(marketer.revenue)}
                                                </p>
                                            </div>
                                        </article>
                                    ))
                                ) : (
                                    <p className="marketing-empty">
                                        Belum ada penjualan kampanye yang
                                        teratribusi.
                                    </p>
                                )}
                            </section>
                            <section className="honey-panel marketing-activity">
                                <h2>
                                    <Clock3 />
                                    Aktivitas Marketing Terbaru
                                </h2>
                                {activity.length ? (
                                    activity.map((event, position) => (
                                        <article key={position}>
                                            <Megaphone />
                                            <p>
                                                {
                                                    (
                                                        {
                                                            marketing_campaign_saved:
                                                                'Kampanye disimpan',
                                                            marketing_asset_uploaded:
                                                                'Materi promosi diunggah',
                                                            marketing_broadcast_saved:
                                                                'Draf broadcast disimpan',
                                                        } as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[event.action]
                                                }{' '}
                                                #{event.subject_id}
                                            </p>
                                            <small>
                                                {new Date(
                                                    event.created_at.replace(
                                                        ' ',
                                                        'T',
                                                    ) + 'Z',
                                                ).toLocaleString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    timeZone: 'Asia/Jakarta',
                                                })}
                                            </small>
                                        </article>
                                    ))
                                ) : (
                                    <p className="marketing-empty">
                                        Aktivitas baru akan tampil setelah
                                        kampanye atau materi disimpan.
                                    </p>
                                )}
                            </section>
                        </div>
                    </>
                ) : tab === 'Materi Promosi' ? (
                    <div className="marketing-library">
                        {materials}
                        {canManage && (
                            <RecordDialog
                                kind="add"
                                label="Unggah Materi"
                                title="Unggah materi promosi"
                            >
                                <AssetForm />
                            </RecordDialog>
                        )}
                        <div className="marketing-uploaded-assets">
                            {assets.map((asset) => (
                                <article className="honey-panel" key={asset.id}>
                                    {asset.kind === 'image' ? (
                                        <img
                                            src={assetFile.url(asset.id)}
                                            alt={asset.title}
                                        />
                                    ) : asset.kind === 'video' ? (
                                        <video
                                            controls
                                            preload="metadata"
                                            src={assetFile.url(asset.id)}
                                        />
                                    ) : (
                                        <FileText />
                                    )}
                                    <h3>{asset.title}</h3>
                                    <a
                                        className="product-secondary"
                                        href={assetFile.url(asset.id, {
                                            query: { download: 1 },
                                        })}
                                    >
                                        <Download />
                                        Unduh
                                    </a>
                                </article>
                            ))}
                        </div>
                    </div>
                ) : tab === 'Kampanye' ? (
                    <section className="honey-panel marketing-campaign-list">
                        <header>
                            <h2>
                                <Megaphone />
                                Daftar Kampanye
                            </h2>
                            {canManage && (
                                <RecordDialog
                                    kind="add"
                                    label="Tambah Kampanye"
                                    title="Tambah kampanye"
                                >
                                    <CampaignForm products={products} />
                                </RecordDialog>
                            )}
                        </header>
                        <DataTable
                            label="Kampanye pemasaran"
                            rows={campaigns.data}
                            rowKey={(campaign) => campaign.id}
                            columns={[
                                {
                                    header: 'Kampanye',
                                    render: (campaign) => (
                                        <div>
                                            <strong>{campaign.name}</strong>
                                            <p>
                                                {campaign.product_name ??
                                                    'Semua produk'}
                                            </p>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    render: (campaign) => (
                                        <StatusBadge status={campaign.status} />
                                    ),
                                },
                                {
                                    header: 'Tautan',
                                    render: (campaign) => (
                                        <button
                                            className="text-primary underline"
                                            onClick={() =>
                                                copy(
                                                    new URL(
                                                        campaignLink(campaign),
                                                        storeUrl,
                                                    ).href,
                                                )
                                            }
                                        >
                                            <Copy />
                                            Salin Link Kampanye
                                        </button>
                                    ),
                                },
                                {
                                    header: 'Aksi',
                                    render: (campaign) =>
                                        canManage ? (
                                            <RecordDialog
                                                kind="edit"
                                                title={`Edit ${campaign.name}`}
                                            >
                                                <CampaignForm
                                                    campaign={campaign}
                                                    products={products}
                                                />
                                            </RecordDialog>
                                        ) : (
                                            '—'
                                        ),
                                },
                            ]}
                        />
                        <footer className="product-pagination">
                            <p>{campaigns.total} kampanye</p>
                            <nav>
                                {campaigns.prev_page_url && (
                                    <Link
                                        href={campaigns.prev_page_url}
                                        preserveState
                                    >
                                        Sebelumnya
                                    </Link>
                                )}
                                {campaigns.next_page_url && (
                                    <Link
                                        href={campaigns.next_page_url}
                                        preserveState
                                    >
                                        Berikutnya
                                    </Link>
                                )}
                            </nav>
                        </footer>
                    </section>
                ) : tab === 'Link Referral' ? (
                    <div className="marketing-referral-tab">
                        {referral}
                        <section className="honey-panel">
                            <h2>
                                <LinkIcon />
                                Cara menggunakan link referral
                            </h2>
                            <p>
                                Bagikan tautan produk kepada calon pelanggan.
                                Atribusi pertama yang valid dipertahankan sampai
                                kedaluwarsa. Komisi baru dihitung setelah
                                transaksi produk dibayar dan memenuhi aturan.
                            </p>
                        </section>
                    </div>
                ) : tab === 'Broadcast' ? (
                    <div className="marketing-broadcast-layout">
                        {canManage && (
                            <section className="honey-panel">
                                <BroadcastForm onCopy={copy} />
                            </section>
                        )}
                        <section className="honey-panel">
                            <h2>
                                <FileText />
                                Draf Pesan Saya
                            </h2>
                            {drafts.length ? (
                                drafts.map((draft) => (
                                    <article
                                        className="marketing-draft"
                                        key={draft.id}
                                    >
                                        <strong>{draft.title}</strong>
                                        <p>{draft.message}</p>
                                        <button
                                            className="product-secondary"
                                            onClick={() => copy(draft.message)}
                                        >
                                            <Copy />
                                            Salin Pesan
                                        </button>
                                    </article>
                                ))
                            ) : (
                                <p className="marketing-empty">
                                    Belum ada draf pesan.
                                </p>
                            )}
                        </section>
                    </div>
                ) : (
                    <div className="marketing-program-tab">
                        {reward}
                        <section className="honey-panel">
                            <header>
                                <h2>
                                    <Star />
                                    Target Peringkat Bulanan
                                </h2>
                                {auth.permissions.includes(
                                    'commission-rules.manage',
                                ) && (
                                    <Link
                                        className="product-secondary"
                                        href={mlmSettings.url()}
                                    >
                                        <Settings />
                                        Pengaturan Program MLM
                                    </Link>
                                )}
                            </header>
                            <DataTable
                                label="Target peringkat"
                                rows={Object.entries(rankRequirements)}
                                rowKey={([rank]) => rank}
                                columns={[
                                    {
                                        header: 'Peringkat',
                                        render: ([rank]) => rank,
                                    },
                                    {
                                        header: 'Omzet Pribadi',
                                        render: ([, target]) =>
                                            money(target.personal_sales),
                                    },
                                    {
                                        header: 'Omzet Tim',
                                        render: ([, target]) =>
                                            money(target.team_sales),
                                    },
                                ]}
                            />
                        </section>
                    </div>
                )}
                <p className="marketing-fine-print">
                    Statistik kampanye dihitung dari kunjungan unik sesi,
                    pendaftaran terverifikasi, dan pesanan terbayar pada periode
                    terpilih. Materi contoh dapat disesuaikan sebelum dibagikan.
                </p>
            </main>
        </>
    );
}
