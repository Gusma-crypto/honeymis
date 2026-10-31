import assert from 'node:assert/strict';
import { test } from 'node:test';
import { layoutHierarchy } from '../resources/js/lib/partner-hierarchy.ts';

const node = (id, parent_id, level) => ({
    id,
    parent_id,
    level,
    name: `Mitra ${id}`,
    status: 'active',
});
const tree = [
    node(1, null, 0),
    node(2, 1, 1),
    node(3, 1, 1),
    node(4, 2, 2),
    node(99, null, 0),
];

void test('layout includes only connected descendants and accurate sponsor links', () => {
    const layout = layoutHierarchy(tree, 1);
    assert.deepEqual(
        layout.nodes.map((value) => value.id).sort((a, b) => a - b),
        [1, 2, 3, 4],
    );
    assert.deepEqual(
        layout.edges
            .map(({ parent, child }) => `${parent.id}-${child.id}`)
            .sort(),
        ['1-2', '1-3', '2-4'],
    );
    for (const { parent, child } of layout.edges) assert.ok(child.y > parent.y);
});
void test('depth and focused branch hide other branches without changing original level numbers', () => {
    assert.deepEqual(
        layoutHierarchy(tree, 1, 1)
            .nodes.map((value) => value.id)
            .sort((a, b) => a - b),
        [1, 2, 3],
    );
    const focused = layoutHierarchy(tree, 2);
    assert.deepEqual(
        focused.nodes.map((value) => value.id).sort((a, b) => a - b),
        [2, 4],
    );
    assert.equal(focused.nodes.find((value) => value.id === 4).level, 2);
});
void test('large diagrams are bounded and report truncation', () => {
    const nodes = [
        node(1, null, 0),
        ...Array.from({ length: 500 }, (_, index) => node(index + 2, 1, 1)),
    ];
    const layout = layoutHierarchy(nodes, 1, 3);
    assert.equal(layout.nodes.length, 200);
    assert.equal(layout.truncated, true);
});
void test('cycles and missing roots do not cause recursion loops', () => {
    const layout = layoutHierarchy([node(1, 2, 0), node(2, 1, 1)], 1);
    assert.equal(layout.nodes.length, 2);
    assert.equal(layout.edges.length, 1);
    assert.deepEqual(layoutHierarchy(tree, 700).nodes, []);
});

void test('a single branch remains centered and nodes stay inside the canvas', () => {
    const layout = layoutHierarchy([node(1, null, 0), node(2, 1, 1)], 1);
    assert.equal(
        layout.nodes.find((value) => value.id === 1).x,
        layout.width / 2,
    );
    for (const value of layout.nodes) {
        assert.ok(value.x >= 92 && value.x <= layout.width - 92);
    }
});
