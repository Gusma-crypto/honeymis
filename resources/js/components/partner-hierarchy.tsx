import { useMemo, useState } from 'react';
import { Expand, Network, RotateCcw, ZoomIn, ZoomOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { inputClass } from '@/components/commerce-ui';
import { layoutHierarchy } from '@/lib/partner-hierarchy';
import type { HierarchyLayout } from '@/lib/partner-hierarchy';
import type { PartnerNetworkData } from '@/components/partner-network-summary';

const colors: Record<string, string> = {
    active: '#10b981',
    pending: '#f59e0b',
    suspended: '#ef4444',
    rejected: '#f43f5e',
    unknown: '#94a3b8',
};
const statuses: Record<string, string> = {
    active: 'Aktif',
    pending: 'Menunggu',
    suspended: 'Ditangguhkan',
    rejected: 'Ditolak',
    unknown: 'Belum dimuat',
};
function HierarchyCanvas({
    layout,
    zoom = 1,
    onFocus,
}: {
    layout: HierarchyLayout;
    zoom?: number;
    onFocus?: (id: number) => void;
}) {
    return (
        <svg
            role={onFocus ? 'group' : 'img'}
            aria-label="Diagram hubungan sponsor jaringan mitra"
            viewBox={`0 0 ${layout.width} ${layout.height}`}
            width={onFocus ? layout.width * zoom : '100%'}
            height={onFocus ? layout.height * zoom : 170}
            className="max-w-none"
        >
            {layout.edges.map(({ parent, child }) => (
                <path
                    key={`${parent.id}-${child.id}`}
                    d={`M ${parent.x} ${parent.y + 76} C ${parent.x} ${parent.y + 102}, ${child.x} ${child.y - 26}, ${child.x} ${child.y}`}
                    fill="none"
                    stroke="var(--primary)"
                    strokeOpacity={0.4}
                    strokeWidth={2}
                />
            ))}
            {layout.nodes.map((node) => (
                <g
                    key={node.id}
                    role={onFocus ? 'button' : undefined}
                    tabIndex={onFocus ? 0 : undefined}
                    aria-label={
                        onFocus
                            ? `Fokus cabang ${node.name}, level ${node.level}, ${statuses[node.status] ?? node.status}`
                            : undefined
                    }
                    onClick={onFocus ? () => onFocus(node.id) : undefined}
                    onKeyDown={
                        onFocus
                            ? (e) => {
                                  if (e.key === 'Enter' || e.key === ' ') {
                                      e.preventDefault();
                                      onFocus(node.id);
                                  }
                              }
                            : undefined
                    }
                    className={
                        onFocus
                            ? 'cursor-pointer outline-none [&:focus-visible_rect]:stroke-primary [&:focus-visible_rect]:stroke-[3px]'
                            : ''
                    }
                >
                    <title>
                        {node.name} ? Level {node.level} ?{' '}
                        {statuses[node.status] ?? node.status}
                    </title>
                    <rect
                        x={node.x - 92}
                        y={node.y}
                        width={184}
                        height={76}
                        rx={12}
                        fill="var(--card)"
                        stroke={colors[node.status] ?? colors.unknown}
                        strokeWidth={1.5}
                    />
                    <circle
                        cx={node.x - 72}
                        cy={node.y + 22}
                        r={4}
                        fill={colors[node.status] ?? colors.unknown}
                    />
                    <text
                        x={node.x - 60}
                        y={node.y + 26}
                        fill="var(--foreground)"
                        fontSize={12}
                        fontWeight={600}
                    >
                        {node.name.length > 23
                            ? `${node.name.slice(0, 21)}?`
                            : node.name}
                    </text>
                    <text
                        x={node.x - 72}
                        y={node.y + 47}
                        fill="var(--muted-foreground)"
                        fontSize={11}
                    >
                        Level {node.level} ?{' '}
                        {statuses[node.status] ?? node.status}
                    </text>
                    <text
                        x={node.x - 72}
                        y={node.y + 63}
                        fill="var(--primary)"
                        fontSize={10}
                    >
                        {node.level === 0
                            ? 'Pangkal jaringan'
                            : `Mitra #${node.id}`}
                    </text>
                </g>
            ))}
        </svg>
    );
}
function HierarchyExplorer({ data }: { data: PartnerNetworkData }) {
    const [root, setRoot] = useState(data.root_id);
    const [depth, setDepth] = useState(3);
    const [zoom, setZoom] = useState(1);
    const layout = useMemo(
        () => layoutHierarchy(data.nodes, root, depth),
        [data.nodes, root, depth],
    );
    const focused = data.nodes.find((node) => node.id === root);
    return (
        <div className="grid min-w-0 gap-4">
            <div className="flex flex-wrap items-end gap-3">
                <label className="grid min-w-40 flex-1 gap-2 text-xs font-medium">
                    Cabang
                    <select
                        className={inputClass}
                        value={root}
                        onChange={(e) => setRoot(Number(e.target.value))}
                    >
                        <option value={data.root_id}>
                            {data.scope === 'company'
                                ? 'Seluruh perusahaan'
                                : 'Jaringan saya'}
                        </option>
                        {data.legs.map((leg, index) => (
                            <option key={leg.id} value={leg.id}>
                                Kaki {index + 1}: {leg.name}
                            </option>
                        ))}
                        {root !== data.root_id &&
                            !data.legs.some((leg) => leg.id === root) && (
                                <option value={root}>{focused?.name}</option>
                            )}
                    </select>
                </label>
                <label className="grid gap-2 text-xs font-medium">
                    Kedalaman tampilan
                    <select
                        className={inputClass}
                        value={depth}
                        onChange={(e) => setDepth(Number(e.target.value))}
                    >
                        {[1, 2, 3, 5, 10, 10000].map((value) => (
                            <option value={value} key={value}>
                                {value === 10000
                                    ? 'Semua level'
                                    : `${value} level di bawah titik fokus`}
                            </option>
                        ))}
                    </select>
                </label>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        aria-label="Perkecil diagram"
                        disabled={zoom <= 0.5}
                        onClick={() =>
                            setZoom((value) => Math.max(0.5, value - 0.1))
                        }
                    >
                        <ZoomOut className="size-4" />
                    </Button>
                    <span className="min-w-10 text-center text-xs">
                        {Math.round(zoom * 100)}%
                    </span>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        aria-label="Perbesar diagram"
                        disabled={zoom >= 1.5}
                        onClick={() =>
                            setZoom((value) => Math.min(1.5, value + 0.1))
                        }
                    >
                        <ZoomIn className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            setRoot(data.root_id);
                            setDepth(3);
                            setZoom(1);
                        }}
                    >
                        <RotateCcw className="size-4" />
                        Reset
                    </Button>
                </div>
            </div>
            <p className="text-xs leading-5 text-muted-foreground">
                Klik kotak mitra untuk fokus pada cabangnya. Geser area diagram
                untuk melihat cabang lain. Nomor level tetap dihitung dari
                pangkal jaringan awal.
            </p>
            <div
                className="max-h-[58vh] min-h-72 overflow-auto rounded-xl border bg-muted/20 p-2"
                style={{
                    backgroundImage:
                        'radial-gradient(var(--border) 1px, transparent 1px)',
                    backgroundSize: '20px 20px',
                }}
            >
                <HierarchyCanvas
                    layout={layout}
                    zoom={zoom}
                    onFocus={setRoot}
                />
            </div>
            {layout.truncated && (
                <p
                    role="status"
                    className="text-xs text-amber-700 dark:text-amber-300"
                >
                    Tampilan dibatasi 200 mitra agar tetap ringan. Pilih atau
                    klik cabang untuk menelusuri jaringan berikutnya.
                </p>
            )}
            <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                {Object.entries(statuses)
                    .filter(([status]) => status !== 'unknown')
                    .map(([status, label]) => (
                        <span
                            key={status}
                            className="inline-flex items-center gap-1.5"
                        >
                            <span
                                className="size-2 rounded-full"
                                style={{ backgroundColor: colors[status] }}
                            />
                            {label}
                        </span>
                    ))}
            </div>
        </div>
    );
}
export function PartnerHierarchy({ data }: { data: PartnerNetworkData }) {
    const [open, setOpen] = useState(false);
    const preview = useMemo(
        () => layoutHierarchy(data.nodes, data.root_id, 2, 12),
        [data.nodes, data.root_id],
    );
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    aria-label="Buka gambar simulasi hierarki MLM"
                    className="group grid overflow-hidden rounded-2xl border border-primary/20 bg-accent/15 text-left transition-colors hover:border-primary/50 hover:bg-accent/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:grid-cols-[1fr_1.3fr]"
                >
                    <span className="flex flex-col justify-center gap-3 p-6">
                        <span className="inline-flex items-center gap-2 font-semibold">
                            <Network className="size-5 text-primary" />
                            Simulasi hierarki MLM
                        </span>
                        <span className="text-sm leading-6 text-muted-foreground">
                            Klik gambar untuk melihat pohon sponsor, kaki, dan
                            level jaringan Anda.
                        </span>
                        <span className="inline-flex items-center gap-2 text-xs font-semibold text-primary">
                            <Expand className="size-4" />
                            Buka diagram jaringan
                        </span>
                    </span>
                    <span
                        className="pointer-events-none block border-t bg-card/60 p-4 sm:border-t-0 sm:border-l"
                        aria-hidden="true"
                    >
                        <HierarchyCanvas layout={preview} />
                    </span>
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[92dvh] overflow-y-auto sm:max-w-6xl">
                <DialogHeader>
                    <DialogTitle>Hierarki jaringan MLM</DialogTitle>
                    <DialogDescription>
                        Visualisasi data sponsor yang tersimpan. Tampilan level
                        tidak mengaktifkan bonus komisi bertingkat.
                    </DialogDescription>
                </DialogHeader>
                {open && <HierarchyExplorer data={data} />}
            </DialogContent>
        </Dialog>
    );
}
