<?php

namespace App\Domains\Partners\Services;

use App\Domains\Partners\Models\Partner;
use Illuminate\Support\Facades\Cache;

class NetworkSummary
{
    /** @return array<string, mixed> */
    public function forRoot(?int $rootId = null): array
    {
        $rows = Cache::remember('partners.network.graph.v1', 15, fn (): array => Partner::query()
            ->join('users', 'users.id', '=', 'partners.user_id')
            ->orderBy('partners.id')->get(['partners.id', 'partners.sponsor_partner_id', 'partners.status', 'users.name'])
            ->map(fn (Partner $partner): array => ['id' => $partner->id, 'parent' => $partner->sponsor_partner_id, 'name' => $partner->getAttribute('name'), 'status' => $partner->status])->all());
        $nodes = [];
        $children = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $nodes[$id] = $row;
            $children[$row['parent'] ?? 0][] = $id;
        }
        $starts = $rootId === null ? ($children[0] ?? []) : ($children[$rootId] ?? []);
        $hierarchy = [['id' => $rootId ?? 0, 'parent_id' => null, 'name' => $rootId === null ? 'HoneyCommerce' : ($nodes[$rootId]['name'] ?? 'Mitra Anda'), 'status' => $rootId === null ? 'active' : ($nodes[$rootId]['status'] ?? 'unknown'), 'level' => 0]];
        $queue = [];
        $legs = [];
        foreach ($starts as $id) {
            $queue[] = [$id, 1, $id];
            $legs[$id] = ['id' => $id, 'name' => $nodes[$id]['name'], 'status' => $nodes[$id]['status'], 'members' => 0, 'max_depth' => 0];
        }
        $seen = $rootId === null ? [] : [$rootId => true];
        $levels = [];
        $cycles = false;
        $maxDepth = 0;
        $total = 0;
        $active = 0;
        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            [$id, $depth, $leg] = $queue[$cursor];
            if (isset($seen[$id])) {
                $cycles = true;

                continue;
            }
            $seen[$id] = true;
            $total++;
            $hierarchy[] = ['id' => $id, 'parent_id' => (int) ($nodes[$id]['parent'] ?? 0), 'name' => $nodes[$id]['name'], 'status' => $nodes[$id]['status'], 'level' => $depth];
            $status = $nodes[$id]['status'];
            $active += $status === 'active' ? 1 : 0;
            $maxDepth = max($maxDepth, $depth);
            $levels[$depth] ??= ['level' => $depth, 'members' => 0, 'active' => 0, 'pending' => 0, 'suspended' => 0, 'rejected' => 0];
            $levels[$depth]['members']++;
            if (array_key_exists($status, $levels[$depth])) {
                $levels[$depth][$status]++;
            }
            $legs[$leg]['members']++;
            $legs[$leg]['max_depth'] = max($legs[$leg]['max_depth'] ?? 0, $depth);
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = [$child, $depth + 1, $leg];
            }
        }
        ksort($levels);
        if ($rootId === null && count($seen) !== count($nodes)) {
            $cycles = true;
        }

        return ['root_id' => $rootId ?? 0, 'nodes' => $hierarchy, 'scope' => $rootId === null ? 'company' : 'own', 'direct_count' => count($starts), 'total_members' => $total,
            'active_members' => $active, 'max_depth' => $maxDepth, 'levels' => array_values($levels), 'legs' => array_values($legs), 'has_cycles' => $cycles];
    }
}
