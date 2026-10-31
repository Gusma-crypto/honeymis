import { MenuTitle } from '@/components/menu-title';
import { Network as MenuIcon } from 'lucide-react';
import { ArrowDown, ArrowRight } from 'lucide-react';
import { NetworkWorkspace } from '@/components/network-workspace';
import type { NetworkPerformance } from '@/components/network-workspace';
import type { PartnerNetworkData } from '@/components/partner-network-summary';
import { ListFilters } from '@/components/list-filters';
import { confirmAction } from '@/lib/sweet-alert';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    RowActions,
    StatusBadge,
} from '@/components/data-list';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Errors,
    Field,
    inputClass,
    Pager,
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { rank as updateRank, store, update } from '@/routes/admin/partners';
import type { Auth } from '@/types/auth';
import type { Paginated } from '@/types/commerce';
const statusActions: Record<string, string> = {
    active: 'Aktifkan',
    suspended: 'Tangguhkan',
    rejected: 'Tolak',
};
type Partner = {
    id: number;
    name: string;
    email: string;
    status: string;
    referral_code: string;
    sponsor_partner_id: number | null;
};

function RankOverrideDialog({
    partner,
}: {
    partner: { id: number; name: string };
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const form = useForm<{ rank: string | null; reason: string }>({
        rank: '',
        reason: '',
    });
    if (
        !auth.permissions.includes('commission-rules.manage') ||
        !auth.permissions.includes('partners.manage')
    ) {
        return null;
    }
    return (
        <RecordDialog
            kind="edit"
            label="Atur peringkat"
            title={`Override peringkat ${partner.name}`}
        >
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(updateRank.url(partner.id), {
                        preserveScroll: true,
                    });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    Override berlaku sampai dicabut admin, termasuk saat
                    berganti bulan. Omzet tetap dihitung dari penjualan valid.
                    Pencabutan mengembalikan peringkat sesuai evaluasi omzet
                    bulan berjalan.
                </p>
                <Field label="Tindakan peringkat">
                    <select
                        className={inputClass}
                        required
                        value={form.data.rank ?? 'revoke'}
                        onChange={(event) =>
                            form.setData(
                                'rank',
                                event.target.value === 'revoke'
                                    ? null
                                    : event.target.value,
                            )
                        }
                    >
                        <option value="" disabled>
                            Pilih tindakan
                        </option>
                        {['Member', 'Bronze', 'Silver', 'Gold', 'Platinum'].map(
                            (rank) => (
                                <option key={rank} value={rank}>
                                    Tetapkan {rank}
                                </option>
                            ),
                        )}
                        <option value="revoke">Cabut override aktif</option>
                    </select>
                </Field>
                <Field label="Alasan (wajib, minimal 10 karakter)">
                    <textarea
                        className={inputClass}
                        required
                        minLength={10}
                        maxLength={2000}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                </Field>
                <Errors errors={form.errors} />
                <Button disabled={form.processing}>
                    {form.processing
                        ? 'Menyimpan...'
                        : 'Simpan keputusan peringkat'}
                </Button>
            </form>
        </RecordDialog>
    );
}
function PlacementForm({
    relative,
    placement,
}: {
    relative?: Pick<
        Partner,
        'id' | 'name' | 'referral_code' | 'sponsor_partner_id'
    >;
    placement: 'below' | 'sibling' | 'root';
}) {
    const form = useForm({
        email: '',
        placement,
        relative_partner_id: relative?.id ?? null,
    });
    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(store.url(), { preserveScroll: true });
            }}
        >
            <div className="rounded-xl border bg-accent/40 p-4 text-sm">
                {placement === 'below' ? (
                    <>
                        Mitra baru menjadi downline langsung{' '}
                        <strong>{relative?.name}</strong>. Kode sponsor:{' '}
                        <strong>{relative?.referral_code}</strong>.
                    </>
                ) : placement === 'sibling' ? (
                    <>
                        Mitra baru sejajar dengan{' '}
                        <strong>{relative?.name}</strong> dan memakai sponsor
                        yang sama:{' '}
                        <strong>
                            {relative?.sponsor_partner_id
                                ? `Mitra #${relative.sponsor_partner_id}`
                                : 'Jaringan utama (tanpa sponsor)'}
                        </strong>
                        .
                    </>
                ) : (
                    <>
                        Mitra baru ditempatkan di jaringan utama, tanpa sponsor.
                    </>
                )}
            </div>
            <Field label="Email akun calon mitra">
                <input
                    className={inputClass}
                    type="email"
                    required
                    autoComplete="off"
                    placeholder="Email akun yang sudah terdaftar"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />
            </Field>
            <p className="text-sm text-muted-foreground">
                Gunakan akun yang aktif, sudah verifikasi email, dan belum
                menjadi mitra. Jika belum punya akun, calon mitra perlu
                mendaftar terlebih dahulu. Status awal menunggu; aktifkan
                setelah persetujuan kemitraan.
            </p>
            <Errors errors={form.errors} />
            <Button disabled={form.processing} className="justify-self-start">
                {form.processing ? 'Menambahkan...' : 'Tambahkan ke jaringan'}
            </Button>
        </form>
    );
}
function Editor({ partner: p }: { partner: Partner }) {
    const key = useRequestKey();
    const form = useForm({ status: '', idempotency_key: '' });
    return (
        <article className="grid gap-3 rounded-xl border p-5">
            <h2 className="font-semibold">
                {p.name} &middot; {p.status}
            </h2>
            <p>
                {p.email} &middot; {p.referral_code} &middot; Sponsor #
                {p.sponsor_partner_id ?? '-'}
            </p>
            <div className="flex gap-3">
                {['active', 'suspended', 'rejected']
                    .filter((s) => s !== p.status)
                    .map((s) => (
                        <Button
                            key={s}
                            variant="outline"
                            disabled={form.processing}
                            onClick={async () => {
                                if (
                                    !(await confirmAction(
                                        `${statusActions[s]} mitra?`,
                                        `Status mitra ${p.name} akan diperbarui.`,
                                        s !== 'active',
                                    ))
                                )
                                    return;
                                form.transform((d) => ({
                                    ...d,
                                    status: s,
                                    idempotency_key: key.get(),
                                }));
                                form.patch(update.url(p.id), {
                                    onSuccess: key.clear,
                                });
                            }}
                        >
                            {statusActions[s] ?? s}
                        </Button>
                    ))}
            </div>
            <Errors errors={form.errors} />
        </article>
    );
}
function PartnerListPage({
    partners,
}: {
    partners: Paginated<Partner>;
    networkSummary: PartnerNetworkData;
}) {
    return (
        <>
            <Head title="Kelola mitra" />
            <main className="grid gap-5 p-4 sm:p-6">
                <header>
                    <MenuTitle icon={MenuIcon} tone="blue">
                        Daftar mitra
                    </MenuTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Atur cabang jaringan, tambahkan downline, dan tinjau
                        status mitra sesuai sponsornya.
                    </p>
                </header>
                <section className="grid gap-4 rounded-xl border bg-card p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold">
                            Cara menambah kaki jaringan
                        </h2>
                        <RecordDialog
                            kind="add"
                            label="Tambah jaringan utama"
                            title="Tambah mitra tanpa sponsor"
                        >
                            <PlacementForm placement="root" />
                        </RecordDialog>
                    </div>
                    <div className="grid gap-3 text-sm sm:grid-cols-2">
                        <p className="rounded-lg bg-blue-50 p-4 text-blue-800 dark:bg-blue-950 dark:text-blue-200">
                            <ArrowDown className="mb-2 size-5" />
                            <strong>Tambah di bawah</strong>
                            <br />
                            Pilih pada baris Andi agar mitra baru menjadi
                            downline langsung Andi.
                        </p>
                        <p className="rounded-lg bg-emerald-50 p-4 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                            <ArrowRight className="mb-2 size-5" />
                            <strong>Tambah sejajar</strong>
                            <br />
                            Pilih pada baris Andi agar mitra baru menjadi
                            saudara jaringan Andi, dengan sponsor yang sama.
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Jaringan memakai hubungan sponsor, bukan slot
                        kiri/kanan. Banyak cabang boleh berada pada sponsor yang
                        sama. Penempatan tidak menghasilkan bonus perekrutan.
                    </p>
                </section>
                <ListFilters
                    placeholder="Nama, email, atau kode referral"
                    statuses={{
                        pending: 'Menunggu',
                        active: 'Aktif',
                        suspended: 'Ditangguhkan',
                        rejected: 'Ditolak',
                    }}
                />
                <DataTable
                    label="Daftar mitra"
                    rows={partners.data}
                    rowKey={(p) => p.id}
                    columns={[
                        { header: 'Nama', render: (p) => p.name },
                        { header: 'Email', render: (p) => p.email },
                        {
                            header: 'Kode referral',
                            render: (p) => (
                                <span className="font-mono text-xs">
                                    {p.referral_code}
                                </span>
                            ),
                        },
                        {
                            header: 'Sponsor',
                            render: (p) =>
                                p.sponsor_partner_id
                                    ? `Mitra #${p.sponsor_partner_id}`
                                    : 'Jaringan utama',
                        },
                        {
                            header: 'Status',
                            render: (p) => <StatusBadge status={p.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (p) => (
                                <RowActions>
                                    <RankOverrideDialog partner={p} />
                                    <RecordDialog
                                        kind="add"
                                        label="Tambah di bawah"
                                        title={`Tambah downline ${p.name}`}
                                        disabled={p.status !== 'active'}
                                    >
                                        <PlacementForm
                                            relative={p}
                                            placement="below"
                                        />
                                    </RecordDialog>
                                    <RecordDialog
                                        kind="add"
                                        label="Tambah sejajar"
                                        title={`Tambah mitra sejajar ${p.name}`}
                                    >
                                        <PlacementForm
                                            relative={p}
                                            placement="sibling"
                                        />
                                    </RecordDialog>
                                    <RecordDialog title={`Detail ${p.name}`}>
                                        <DetailFields
                                            rows={[
                                                ['Nama', p.name],
                                                ['Email', p.email],
                                                [
                                                    'Kode referral',
                                                    p.referral_code,
                                                ],
                                                [
                                                    'Sponsor',
                                                    p.sponsor_partner_id ?? '-',
                                                ],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={p.status}
                                                    />,
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                    <RecordDialog
                                        kind="edit"
                                        title={`Edit status ${p.name}`}
                                    >
                                        <Editor partner={p} />
                                    </RecordDialog>
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={partners} />
            </main>
        </>
    );
}

export default function Partners(props: {
    partners: Paginated<Partner>;
    networkSummary: PartnerNetworkData;
    performance: NetworkPerformance;
}) {
    const addRoot = (
        <RecordDialog
            kind="add"
            label="Tambah jaringan utama"
            title="Tambah mitra tanpa sponsor"
        >
            <PlacementForm placement="root" />
        </RecordDialog>
    );
    return (
        <NetworkWorkspace
            data={props.networkSummary}
            performance={props.performance}
            addRoot={addRoot}
            actions={(node) => {
                const relative = {
                    id: node.id,
                    name: node.name,
                    referral_code: node.referral_code ?? '',
                    sponsor_partner_id:
                        node.parent_id === 0 ? null : node.parent_id,
                };
                return (
                    <>
                        <RankOverrideDialog partner={node} />
                        <RecordDialog
                            kind="add"
                            label="Tambah di bawah"
                            title={`Tambah downline ${node.name}`}
                            disabled={node.status !== 'active'}
                        >
                            <PlacementForm
                                relative={relative}
                                placement="below"
                            />
                        </RecordDialog>
                        <RecordDialog
                            kind="add"
                            label="Tambah sejajar"
                            title={`Tambah mitra sejajar ${node.name}`}
                        >
                            <PlacementForm
                                relative={relative}
                                placement="sibling"
                            />
                        </RecordDialog>
                    </>
                );
            }}
        >
            <PartnerListPage {...props} />
        </NetworkWorkspace>
    );
}
