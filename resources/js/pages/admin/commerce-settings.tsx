import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import {
    Settings,
    Network,
    CreditCard,
    Truck,
    Bell,
    Users,
    Link as LinkIcon,
    Ellipsis,
    Building2,
    Phone,
    Image,
    Globe,
    SlidersHorizontal,
    Upload,
    Trash2,
    Save,
    RotateCcw,
    Info,
    Check,
    Wrench,
    Mail,
    MapPin,
    Droplet,
    ArrowUpRight,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { ShippingSettingsForm } from '@/components/shipping-settings-form';
import type { ShippingConfiguration } from '@/components/shipping-settings-form';
import { Errors } from '@/components/commerce-ui';
import { update } from '@/routes/admin/store-settings';
import { index as mlmSettings } from '@/routes/admin/mlm-settings';
import { index as commissionRules } from '@/routes/admin/commission-rules';
import { index as users } from '@/routes/admin/users';
import { index as audit } from '@/routes/admin/audit';
import { index as marketing } from '@/routes/admin/marketing';

type Profile = {
    company_name: string;
    brand_name: string;
    slogan: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    address: string | null;
    description: string | null;
    payment_instructions: string;
    system_active: boolean;
    registration_enabled: boolean;
    maintenance_mode: boolean;
    revision: string;
    logo_url: string | null;
    favicon_url: string | null;
};
type Props = {
    storeSettings: Profile;
    canManageStore: boolean;
    settings: Record<string, string | number>;
    shippingSettings: ShippingConfiguration;
    canManageShipping: boolean;
    runtime: Record<string, string>;
};
const tabs = [
    ['general', 'Umum', Settings],
    ['mlm', 'MLM & Komisi', Network],
    ['payment', 'Pembayaran', CreditCard],
    ['shipping', 'Pengiriman', Truck],
    ['notifications', 'Notifikasi', Bell],
    ['users', 'Pengguna & Akses', Users],
    ['integrations', 'Integrasi', LinkIcon],
    ['other', 'Lainnya', Ellipsis],
] as const;
const sections = [
    ['profile', 'Profil Perusahaan', Building2],
    ['contact', 'Informasi Kontak', Phone],
    ['brand', 'Identitas Brand', Image],
    ['regional', 'Mata Uang & Regional', Globe],
    ['preferences', 'Preferensi Sistem', SlidersHorizontal],
] as const;
const initialData = (profile: Profile) => ({
    company_name: profile.company_name,
    brand_name: profile.brand_name,
    slogan: profile.slogan ?? '',
    email: profile.email ?? '',
    phone: profile.phone ?? '',
    website: profile.website ?? '',
    address: profile.address ?? '',
    description: profile.description ?? '',
    payment_instructions: profile.payment_instructions,
    system_active: profile.system_active,
    registration_enabled: profile.registration_enabled,
    maintenance_mode: profile.maintenance_mode,
    revision: profile.revision,
    logo: null as File | null,
    favicon: null as File | null,
    remove_logo: false,
    remove_favicon: false,
});

function PanelHeading({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
}) {
    return (
        <header className="store-settings-panel-heading">
            <Icon size={26} />
            <div>
                <h2>{title}</h2>
                {description && <p>{description}</p>}
            </div>
        </header>
    );
}
function SettingField({
    label,
    icon: Icon,
    children,
    required = false,
}: {
    label: string;
    icon?: LucideIcon;
    children: ReactNode;
    required?: boolean;
}) {
    return (
        <label className="store-settings-field">
            <span>
                {label}
                {required && <em> *</em>}
            </span>
            <div className={Icon ? 'with-icon' : ''}>
                {Icon && <Icon size={18} />}
                {children}
            </div>
        </label>
    );
}
function Toggle({
    title,
    description,
    icon: Icon,
    value,
    onChange,
    disabled,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    value: boolean;
    onChange: () => void;
    disabled: boolean;
}) {
    return (
        <div className="store-settings-toggle-row">
            <span
                className={`store-settings-status-icon ${value ? 'enabled' : ''}`}
            >
                <Icon size={21} />
            </span>
            <div>
                <strong>{title}</strong>
                <p>{description}</p>
            </div>
            <button
                type="button"
                role="switch"
                aria-label={title}
                aria-checked={value}
                disabled={disabled}
                onClick={onChange}
                className={`store-settings-toggle ${value ? 'is-on' : ''}`}
            >
                <span />
            </button>
        </div>
    );
}
function BrandAsset({
    kind,
    file,
    url,
    removed,
    brand,
    slogan,
    disabled,
    onChange,
    onRemove,
}: {
    kind: 'logo' | 'favicon';
    file: File | null;
    url: string | null;
    removed: boolean;
    brand: string;
    slogan: string;
    disabled: boolean;
    onChange: (file: File | null) => void;
    onRemove: () => void;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const input = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }
        const objectUrl = URL.createObjectURL(file);
        setPreview(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);
    const source = preview || (!removed ? url : null);
    return (
        <section className={`store-settings-asset ${kind}`}>
            <h3>{kind === 'logo' ? 'Logo Perusahaan' : 'Favicon'}</h3>
            <div className="store-settings-brand-preview">
                {source ? (
                    <img src={source} alt={`Pratinjau ${kind}`} />
                ) : (
                    <span className="honey-emblem">
                        <Droplet size={30} fill="#ffac13" />
                    </span>
                )}
                {kind === 'logo' && (
                    <div>
                        <strong>{brand || 'Nama Brand'}</strong>
                        <small>{slogan}</small>
                    </div>
                )}
            </div>
            <input
                ref={input}
                type="file"
                hidden
                accept={kind === 'logo' ? '.jpg,.jpeg,.png' : '.png,.ico'}
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                disabled={disabled}
            />
            <div className="store-settings-asset-actions">
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => input.current?.click()}
                >
                    <Upload size={16} />
                    Ubah {kind === 'logo' ? 'Logo' : 'Favicon'}
                </button>
                <button
                    type="button"
                    className="remove"
                    aria-label={`Hapus ${kind}`}
                    disabled={disabled}
                    onClick={() => {
                        if (input.current) {
                            input.current.value = '';
                        }
                        onRemove();
                    }}
                >
                    <Trash2 size={16} />
                </button>
            </div>
            <p>
                {kind === 'logo'
                    ? 'Format: JPG atau PNG. Maksimal 2 MB. Rekomendasi: 512 × 512 px.'
                    : 'Format: ICO atau PNG. Maksimal 512 KB. Rekomendasi: 32 × 32 atau 64 × 64 px.'}
            </p>
            {removed && (
                <small>Akan kembali ke ikon bawaan setelah disimpan.</small>
            )}
        </section>
    );
}
function Destination({
    href,
    title,
    children,
}: {
    href: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <Link href={href} className="store-settings-destination">
            <span>
                <strong>{title}</strong>
                <small>{children}</small>
            </span>
            <ArrowUpRight size={22} />
        </Link>
    );
}

export default function SettingsPage({
    storeSettings,
    canManageStore,
    settings,
    shippingSettings,
    canManageShipping,
    runtime,
}: Props) {
    const [tab, setTab] = useState<(typeof tabs)[number][0]>('general');
    const [section, setSection] =
        useState<(typeof sections)[number][0]>('profile');
    const form = useForm(initialData(storeSettings));
    const { auth } = usePage().props;
    const disabled = !canManageStore || form.processing;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const saved = initialData(page.props.storeSettings as Profile);
                form.setDefaults(saved);
                form.setData(saved);
            },
        });
    };
    const field = (
        key:
            | 'company_name'
            | 'brand_name'
            | 'slogan'
            | 'email'
            | 'phone'
            | 'website',
        label: string,
        icon?: LucideIcon,
        type = 'text',
        required = false,
    ) => (
        <SettingField label={label} icon={icon} required={required}>
            <input
                type={type}
                required={required}
                value={form.data[key]}
                onChange={(event) => form.setData(key, event.target.value)}
            />
        </SettingField>
    );
    const asset = (kind: 'logo' | 'favicon') => (
        <BrandAsset
            kind={kind}
            file={form.data[kind]}
            url={storeSettings[`${kind}_url`]}
            removed={form.data[`remove_${kind}`]}
            brand={form.data.brand_name}
            slogan={form.data.slogan}
            disabled={disabled}
            onChange={(file) => {
                form.setData(kind, file);
                form.setData(`remove_${kind}`, false);
            }}
            onRemove={() => {
                form.setData(kind, null);
                form.setData(`remove_${kind}`, true);
            }}
        />
    );
    const status = (
        <section className="store-settings-panel store-settings-system">
            <PanelHeading icon={Building2} title="Status Sistem" />
            <Toggle
                title="Sistem Aktif"
                description="Aktifkan atau nonaktifkan akses toko."
                icon={Check}
                value={form.data.system_active}
                disabled={disabled}
                onChange={() =>
                    form.setData('system_active', !form.data.system_active)
                }
            />
            <Toggle
                title="Registrasi Member Baru"
                description="Izinkan pendaftaran member baru."
                icon={Users}
                value={form.data.registration_enabled}
                disabled={disabled}
                onChange={() =>
                    form.setData(
                        'registration_enabled',
                        !form.data.registration_enabled,
                    )
                }
            />
            <Toggle
                title="Mode Maintenance"
                description="Tampilkan halaman pemeliharaan sistem."
                icon={Wrench}
                value={form.data.maintenance_mode}
                disabled={disabled}
                onChange={() =>
                    form.setData(
                        'maintenance_mode',
                        !form.data.maintenance_mode,
                    )
                }
            />
        </section>
    );
    return (
        <main className="store-settings-page">
            <Head title="Pengaturan" />
            <header className="store-settings-title">
                <Settings size={48} fill="#ffbd22" strokeWidth={2.5} />
                <div>
                    <h1>Pengaturan</h1>
                    <p>
                        Kelola konfigurasi sistem, bisnis, dan program MLM
                        HoneyCommerce.
                    </p>
                </div>
            </header>
            <nav
                className="store-settings-tabs"
                aria-label="Kategori pengaturan"
            >
                {tabs.map(([key, label, Icon]) => (
                    <button
                        type="button"
                        key={key}
                        className={tab === key ? 'active' : ''}
                        aria-pressed={tab === key}
                        onClick={() => setTab(key)}
                    >
                        <Icon size={20} />
                        {label}
                    </button>
                ))}
            </nav>
            {!canManageStore && (
                <p className="store-settings-notice">
                    <ShieldCheck size={18} />
                    Profil perusahaan hanya dapat diubah oleh administrator
                    pengelola akses.
                </p>
            )}
            {tab === 'general' || tab === 'payment' ? (
                <form onSubmit={submit}>
                    <Errors errors={form.errors} />
                    {tab === 'general' ? (
                        <>
                            <div className="store-settings-general">
                                <aside
                                    className="store-settings-panel store-settings-subnav"
                                    aria-label="Pengaturan umum"
                                >
                                    {sections.map(([key, label, Icon]) => (
                                        <button
                                            type="button"
                                            key={key}
                                            className={
                                                section === key ? 'active' : ''
                                            }
                                            onClick={() => setSection(key)}
                                            aria-pressed={section === key}
                                        >
                                            <Icon size={20} />
                                            {label}
                                        </button>
                                    ))}
                                </aside>
                                <section className="store-settings-panel store-settings-profile">
                                    <PanelHeading
                                        icon={
                                            sections.find(
                                                ([key]) => key === section,
                                            )?.[2] ?? Building2
                                        }
                                        title={
                                            sections.find(
                                                ([key]) => key === section,
                                            )?.[1] ?? 'Profil Perusahaan'
                                        }
                                        description="Atur informasi utama perusahaan yang akan digunakan di seluruh sistem."
                                    />
                                    {section === 'regional' ? (
                                        <div className="store-settings-body">
                                            <dl className="store-settings-details">
                                                {Object.entries(settings)
                                                    .slice(0, 2)
                                                    .map(([name, value]) => (
                                                        <div key={name}>
                                                            <dt>{name}</dt>
                                                            <dd>{value}</dd>
                                                        </div>
                                                    ))}
                                            </dl>
                                            <p className="store-settings-hint">
                                                Transaksi menggunakan Rupiah
                                                tanpa desimal. Periode komisi
                                                dan rank mengikuti waktu
                                                Asia/Jakarta agar perhitungan
                                                tetap konsisten.
                                            </p>
                                        </div>
                                    ) : section === 'preferences' ? (
                                        <div className="store-settings-body">
                                            <p>
                                                Atur akses toko dan pendaftaran
                                                melalui Status Sistem di bawah.
                                                Administrator tetap dapat masuk
                                                untuk memulihkan akses saat
                                                pemeliharaan.
                                            </p>
                                            <dl className="store-settings-details">
                                                {Object.entries(runtime).map(
                                                    ([name, value]) => (
                                                        <div key={name}>
                                                            <dt>{name}</dt>
                                                            <dd>{value}</dd>
                                                        </div>
                                                    ),
                                                )}
                                            </dl>
                                        </div>
                                    ) : section === 'brand' ? (
                                        <div className="store-settings-body store-settings-brand-grid">
                                            {asset('logo')}
                                            {asset('favicon')}
                                            <p className="store-settings-hint">
                                                Logo akan diterapkan pada
                                                sidebar. Favicon digunakan
                                                sebagai ikon tab browser.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="store-settings-profile-grid">
                                            <fieldset
                                                disabled={disabled}
                                                className="store-settings-fields"
                                            >
                                                {section === 'profile' && (
                                                    <>
                                                        {field(
                                                            'company_name',
                                                            'Nama Perusahaan',
                                                            undefined,
                                                            'text',
                                                            true,
                                                        )}
                                                        {field(
                                                            'brand_name',
                                                            'Nama Brand / Aplikasi',
                                                            undefined,
                                                            'text',
                                                            true,
                                                        )}
                                                        {field(
                                                            'slogan',
                                                            'Slogan',
                                                        )}
                                                    </>
                                                )}
                                                {field(
                                                    'email',
                                                    'Email Resmi',
                                                    Mail,
                                                    'email',
                                                )}
                                                {field(
                                                    'phone',
                                                    'Nomor Telepon',
                                                    Phone,
                                                    'tel',
                                                )}
                                                {field(
                                                    'website',
                                                    'Website',
                                                    LinkIcon,
                                                    'url',
                                                )}
                                                <div className="store-settings-wide">
                                                    <SettingField
                                                        label="Alamat Kantor"
                                                        icon={MapPin}
                                                    >
                                                        <textarea
                                                            rows={3}
                                                            value={
                                                                form.data
                                                                    .address
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'address',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </SettingField>
                                                </div>
                                            </fieldset>
                                            <aside className="store-settings-assets">
                                                {asset('logo')}
                                                {asset('favicon')}
                                            </aside>
                                        </div>
                                    )}
                                </section>
                            </div>
                            <div className="store-settings-bottom">
                                <section className="store-settings-panel store-settings-description">
                                    <PanelHeading
                                        icon={Info}
                                        title="Informasi Singkat"
                                    />
                                    <textarea
                                        aria-label="Informasi singkat perusahaan"
                                        rows={5}
                                        maxLength={5000}
                                        disabled={disabled}
                                        value={form.data.description}
                                        onChange={(event) =>
                                            form.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Ceritakan profil singkat perusahaan Anda…"
                                    />
                                    <small>
                                        Ditampilkan di halaman Tentang Toko.
                                        Teks disimpan sebagai teks biasa.
                                    </small>
                                </section>
                                {status}
                            </div>
                        </>
                    ) : (
                        <section className="store-settings-panel">
                            <PanelHeading
                                icon={CreditCard}
                                title="Pengaturan Pembayaran"
                                description="Instruksi ditampilkan pada checkout dan pesanan baru. Pembayaran diverifikasi oleh Finance."
                            />
                            <fieldset
                                disabled={disabled}
                                className="store-settings-body"
                            >
                                <SettingField
                                    label="Instruksi Pembayaran"
                                    required
                                >
                                    <textarea
                                        rows={6}
                                        value={form.data.payment_instructions}
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_instructions',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                </SettingField>
                            </fieldset>
                        </section>
                    )}
                    <footer className="store-settings-footer">
                        <span role="status">
                            {form.recentlySuccessful
                                ? 'Perubahan berhasil disimpan.'
                                : form.isDirty
                                  ? 'Ada perubahan yang belum disimpan.'
                                  : ''}
                        </span>
                        <button
                            type="button"
                            disabled={disabled || !form.isDirty}
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                            }}
                        >
                            <RotateCcw size={20} />
                            Reset
                        </button>
                        <button
                            type="submit"
                            className="primary"
                            disabled={disabled || !form.isDirty}
                        >
                            <Save size={20} />
                            {form.processing
                                ? 'Menyimpan…'
                                : 'Simpan Perubahan'}
                        </button>
                    </footer>
                </form>
            ) : (
                <section className="store-settings-panel store-settings-secondary">
                    {tab === 'shipping' || tab === 'integrations' ? (
                        <>
                            <PanelHeading
                                icon={Truck}
                                title={
                                    tab === 'shipping'
                                        ? 'Pengaturan Pengiriman'
                                        : 'Integrasi Pengiriman'
                                }
                                description="Kelola tarif manual atau hubungkan API RajaOngkir/Komerce."
                            />
                            {canManageShipping ? (
                                <ShippingSettingsForm
                                    key={shippingSettings.revision}
                                    settings={shippingSettings}
                                />
                            ) : (
                                <p>
                                    Anda tidak memiliki izin mengubah
                                    pengiriman.
                                </p>
                            )}
                        </>
                    ) : tab === 'mlm' ? (
                        <>
                            <PanelHeading
                                icon={Network}
                                title="MLM & Komisi"
                                description="Gunakan pengaturan program dan aturan komisi yang telah terhubung ke transaksi."
                            />
                            <dl className="store-settings-details">
                                {Object.entries(settings)
                                    .slice(3)
                                    .map(([name, value]) => (
                                        <div key={name}>
                                            <dt>{name}</dt>
                                            <dd>{value}</dd>
                                        </div>
                                    ))}
                            </dl>
                            {auth.permissions.includes(
                                'commission-rules.manage',
                            ) && (
                                <>
                                    <Destination
                                        href={mlmSettings.url()}
                                        title="Program MLM & Peringkat"
                                    >
                                        Target rank, referral, dan tarif komisi
                                        lima generasi.
                                    </Destination>
                                    <Destination
                                        href={commissionRules.url()}
                                        title="Aturan Komisi Langsung"
                                    >
                                        Simulasi, versi, serta publikasi tarif
                                        komisi.
                                    </Destination>
                                </>
                            )}
                        </>
                    ) : tab === 'users' ? (
                        <>
                            <PanelHeading
                                icon={Users}
                                title="Pengguna & Akses"
                                description="Kelola akun serta hak akses melalui halaman pengelolaan pengguna."
                            />
                            {canManageStore ? (
                                <Destination
                                    href={users.url()}
                                    title="Kelola Pengguna & Akses"
                                >
                                    Buat akun, atur peran, dan kelola status
                                    pengguna.
                                </Destination>
                            ) : (
                                <p>
                                    Hanya administrator pengelola akses yang
                                    dapat membuka menu ini.
                                </p>
                            )}
                        </>
                    ) : tab === 'notifications' ? (
                        <>
                            <PanelHeading
                                icon={Bell}
                                title="Notifikasi"
                                description="Status kanal notifikasi dan pengiriman email aplikasi."
                            />
                            <dl className="store-settings-details">
                                <div>
                                    <dt>Pengiriman email</dt>
                                    <dd>
                                        {runtime.mail === 'log'
                                            ? 'Mode log — email dicatat, belum dikirim ke penerima'
                                            : runtime.mail}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Antrean notifikasi</dt>
                                    <dd>{runtime.queue}</dd>
                                </div>
                            </dl>
                            <p className="store-settings-hint">
                                Kredensial SMTP dikelola melalui environment
                                server. Notifikasi transaksi mengikuti peristiwa
                                pesanan dan keuangan.
                            </p>
                            {auth.permissions.includes('catalog.manage') && (
                                <Destination
                                    href={marketing.url()}
                                    title="Pesan Broadcast"
                                >
                                    Kelola draf pesan dan materi pemasaran.
                                </Destination>
                            )}
                        </>
                    ) : (
                        <>
                            <PanelHeading
                                icon={SlidersHorizontal}
                                title="Pengaturan Lainnya"
                                description="Informasi operasional dan riwayat perubahan sistem."
                            />
                            <dl className="store-settings-details">
                                {Object.entries(runtime).map(
                                    ([name, value]) => (
                                        <div key={name}>
                                            <dt>{name}</dt>
                                            <dd>{value}</dd>
                                        </div>
                                    ),
                                )}
                            </dl>
                            {auth.permissions.includes('audit.view') && (
                                <Destination
                                    href={audit.url()}
                                    title="Audit Aktivitas"
                                >
                                    Lihat riwayat perubahan dan pelakunya.
                                </Destination>
                            )}
                        </>
                    )}
                </section>
            )}
        </main>
    );
}
