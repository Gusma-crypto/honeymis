export type HierarchyNode = {
    id: number;
    parent_id: number | null;
    name: string;
    status: string;
    level: number;
    gender?: 'male' | 'female' | null;
    avatar?: string | null;
    referral_code?: string;
    joined_at?: string | null;
};
export type PositionedNode = HierarchyNode & { x: number; y: number };
export type NetworkCardNode = PositionedNode & {
    card_width: number;
    card_height: number;
    relative_level: number;
};

export function layoutNetworkCards(
    nodes: HierarchyNode[],
    rootId: number,
    depth = 2,
) {
    const base = layoutHierarchy(nodes, rootId, depth, 150);
    const rootLevel = nodes.find((node) => node.id === rootId)?.level ?? 0;
    const leafCount = base.nodes.filter(
        (node) => !base.edges.some((edge) => edge.parent.id === node.id),
    ).length;
    const firstLevelCount = base.nodes.filter(
        (node) => node.level - rootLevel === 1,
    ).length;
    const width = Math.max(
        900,
        leafCount * 82 + 140,
        firstLevelCount * 154 + 130,
    );
    const sidePadding = firstLevelCount === leafCount ? 154 : 115;
    const left = Math.min(...base.nodes.map((node) => node.x));
    const right = Math.max(...base.nodes.map((node) => node.x));
    const positioned: NetworkCardNode[] = base.nodes.map((node) => {
        const level = node.level - rootLevel;
        return {
            ...node,
            x:
                right === left
                    ? width / 2
                    : sidePadding +
                      ((node.x - left) / (right - left)) *
                          (width - sidePadding * 2),
            y: 15 + level * 104,
            card_width: level === 0 ? 184 : level === 1 ? 140 : 76,
            card_height: level === 0 ? 74 : level === 1 ? 64 : 86,
            relative_level: level,
        };
    });
    const byId = new Map(positioned.map((node) => [node.id, node]));
    const edges = base.edges.flatMap((edge) => {
        const parent = byId.get(edge.parent.id);
        const child = byId.get(edge.child.id);
        return parent && child ? [{ parent, child }] : [];
    });
    return {
        ...base,
        width,
        height: Math.max(365, 15 + depth * 104 + 145),
        nodes: positioned,
        edges,
    };
}
export type HierarchyLayout = {
    nodes: PositionedNode[];
    edges: { parent: PositionedNode; child: PositionedNode }[];
    width: number;
    height: number;
    truncated: boolean;
};

export function layoutHierarchy(
    nodes: HierarchyNode[],
    rootId: number,
    depthLimit = 3,
    limit = 200,
): HierarchyLayout {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const children = new Map<number, number[]>();
    for (const node of nodes) {
        if (node.parent_id !== null && node.id !== node.parent_id) {
            const siblings = children.get(node.parent_id) ?? [];
            siblings.push(node.id);
            children.set(node.parent_id, siblings);
        }
    }
    if (!byId.has(rootId))
        return {
            nodes: [],
            edges: [],
            width: 480,
            height: 160,
            truncated: false,
        };
    const queue = [{ id: rootId, depth: 0 }];
    const seen = new Set<number>();
    const visible = new Map<number, number>();
    let truncated = false;
    for (let cursor = 0; cursor < queue.length; cursor++) {
        const item = queue[cursor];
        if (seen.has(item.id) || !byId.has(item.id)) continue;
        seen.add(item.id);
        if (visible.size >= limit) {
            truncated = true;
            break;
        }
        visible.set(item.id, item.depth);
        if (item.depth < depthLimit) {
            for (const child of children.get(item.id) ?? [])
                queue.push({ id: child, depth: item.depth + 1 });
        }
    }
    const positions = new Map<number, PositionedNode>();
    let leaf = 0;
    function position(id: number): number {
        const childIds = (children.get(id) ?? []).filter(
            (child) => visible.get(child) === (visible.get(id) ?? 0) + 1,
        );
        const childX = childIds.map(position);
        const x = childX.length
            ? (childX[0] + childX[childX.length - 1]) / 2
            : 116 + leaf++ * 220;
        const node = byId.get(id);
        if (node)
            positions.set(id, {
                ...node,
                x,
                y: 24 + (visible.get(id) ?? 0) * 128,
            });
        return x;
    }
    position(rootId);
    const raw = [...positions.values()];
    const left = Math.min(...raw.map((node) => node.x - 92));
    const right = Math.max(...raw.map((node) => node.x + 92));
    const width = Math.max(480, right - left + 48);
    const shift = (width - (right - left)) / 2 - left;
    const result = raw.map((node) => ({ ...node, x: node.x + shift }));
    const centered = new Map(result.map((node) => [node.id, node]));
    const edges = result.flatMap((node) => {
        const parent =
            node.parent_id === null ? undefined : centered.get(node.parent_id);
        return parent && node.id !== rootId ? [{ parent, child: node }] : [];
    });
    return {
        nodes: result,
        edges,
        width,
        height: Math.max(160, ...result.map((node) => node.y + 104)),
        truncated,
    };
}
