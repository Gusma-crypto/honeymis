import { PartnerHierarchy } from '@/components/partner-hierarchy';
import type { HierarchyNode } from '@/lib/partner-hierarchy';
import { GitBranch, Layers3, Network, Users } from 'lucide-react';
import { DataTable, StatusBadge } from '@/components/data-list';

export type PartnerNetworkData = {
    scope: 'company' | 'own';
    root_id: number;
    nodes: HierarchyNode[];
    direct_count: number;
    total_members: number;
    active_members: number;
    max_depth: number;
    has_cycles: boolean;
    levels: {
        level: number;
        members: number;
        active: number;
        pending: number;
        suspended: number;
        rejected: number;
    }[];
    legs: {
        id: number;
        name: string;
        status: string;
        members: number;
        max_depth: number;
    }[];
};
export function PartnerNetworkSummary({ data }: { data: PartnerNetworkData }) {
    const number = (value: number) => value.toLocaleString('id-ID');
    return (
        <section className="grid gap-5 rounded-2xl border border-primary/20 bg-card p-5 sm:p-6">
            <header>
                <p className="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                    Struktur jaringan mitra
                </p>
                <h2 className="flex items-center gap-2 text-xl font-semibold">
                    <Network className="size-5 text-primary" />
                    {data.max_depth > 0
                        ? `Jaringan sudah sampai level ${data.max_depth}`
                        : 'Belum ada jaringan mitra'}
                </h2>
                <p className="mt-2 text-xs leading-6 text-muted-foreground">
                    {data.scope === 'company'
                        ? 'Ringkasan seluruh struktur sponsor. Level 1 adalah mitra tanpa sponsor.'
                        : 'Level 1 adalah mitra langsung di bawah Anda; level berikutnya adalah jaringan di bawah mereka.'}{' '}
                    Jumlah kaki berarti cabang sponsor langsung, bukan skema
                    binary. Komisi jaringan dibatasi maksimal lima generasi dan
                    dihitung dari penjualan produk yang valid.
                </p>
            </header>
            <PartnerHierarchy data={data} />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    {
                        label:
                            data.scope === 'company'
                                ? 'Jaringan utama'
                                : 'Kaki / mitra langsung',
                        value: data.direct_count,
                        Icon: GitBranch,
                    },
                    {
                        label:
                            data.scope === 'company'
                                ? 'Total mitra'
                                : 'Total downline',
                        value: data.total_members,
                        Icon: Users,
                    },
                    {
                        label: 'Kedalaman jaringan',
                        value: data.max_depth,
                        Icon: Layers3,
                    },
                    {
                        label: 'Mitra aktif dalam struktur',
                        value: data.active_members,
                        Icon: Network,
                    },
                ].map(({ label, value, Icon }) => (
                    <article
                        key={label}
                        className="rounded-xl border bg-accent/25 p-4"
                    >
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                            <Icon className="size-4 text-primary" />
                        </div>
                        <p className="text-2xl font-semibold">
                            {number(value)}
                        </p>
                    </article>
                ))}
            </div>
            {data.has_cycles && (
                <p
                    role="status"
                    className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    Ada hubungan sponsor yang tidak membentuk pohon valid. Angka
                    level hanya mencakup cabang yang dapat ditelusuri;
                    administrator perlu meninjau data.
                </p>
            )}
            <div className="grid gap-3">
                <h3 className="text-sm font-semibold">
                    Sebaran mitra per level
                </h3>
                <DataTable
                    label="Jumlah mitra per level"
                    rows={data.levels}
                    rowKey={(row) => row.level}
                    empty="Belum ada jaringan di bawah level ini."
                    columns={[
                        {
                            header: 'Level',
                            render: (row) => (
                                <span className="font-semibold text-primary">
                                    Level {row.level}
                                </span>
                            ),
                        },
                        {
                            header: 'Jumlah mitra',
                            render: (row) => number(row.members),
                        },
                        {
                            header: 'Aktif',
                            render: (row) => number(row.active),
                        },
                        {
                            header: 'Menunggu',
                            render: (row) => number(row.pending),
                        },
                        {
                            header: 'Ditangguhkan',
                            render: (row) => number(row.suspended),
                        },
                        {
                            header: 'Ditolak',
                            render: (row) => number(row.rejected),
                        },
                    ]}
                />
            </div>
            <div className="grid gap-3">
                <h3 className="text-sm font-semibold">
                    {data.scope === 'company'
                        ? 'Cabang jaringan utama'
                        : 'Rincian setiap kaki langsung'}
                </h3>
                <DataTable
                    label="Cabang sponsor langsung"
                    rows={data.legs}
                    rowKey={(row) => row.id}
                    searchable
                    localFilter={{
                        label: 'Status',
                        options: {
                            active: 'Aktif',
                            pending: 'Menunggu',
                            suspended: 'Ditangguhkan',
                            rejected: 'Ditolak',
                        },
                        value: (row) => row.status,
                    }}
                    columns={[
                        {
                            header: 'Kaki',
                            render: (_row, index) => `Kaki ${index + 1}`,
                        },
                        { header: 'Mitra', render: (row) => row.name },
                        {
                            header: 'Status',
                            render: (row) => (
                                <StatusBadge status={row.status} />
                            ),
                        },
                        {
                            header: 'Mitra dalam cabang',
                            render: (row) => number(row.members),
                        },
                        {
                            header: 'Level terdalam',
                            render: (row) => number(row.max_depth),
                        },
                    ]}
                />
            </div>
            <p className="text-[11px] text-muted-foreground">
                Angka jaringan mencakup status pendaftaran mitra, bukan jumlah
                transaksi atau hak komisi. Data ringkasan disimpan sementara
                maksimal 15 detik.
            </p>
        </section>
    );
}
