export type HierarchyNode = {
    id: number;
    parent_id: number | null;
    name: string;
    status: string;
    level: number;
};
export type PositionedNode = HierarchyNode & { x: number; y: number };
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
