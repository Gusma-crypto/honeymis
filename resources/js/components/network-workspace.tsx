import { Head } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    ArrowUp,
    CalendarDays,
    Coins,
    Download,
    Expand,
    Maximize,
    Minus,
    Network,
    Plus,
    Search,
    ShoppingCart,
    Trophy,
    Users,
} from 'lucide-react';
import { MemberAvatar } from '@/components/member-avatar';
import { money } from '@/components/commerce-ui';
import { MenuTitle } from '@/components/menu-title';
import { layoutNetworkCards } from '@/lib/partner-hierarchy';
import { NetworkGenealogyCanvas } from '@/components/network-genealogy-canvas';
import type { HierarchyNode } from '@/lib/partner-hierarchy';
import type { PartnerNetworkData } from '@/components/partner-network-summary';

export type NetworkPerformance = Record<
    number,
    {
        rank: string;
        personal_sales: number;
        team_sales: number;
        commissions: number;
    }
> | null;
export function NetworkWorkspace({
    data,
    performance,
    children,
    addRoot,
    actions,
    ranks = {},
    ownSummary,
}: {
    data: PartnerNetworkData;
    performance: NetworkPerformance;
    children: ReactNode;
    addRoot: ReactNode;
    actions: (node: HierarchyNode) => ReactNode;
    ranks?: Record<number, string>;
    ownSummary?: {rank: string; personal_sales: number; team_sales: number; commissions: number};
}) {
    const [tab, setTab] = useState('Struktur Jaringan');
    const [rootId, setRootId] = useState(data.root_id);
    const [selectedId, setSelectedId] = useState(
        data.nodes.find((node) => node.id !== data.root_id)?.id ?? data.root_id,
    );
    const [zoom, setZoom] = useState(1);
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState(false);
    const [depth, setDepth] = useState(2);
    const [showActions, setShowActions] = useState(false);
    const chart = useRef<HTMLDivElement>(null);
    const byId = useMemo(
        () => new Map(data.nodes.map((node) => [node.id, node])),
        [data.nodes],
    );
    const childrenById = useMemo(() => {
        const map = new Map<number, HierarchyNode[]>();
        data.nodes.forEach((node) => {
            if (node.parent_id !== null)
                map.set(node.parent_id, [
                    ...(map.get(node.parent_id) ?? []),
                    node,
                ]);
        });
        return map;
    }, [data.nodes]);
    function branch(id: number): HierarchyNode[] {
        const result: HierarchyNode[] = [];
        const queue = [id];
        const seen = new Set<number>();
        for (let index = 0; index < queue.length; index++) {
            const node = byId.get(queue[index]);
            if (!node || seen.has(node.id)) continue;
            seen.add(node.id);
            result.push(node);
            (childrenById.get(node.id) ?? []).forEach((child) =>
                queue.push(child.id),
            );
        }
        return result;
    }
    const root = byId.get(rootId) ?? data.nodes[0];
    const scope = branch(rootId);
    const members = scope.filter((node) => node.id !== 0);
    const selected = byId.get(selectedId) ?? root;
    const selectedStats = performance?.[selected.id] ?? (selected.id === data.root_id ? ownSummary : undefined);
    const selectedDownline = Math.max(0, branch(selected.id).length - 1);
    const layout = useMemo(
        () => layoutNetworkCards(data.nodes, rootId, depth),
        [data.nodes, rootId, depth],
    );
    const totalSales = performance
        ? members.reduce(
              (sum, node) => sum + (performance[node.id]?.personal_sales ?? 0),
              0,
          )
        : rootId === data.root_id ? ownSummary?.team_sales ?? null : null;
    const totalCommission = performance
        ? members.reduce(
              (sum, node) => sum + (performance[node.id]?.commissions ?? 0),
              0,
          )
        : rootId === data.root_id ? ownSummary?.commissions ?? null : null;
    const top = members
        .filter((node) => node.id !== rootId)
        .sort(
            (a, b) =>
                (performance?.[b.id]?.team_sales ?? 0) -
                (performance?.[a.id]?.team_sales ?? 0),
        )
        .slice(0, 3);
    const levels = Array.from(
        { length: Math.max(5, Math.min(10, data.max_depth)) },
        (_, index) => ({
            level: index + 1,
            count: scope.filter((node) => node.level - root.level === index + 1)
                .length,
        }),
    );
    const matches = search.trim()
        ? members
              .filter((node) =>
                  `${node.name} ${node.referral_code} ${node.id}`
                      .toLowerCase()
                      .includes(search.trim().toLowerCase()),
              )
              .slice(0, 6)
        : [];
    function exportNetwork() {
        const escape = (value: string | number) =>
            `"${String(value)
                .replace(/^[=+@-]/, "'$&")
                .replaceAll('"', '""')}"`;
        const csv = [
            'ID,Nama,Kode referral,Sponsor,Level,Status',
            ...members.map((node) =>
                [
                    node.id,
                    node.name,
                    node.referral_code ?? '',
                    node.parent_id ?? '',
                    node.level,
                    node.status,
                ]
                    .map(escape)
                    .join(','),
            ),
        ].join('\r\n');
        const url = URL.createObjectURL(
            new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }),
        );
        const link = document.createElement('a');
        link.href = url;
        link.download = 'jaringan-mlm.csv';
        link.click();
        URL.revokeObjectURL(url);
    }
    return (
        <main className="network-page">
            <Head title="Jaringan MLM" />
            <header className="network-page-heading">
                <div>
                    <MenuTitle icon={Network} tone="amber">
                        Jaringan MLM
                    </MenuTitle>
                    <p>
                        Lihat struktur jaringan, downline, dan performa tim
                        Anda.
                    </p>
                </div>
                <div className="network-toolbar">
                    <label>
                        <Network size={16} />
                        <select
                            aria-label="Pilih cabang jaringan"
                            value={rootId}
                            onChange={(event) => {
                                const id = Number(event.target.value);
                                setRootId(id);
                                setSelectedId(id);
                            }}
                        >
                            <option value={data.root_id}>
                                Seluruh Jaringan
                            </option>
                            {data.nodes
                                .filter((node) => node.id !== data.root_id)
                                .map((node) => (
                                    <option key={node.id} value={node.id}>
                                        {node.name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <button className="network-primary" onClick={exportNetwork}>
                        <Download size={16} />
                        Export
                    </button>
                </div>
            </header>
            <nav className="network-tabs" aria-label="Tampilan jaringan">
                {[
                    'Struktur Jaringan',
                    'Daftar Downline',
                    'Pertumbuhan Tim',
                    'Genealogi',
                    'Peta Sebaran',
                ].map((item) => (
                    <button
                        key={item}
                        className={tab === item ? 'active' : ''}
                        onClick={() => {
                            setTab(item);
                            if (item === 'Genealogi') setDepth(4);
                            else setDepth(2);
                        }}
                    >
                        {item}
                    </button>
                ))}
            </nav>
            <section className="network-metrics">
                {[
                    {
                        label: 'Total Downline',
                        value: Math.max(0, scope.length - 1).toLocaleString(
                            'id-ID',
                        ),
                        icon: Users,
                        color: 'blue',
                        detail: `${members.filter((node) => node.status === 'active').length} mitra aktif`,
                    },
                    {
                        label: 'Generasi Aktif',
                        value: `${Math.max(0, ...scope.map((node) => node.level - root.level))} Level`,
                        icon: Network,
                        color: 'green',
                        detail: 'Komisi maksimal 5 generasi',
                    },
                    {
                        label: 'Omzet Tim',
                        value: totalSales === null ? '—' : money(totalSales),
                        icon: ShoppingCart,
                        color: 'amber',
                        detail: 'Penjualan valid bulan ini',
                    },
                    {
                        label: ownSummary ? 'Komisi Saya' : 'Komisi Tim',
                        value:
                            totalCommission === null
                                ? '—'
                                : money(totalCommission),
                        icon: Coins,
                        color: 'coral',
                        detail: 'Bersih setelah reversal',
                    },
                ].map(({ label, value, icon: Icon, color, detail }) => (
                    <article
                        className={`network-metric network-${color}`}
                        key={label}
                    >
                        <span>
                            <Icon size={31} />
                        </span>
                        <div>
                            <p>{label}</p>
                            <strong>{value}</strong>
                            <small>{detail}</small>
                        </div>
                    </article>
                ))}
            </section>
            {tab === 'Daftar Downline' ? (
                <div className="network-list-content">{children}</div>
            ) : tab === 'Peta Sebaran' ? (
                <section className="honey-panel network-location">
                    <Network size={42} />
                    <h2>Peta Sebaran Jaringan</h2>
                    <p>
                        Lokasi domisili mitra belum tersedia. Gunakan struktur
                        jaringan untuk menelusuri setiap cabang.
                    </p>
                    {levels
                        .filter((level) => level.count > 0)
                        .map((level) => (
                            <p key={level.level}>
                                Level {level.level}: {level.count} member
                            </p>
                        ))}
                </section>
            ) : tab === 'Pertumbuhan Tim' ? (
                <section className="honey-panel network-growth">
                    <h2>
                        <ArrowUp size={21} />
                        Pertumbuhan Tim Tahun Ini
                    </h2>
                    <p>Jumlah mitra berdasarkan tanggal bergabung.</p>
                    <div>
                        {Array.from({ length: 12 }, (_, month) => {
                            const count = members.filter(
                                (node) =>
                                    node.joined_at &&
                                    Number(
                                        new Intl.DateTimeFormat('en', {
                                            month: 'numeric',
                                            timeZone: 'Asia/Jakarta',
                                        }).format(new Date(node.joined_at)),
                                    ) ===
                                        month + 1 &&
                                    new Date(node.joined_at).getFullYear() ===
                                        new Date().getFullYear(),
                            ).length;
                            return (
                                <article key={month}>
                                    <strong>{count}</strong>
                                    <span
                                        style={{
                                            height: Math.max(
                                                4,
                                                Math.min(180, count * 12),
                                            ),
                                        }}
                                    />
                                    <small>
                                        {new Date(
                                            2026,
                                            month,
                                            1,
                                        ).toLocaleDateString('id-ID', {
                                            month: 'short',
                                        })}
                                    </small>
                                </article>
                            );
                        })}
                    </div>
                </section>
            ) : (
                <div className="network-content-grid">
                    <div className="network-main-column">
                        <section
                            className={`honey-panel network-genealogy ${expanded ? 'network-expanded' : ''}`}
                            ref={chart}
                        >
                            <header>
                                <h2>
                                    <Network size={21} />
                                    Struktur Jaringan (Genealogi)
                                </h2>
                                <div className="network-diagram-controls">
                                    <div className="network-search">
                                        <Search size={15} />
                                        <input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Cari member (nama / kode / ID)..."
                                            aria-label="Cari member jaringan"
                                        />
                                        {search && (
                                            <div className="network-search-results">
                                                {matches.length ? (
                                                    matches.map((node) => (
                                                        <button
                                                            key={node.id}
                                                            onClick={() => {
                                                                setRootId(
                                                                    node.id,
                                                                );
                                                                setSelectedId(
                                                                    node.id,
                                                                );
                                                                setSearch('');
                                                            }}
                                                        >
                                                            {node.name}
                                                            <small>
                                                                {
                                                                    node.referral_code
                                                                }
                                                            </small>
                                                        </button>
                                                    ))
                                                ) : (
                                                    <p>
                                                        Member tidak ditemukan.
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <button
                                        title="Reset tampilan"
                                        onClick={() => {
                                            setRootId(data.root_id);
                                            setZoom(1);
                                        }}
                                    >
                                        <Maximize size={16} />
                                    </button>
                                    <div className="network-zoom">
                                        <button
                                            aria-label="Perkecil"
                                            onClick={() =>
                                                setZoom(
                                                    Math.max(0.5, zoom - 0.1),
                                                )
                                            }
                                        >
                                            <Minus size={15} />
                                        </button>
                                        <b>{Math.round(zoom * 100)}%</b>
                                        <button
                                            aria-label="Perbesar"
                                            onClick={() =>
                                                setZoom(
                                                    Math.min(1.7, zoom + 0.1),
                                                )
                                            }
                                        >
                                            <Plus size={15} />
                                        </button>
                                    </div>
                                    <button
                                        aria-label={
                                            expanded
                                                ? 'Tutup layar penuh'
                                                : 'Perluas bagan'
                                        }
                                        onClick={() => setExpanded(!expanded)}
                                    >
                                        <Expand size={16} />
                                    </button>
                                </div>
                            </header>
                            <div className="network-diagram-scroll">
                                <div className="network-level-labels">
                                    {levels.slice(0, depth + 1).map((level) => (
                                        <div
                                            key={level.level}
                                            style={{
                                                top: 104 * level.level + 20,
                                            }}
                                        >
                                            <strong>Level {level.level}</strong>
                                            <small>
                                                ({level.count} member)
                                            </small>
                                        </div>
                                    ))}
                                </div>
                                <NetworkGenealogyCanvas
                                    layout={layout}
                                    selectedId={selectedId}
                                    performance={performance}
                                    ranks={ranks}
                                    zoom={zoom}
                                    childCounts={
                                        new Map(
                                            data.nodes.map((node) => [
                                                node.id,
                                                childrenById.get(node.id)
                                                    ?.length ?? 0,
                                            ]),
                                        )
                                    }
                                    onSelect={setSelectedId}
                                    onExpand={(id) => {
                                        setRootId(id);
                                        setSelectedId(id);
                                    }}
                                    onAdd={(node) => {
                                        setSelectedId(node.id);
                                        setShowActions(true);
                                    }}
                                />
                            </div>
                            {layout.truncated && (
                                <p className="network-note">
                                    Bagan dibatasi 150 member. Pilih cabang
                                    untuk melihat jaringan berikutnya.
                                </p>
                            )}
                        </section>
                        <section className="honey-panel network-top">
                            <header>
                                <h2>
                                    <Trophy size={23} />
                                    {performance ? 'Top Downline (Berdasarkan Omzet)' : 'Downline Jaringan Anda'}
                                </h2>
                                <small>Bulan Ini</small>
                            </header>
                            <div className="honey-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            {[
                                                '#',
                                                'Nama Member',
                                                'Peringkat',
                                                'Total Downline',
                                                'Omzet Tim',
                                                'Komisi',
                                                'Status',
                                            ].map((label) => (
                                                <th key={label}>{label}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {top.map((node, index) => (
                                            <tr
                                                key={node.id}
                                                onClick={() =>
                                                    setSelectedId(node.id)
                                                }
                                            >
                                                <td>
                                                    <b
                                                        className={`network-medal medal-${index}`}
                                                    >
                                                        {index + 1}
                                                    </b>
                                                </td>
                                                <td>
                                                    <button
                                                        className="network-table-member"
                                                        onClick={() =>
                                                            setSelectedId(
                                                                node.id,
                                                            )
                                                        }
                                                    >
                                                        <MemberAvatar
                                                            name={node.name}
                                                            gender={node.gender}
                                                            photo={node.avatar}
                                                        />
                                                        <strong>
                                                            {node.name}
                                                        </strong>
                                                        <small>
                                                            (
                                                            {node.referral_code}
                                                            )
                                                        </small>
                                                    </button>
                                                </td>
                                                <td>
                                                    <span
                                                        className={`network-rank rank-${performance?.[node.id]?.rank ?? ranks[node.id] ?? 'Member'}`}
                                                    >
                                                        {performance?.[node.id]
                                                            ?.rank ?? ranks[node.id] ?? 'Member'}
                                                    </span>
                                                </td>
                                                <td>
                                                    {Math.max(
                                                        0,
                                                        branch(node.id).length -
                                                            1,
                                                    )}
                                                </td>
                                                <td>
                                                    {performance
                                                        ? money(
                                                              performance[
                                                                  node.id
                                                              ]?.team_sales ??
                                                                  0,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td>
                                                    {performance
                                                        ? money(
                                                              performance[
                                                                  node.id
                                                              ]?.commissions ??
                                                                  0,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td>
                                                    <span
                                                        className={`honey-status honey-status-${node.status === 'active' ? 'paid' : 'processing'}`}
                                                    >
                                                        {node.status ===
                                                        'active'
                                                            ? 'Aktif'
                                                            : node.status ===
                                                                'pending'
                                                              ? 'Menunggu'
                                                              : node.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {top.length === 0 && (
                                    <p className="network-note">
                                        Belum ada downline pada cabang ini.
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>
                    <aside className="network-detail-column">
                        <section className="honey-panel network-detail">
                            <h2>Detail Member</h2>
                            <div className="network-detail-identity">
                                <MemberAvatar
                                    name={selected.name}
                                    gender={selected.gender}
                                    photo={selected.avatar}
                                />
                                <span>
                                    <strong>{selected.name}</strong>
                                    <small>
                                        {selected.referral_code ??
                                            'Pusat jaringan'}
                                    </small>
                                </span>
                                <span
                                    className={`network-rank rank-${selectedStats?.rank ?? ranks[selected.id] ?? 'Member'}`}
                                >
                                    {selected.id === 0
                                        ? 'Perusahaan'
                                        : (selectedStats?.rank ?? ranks[selected.id] ?? 'Member')}
                                </span>
                            </div>
                            <dl>
                                {[
                                    {
                                        icon: CalendarDays,
                                        label: 'Tanggal Bergabung',
                                        value: selected.joined_at
                                            ? new Date(
                                                  selected.joined_at,
                                              ).toLocaleDateString('id-ID', {
                                                  day: 'numeric',
                                                  month: 'short',
                                                  year: 'numeric',
                                                  timeZone: 'Asia/Jakarta',
                                              })
                                            : '—',
                                    },
                                    {
                                        icon: Network,
                                        label: 'Posisi',
                                        value: `Level ${selected.level}`,
                                    },
                                    {
                                        icon: Users,
                                        label: 'Total Downline',
                                        value: `${selectedDownline} member`,
                                    },
                                    {
                                        icon: ShoppingCart,
                                        label: 'Omzet Tim',
                                        value: selectedStats
                                            ? money(selectedStats.team_sales)
                                            : '—',
                                    },
                                    {
                                        icon: Coins,
                                        label: 'Total Komisi',
                                        value: selectedStats
                                            ? money(selectedStats.commissions)
                                            : '—',
                                    },
                                ].map(({ icon: Icon, label, value }) => (
                                    <div key={label}>
                                        <dt>
                                            <Icon size={16} />
                                            {label}
                                        </dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                            <button
                                className="network-member-detail-button"
                                onClick={() => setShowActions(!showActions)}
                            >
                                Lihat Detail Member <Expand size={14} />
                            </button>
                            <div
                                className={`network-placement-actions ${showActions ? '' : 'network-actions-hidden'}`}
                            >
                                {selected.id === 0
                                    ? addRoot
                                    : actions(selected)}
                            </div>
                        </section>
                        <section className="honey-panel network-summary">
                            <h2>Rangkuman Jaringan Anda</h2>
                            {levels.map((level) => (
                                <button
                                    key={level.level}
                                    onClick={() => {
                                        setDepth(level.level);
                                        setTab('Genealogi');
                                    }}
                                >
                                    <span>
                                        <Network size={18} />
                                        Level {level.level}
                                    </span>
                                    <b>{level.count} member</b>
                                    <span>›</span>
                                </button>
                            ))}
                        </section>
                        <div className="network-note">
                            Angka omzet dan komisi menggunakan data valid bulan
                            berjalan. Penempatan anggota tidak menghasilkan
                            bonus.
                        </div>
                    </aside>
                </div>
            )}
        </main>
    );
}
