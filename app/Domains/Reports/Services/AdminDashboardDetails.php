<?php

namespace App\Domains\Reports\Services;

use App\Domains\Partners\Models\Partner;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardDetails
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $data = [];
        if ($user->can('partners.manage')) {
            $data['members'] = Cache::remember('dashboard.members.v2', 15, fn (): array => [
                'total' => Partner::count(),
                'recent' => Partner::with('user:id,name')->latest('id')->limit(5)->get()
                    ->map(fn (Partner $partner): array => [
                        'id' => $partner->id, 'name' => $partner->user->name,
                        'status' => $partner->status, 'joinedAt' => $partner->created_at?->toIso8601String(),
                        'sponsorId' => $partner->sponsor_partner_id,
                    ])->all(),
            ]);
        }
        if ($user->can('reports.view') && $user->can('wallets.view')) {
            $data['analytics'] = Cache::remember('dashboard.analytics.v2.'.now('Asia/Jakarta')->year, 15, $this->analytics(...));
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function analytics(): array
    {
        $yearStart = now('Asia/Jakarta')->startOfYear();
        $from = $yearStart->copy()->utc();
        $until = $yearStart->copy()->addYear()->utc();
        $paidOrders = DB::table('orders')->where('fraud_hold', false)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('payments')
                ->whereColumn('payments.order_id', 'orders.id')->where('payments.status', 'verified')->whereNotNull('payments.verified_at'));
        $sales = (clone $paidOrders)->where('orders.placed_at', '>=', $from)->where('orders.placed_at', '<', $until)
            ->selectRaw($this->monthExpression('orders.placed_at').' as period, COUNT(*) as orders, SUM(subtotal - discount_total) as sales')
            ->groupBy('period')->get()->keyBy('period');
        $members = DB::table('partners')->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->selectRaw($this->monthExpression('created_at').' as period, COUNT(*) as members')->groupBy('period')->pluck('members', 'period');
        $commissions = DB::table('commission_entries')->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->selectRaw($this->monthExpression('created_at').' as period, SUM(amount - reversed_amount) as commissions')
            ->groupBy('period')->pluck('commissions', 'period');
        $series = [];
        for ($month = 0; $month < 12; $month++) {
            $date = $yearStart->copy()->addMonths($month);
            $date->locale('id');
            $key = $date->format('Y-m');
            $series[] = [
                'period' => $key, 'label' => $date->isoFormat('MMM'),
                'orders' => (int) ($sales->get($key)->orders ?? 0), 'sales' => (int) ($sales->get($key)->sales ?? 0),
                'members' => (int) ($members[$key] ?? 0), 'commissions' => (int) ($commissions[$key] ?? 0),
            ];
        }
        $topProducts = DB::table('order_items')->joinSub((clone $paidOrders)->select('orders.id'), 'valid_orders', fn ($join) => $join->on('valid_orders.id', '=', 'order_items.order_id'))
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->select('products.id', 'products.name')->selectRaw('SUM(order_items.quantity) as sold, MIN(order_items.unit_price_snapshot) as price')
            ->groupBy('products.id', 'products.name')->orderByDesc('sold')->orderBy('products.id')->limit(5)->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => $row->name, 'sold' => (int) $row->sold, 'price' => (int) $row->price])->all();
        $typeColumn = Schema::hasColumn('commission_entries', 'commission_type') ? 'commission_type' : 'status';
        $breakdown = DB::table('commission_entries')->select($typeColumn)->selectRaw('SUM(amount - reversed_amount) as total')
            ->groupBy($typeColumn)->pluck('total', $typeColumn)->map(fn ($amount): int => (int) $amount)->all();

        return ['year' => $yearStart->year, 'series' => $series, 'topProducts' => $topProducts, 'commissionBreakdown' => $breakdown];
    }

    /**
     * @param  'created_at'|'orders.placed_at'  $column
     * @return literal-string
     */
    private function monthExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', ".$column.", '+7 hours')",
            'pgsql' => 'to_char('.$column." AT TIME ZONE 'Asia/Jakarta', 'YYYY-MM')",
            default => 'DATE_FORMAT(DATE_ADD('.$column.", INTERVAL 7 HOUR), '%Y-%m')",
        };
    }
}
