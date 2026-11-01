import { Check, Plus, Users } from 'lucide-react';
import { MemberAvatar } from '@/components/member-avatar';
import type { HierarchyNode } from '@/lib/partner-hierarchy';
import { layoutNetworkCards } from '@/lib/partner-hierarchy';
import type { NetworkPerformance } from '@/components/network-workspace';

export function NetworkGenealogyCanvas({
    layout,
    selectedId,
    performance,
    childCounts,
    onSelect,
    onExpand,
    onAdd,
    zoom,
    ranks = {},
}: {
    layout: ReturnType<typeof layoutNetworkCards>;
    selectedId: number;
    performance: NetworkPerformance;
    childCounts: Map<number, number>;
    onSelect: (id: number) => void;
    onExpand: (id: number) => void;
    onAdd: (node: HierarchyNode) => void;
    zoom: number;
    ranks?: Record<number, string>;
}) {
    return (
        <svg
            className="network-reference-canvas"
            viewBox={`0 0 ${layout.width} ${layout.height}`}
            style={{
                width: `${zoom * 100}%`,
                minWidth: layout.width > 950 ? layout.width * zoom : undefined,
            }}
            role="group"
            aria-label="Struktur jaringan dengan kartu per generasi"
        >
            <defs>
                <filter
                    id="network-card-shadow"
                    x="-20%"
                    y="-20%"
                    width="140%"
                    height="155%"
                >
                    <feDropShadow
                        dx="0"
                        dy="2"
                        stdDeviation="2"
                        floodColor="#204177"
                        floodOpacity=".10"
                    />
                </filter>
            </defs>
            {layout.edges.map(({ parent, child }) => {
                const middle = child.y - 18;
                return (
                    <path
                        key={`${parent.id}-${child.id}`}
                        d={`M${parent.x} ${parent.y + parent.card_height}V${middle}H${child.x}Q${child.x} ${middle} ${child.x} ${middle + 9}V${child.y}`}
                        fill="none"
                        stroke="#648dff"
                        strokeWidth="1.5"
                        strokeLinejoin="round"
                    />
                );
            })}
            {layout.nodes.map((node) => {
                const small = node.relative_level >= 2;
                const rank =
                    performance?.[node.id]?.rank ?? ranks[node.id] ?? 'Member';
                const left = node.x - node.card_width / 2;
                return (
                    <g
                        key={node.id}
                        className={`network-reference-node ${node.id === selectedId ? 'selected' : ''}`}
                        role="button"
                        tabIndex={0}
                        aria-label={`Pilih ${node.name}`}
                        onClick={() => onSelect(node.id)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                onSelect(node.id);
                            }
                        }}
                    >
                        <rect
                            x={left}
                            y={node.y}
                            width={node.card_width}
                            height={node.card_height}
                            rx={small ? 11 : 12}
                            fill={
                                node.relative_level === 0
                                    ? 'var(--network-root-fill)'
                                    : 'var(--card)'
                            }
                            stroke={
                                node.id === selectedId ||
                                node.relative_level === 0
                                    ? '#3266ff'
                                    : '#d7e1f7'
                            }
                            strokeWidth={node.relative_level === 0 ? 1.7 : 1.15}
                            filter="url(#network-card-shadow)"
                        />
                        <foreignObject
                            x={left}
                            y={node.y}
                            width={node.card_width}
                            height={node.card_height}
                        >
                            <div
                                className={`network-card-body ${small ? 'small' : ''}`}
                            >
                                <div className="network-card-portrait">
                                    <MemberAvatar
                                        name={node.name}
                                        gender={node.gender}
                                        photo={node.avatar}
                                    />
                                    {node.status === 'active' && (
                                        <i>
                                            <Check size={9} />
                                        </i>
                                    )}
                                </div>
                                <div className="network-card-copy">
                                    <strong title={node.name}>
                                        {node.relative_level === 0
                                            ? node.name
                                            : small
                                              ? node.name.split(' ')[0]
                                              : node.name
                                                    .split(' ')
                                                    .map((part, index) =>
                                                        index === 0
                                                            ? part
                                                            : `${part[0]}.`,
                                                    )
                                                    .slice(0, 2)
                                                    .join(' ')}
                                    </strong>
                                    <span
                                        className={`network-rank rank-${rank}`}
                                    >
                                        {node.id === 0 ? 'Perusahaan' : rank}
                                    </span>
                                    <small>
                                        {node.referral_code ?? 'Pusat Jaringan'}
                                    </small>
                                </div>
                            </div>
                        </foreignObject>
                        {node.relative_level >= 2 && (
                            <>
                                <path
                                    d={`M${node.x} ${node.y + node.card_height}V${node.y + node.card_height + 14}`}
                                    stroke="#d6def5"
                                />
                                <foreignObject
                                    x={node.x - 24}
                                    y={node.y + node.card_height + 14}
                                    width="48"
                                    height="24"
                                >
                                    <button
                                        className="network-descendant-pill"
                                        title={`Lihat anggota di bawah ${node.name}`}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onExpand(node.id);
                                        }}
                                    >
                                        <Users size={12} />
                                        {childCounts.get(node.id) ?? 0}
                                    </button>
                                </foreignObject>
                                <foreignObject
                                    x={node.x - 12}
                                    y={node.y + node.card_height + 43}
                                    width="24"
                                    height="24"
                                >
                                    <button
                                        className="network-plus-slot"
                                        aria-label={`Tambah di bawah ${node.name}`}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onAdd(node);
                                        }}
                                    >
                                        <Plus size={12} />
                                    </button>
                                </foreignObject>
                            </>
                        )}
                    </g>
                );
            })}
        </svg>
    );
}
