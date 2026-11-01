<?php

namespace App\Domains\Reports\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportWorkspace
{
    public function __construct(private CommerceReport $reports) {}

    /** @return array<string, mixed> */
    public function present(User $user, string $from, string $until, string $tab, string $trend, string $topPeriod): array
    {
        Gate::forUser($user)->authorize('reports.view');
        $range = $this->reports->range($from, $until);
        $start = CarbonImmutable::parse($from, 'Asia/Jakarta');
        $end = CarbonImmutable::parse($until, 'Asia/Jakarta');
        $previousUntil = $start->subDay();
        $previousFrom = $start->day === 1 && $end->isLastOfMonth()
            ? $start->subMonthNoOverflow()->startOfMonth()
            : $start->subDays((int) $start->diffInDays($end) + 1);
        $previous = $this->reports->range($previousFrom->toDateString(), $previousUntil->toDateString());
        $stats = $this->totals($user, $range['from'], $range['until']);
        $prior = $this->totals($user, $previous['from'], $previous['until']);
        $changes = [];
        foreach ($stats as $key => $value) {
            $changes[$key] = $prior[$key] === 0 ? ($value === 0 ? 0 : null) : round(($value - $prior[$key]) * 100 / $prior[$key], 1);
        }
        $topFrom = match ($topPeriod) {
            'previous' => $start->subMonthNoOverflow()->startOfMonth(),
            'year' => $end->startOfYear(),
            default => $start,
        };
        $topUntil = $topPeriod === 'previous' ? $topFrom->endOfMonth() : $end;
        $topRange = $this->reports->range($topFrom->toDateString(), $topUntil->toDateString());
        $productQuery = $this->products($range['from'], $range['until']);
        $productTotal = (int) DB::query()->fromSub((clone $productQuery)->reorder(), 'product_sales')->sum('sales');
        $distribution = (clone $productQuery)->limit(5)->get()->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name, 'quantity' => (int) $row->quantity, 'sales' => (int) $row->sales])->all();
        $remaining = $productTotal - array_sum(array_column($distribution, 'sales'));
        if ($remaining > 0) {
            $distribution[] = ['id' => 0, 'name' => 'Produk Lainnya', 'quantity' => 0, 'sales' => $remaining];
        }

        return [
            'stats' => $stats, 'changes' => $changes,
            'trend' => $this->trend($user, $end, $trend),
            'distribution' => $distribution,
            'performers' => $this->performers($user, $topRange['from'], $topRange['until'], $topUntil->startOfMonth()->toDateString()),
            'details' => $tab === 'summary' ? null : $this->detailQuery($user, $tab, $range['from'], $range['until'])->paginate(15)->withQueryString(),
        ];
    }

    private function paid(CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return DB::table('orders')->where('orders.placed_at', '>=', $from)->where('orders.placed_at', '<', $until)
            ->where('orders.fraud_hold', false)->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered', 'completed', 'refunded'])
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('payments')->whereColumn('payments.order_id', 'orders.id')
                ->whereIn('payments.status', ['verified', 'refunded'])->whereNotNull('payments.verified_at'));
    }

    /** @return array<string, int> */
    private function totals(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $paid = $this->paid($from, $until);
        $refunds = DB::table('refunds')->whereIn('order_id', (clone $paid)->select('orders.id'))->where('status', 'processed')->sum('amount');
        $stats = ['sales' => (int) (clone $paid)->sum('orders.grand_total') - (int) $refunds,
            'members' => DB::table('partners')->join('users', 'users.id', '=', 'partners.user_id')->where('partners.created_at', '<', $until)->where('partners.status', 'active')->where('users.status', 'active')->count(),
            'downline' => DB::table('partners')->where('created_at', '<', $until)->whereNotNull('sponsor_partner_id')->count()];
        if ($user->can('wallets.view')) {
            $stats['commissions'] = (int) DB::table('commission_entries')->join('order_items', 'order_items.id', '=', 'commission_entries.order_item_id')
                ->whereIn('order_items.order_id', (clone $paid)->select('orders.id'))->sum(DB::raw('commission_entries.amount - commission_entries.reversed_amount'));
        }

        return $stats;
    }

    /** @return list<array<string, mixed>> */
    private function trend(User $user, CarbonImmutable $end, string $trend): array
    {
        $first = $trend === 'year' ? $end->startOfYear() : $end->startOfMonth()->subMonths($trend === '12' ? 11 : 5);
        $range = $this->reports->range($first->toDateString(), $end->toDateString());
        $paid = $this->paid($range['from'], $range['until']);
        $monthExpression = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', orders.placed_at, '+7 hours')",
            'pgsql' => "to_char(orders.placed_at AT TIME ZONE 'Asia/Jakarta', 'YYYY-MM')",
            default => "DATE_FORMAT(DATE_ADD(orders.placed_at, INTERVAL 7 HOUR), '%Y-%m')",
        };
        $sales = (clone $paid)->selectRaw($monthExpression.' as period, SUM(orders.grand_total) as sales, COUNT(*) as orders')->groupBy('period')->get()->keyBy('period');
        $refunds = DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id')->whereIn('orders.id', (clone $paid)->select('orders.id'))
            ->where('refunds.status', 'processed')->selectRaw($monthExpression.' as period, SUM(refunds.amount) as amount')->groupBy('period')->pluck('amount', 'period');
        $commissions = $user->can('wallets.view') ? DB::table('commission_entries')->join('order_items', 'order_items.id', '=', 'commission_entries.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')->whereIn('orders.id', (clone $paid)->select('orders.id'))
            ->selectRaw($monthExpression.' as period, SUM(commission_entries.amount - commission_entries.reversed_amount) as amount')->groupBy('period')->pluck('amount', 'period') : collect();
        $series = [];
        for ($month = $first; $month <= $end->startOfMonth(); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $month->locale('id');
            $series[] = ['period' => $key, 'label' => $month->isoFormat('MMM'), 'sales' => (int) ($sales->get($key)->sales ?? 0) - (int) ($refunds[$key] ?? 0),
                ...($user->can('wallets.view') ? ['commissions' => (int) ($commissions[$key] ?? 0)] : []),
                'orders' => (int) ($sales->get($key)->orders ?? 0)];
        }

        return $series;
    }

    private function products(CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        $refunds = DB::table('refunds')->where('status', 'processed')->whereNotNull('order_item_id')->groupBy('order_item_id')->selectRaw('order_item_id, SUM(amount) as amount');

        return DB::table('order_items')->joinSub($this->paid($from, $until)->select('orders.id'), 'paid_orders', fn ($join) => $join->on('paid_orders.id', '=', 'order_items.order_id'))
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoinSub($refunds, 'refund_totals', fn ($join) => $join->on('refund_totals.order_item_id', '=', 'order_items.id'))
            ->select('products.id', 'products.name')->selectRaw('SUM(order_items.quantity) as quantity, SUM(order_items.unit_price_snapshot * order_items.quantity - order_items.discount_snapshot - COALESCE(refund_totals.amount, 0)) as sales')
            ->groupBy('products.id', 'products.name')->orderByDesc('sales')->orderBy('products.id');
    }

    /** @return list<array<string, mixed>> */
    private function performers(User $user, CarbonImmutable $from, CarbonImmutable $until, string $period): array
    {
        $sales = $this->paid($from, $until)->whereNotNull('partner_referral_id')->selectRaw('partner_referral_id, SUM(subtotal - discount_total) as sales')->groupBy('partner_referral_id')->pluck('sales', 'partner_referral_id');
        $refunds = DB::table('refunds')->joinSub($this->paid($from, $until)->select('orders.id', 'orders.partner_referral_id'), 'paid_orders', fn ($join) => $join->on('paid_orders.id', '=', 'refunds.order_id'))
            ->where('refunds.status', 'processed')->whereNotNull('refunds.order_item_id')->groupBy('paid_orders.partner_referral_id')->selectRaw('paid_orders.partner_referral_id, SUM(refunds.amount) as amount')->pluck('amount', 'partner_referral_id');
        $partners = DB::table('partners')->join('users', 'users.id', '=', 'partners.user_id')->where('partners.created_at', '<', $until)
            ->get(['partners.id', 'partners.sponsor_partner_id', 'partners.referral_code', 'users.name', 'users.gender', 'users.profile_photo_path'])->keyBy('id');
        $teams = [];
        foreach ($sales as $id => $amount) {
            $net = max(0, (int) $amount - (int) ($refunds[$id] ?? 0));
            $sales[$id] = $net;
            $seen = [(int) $id => true];
            $parent = $partners->get($id)?->sponsor_partner_id;
            while ($parent !== null && ! isset($seen[$parent])) {
                $seen[$parent] = true;
                $teams[$parent] = ($teams[$parent] ?? 0) + $net;
                $parent = $partners->get($parent)?->sponsor_partner_id;
            }
        }
        $ranks = DB::table('partner_monthly_ranks')->where('period_start', $period)->pluck('rank', 'partner_id');
        $commissions = $user->can('wallets.view') ? DB::table('commission_entries')->join('order_items', 'order_items.id', '=', 'commission_entries.order_item_id')
            ->whereIn('order_items.order_id', $this->paid($from, $until)->select('orders.id'))->groupBy('beneficiary_partner_id')
            ->selectRaw('beneficiary_partner_id, SUM(amount - reversed_amount) as amount')->pluck('amount', 'beneficiary_partner_id') : collect();

        return array_values($partners->map(fn (object $partner): array => ['id' => (int) $partner->id, 'name' => $partner->name, 'code' => $partner->referral_code,
            'gender' => $partner->gender, 'avatar' => $partner->profile_photo_path === null ? null : Storage::disk('public')->url($partner->profile_photo_path),
            'rank' => $ranks[$partner->id] ?? 'Member', 'personal_sales' => (int) ($sales[$partner->id] ?? 0), 'team_sales' => $teams[$partner->id] ?? 0,
            ...($user->can('wallets.view') ? ['commissions' => (int) ($commissions[$partner->id] ?? 0)] : []),
        ])->filter(fn (array $row): bool => $row['personal_sales'] > 0 || $row['team_sales'] > 0)
            ->sort(fn (array $a, array $b): int => ($b['team_sales'] <=> $a['team_sales']) ?: (($b['personal_sales'] <=> $a['personal_sales']) ?: ($a['id'] <=> $b['id'])))->take(5)->values()->all());
    }

    public function detailQuery(User $user, string $tab, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        Gate::forUser($user)->authorize('reports.view');
        if (in_array($tab, ['commissions', 'payouts'], true)) {
            Gate::forUser($user)->authorize('wallets.view');
        }
        if ($tab === 'activity') {
            Gate::forUser($user)->authorize('audit.view');
        }

        return match ($tab) {
            'orders' => DB::table('orders')->where('placed_at', '>=', $from)->where('placed_at', '<', $until)->select('id', 'order_number', 'status', 'grand_total', 'placed_at')->orderByDesc('id'),
            'commissions' => DB::table('commission_entries')->join('partners', 'partners.id', '=', 'commission_entries.beneficiary_partner_id')->join('users', 'users.id', '=', 'partners.user_id')
                ->where('commission_entries.created_at', '>=', $from)->where('commission_entries.created_at', '<', $until)
                ->select('commission_entries.id', 'users.name', 'partners.referral_code', 'commission_type', 'level', 'amount', 'reversed_amount', 'commission_entries.status', 'commission_entries.created_at')->orderByDesc('commission_entries.id'),
            'network', 'members' => DB::table('partners')->join('users', 'users.id', '=', 'partners.user_id')->where('partners.created_at', '>=', $from)->where('partners.created_at', '<', $until)
                ->select('partners.id', 'users.name', 'partners.referral_code', 'partners.sponsor_partner_id', 'partners.status', 'partners.created_at')->orderByDesc('partners.id'),
            'products' => $this->products($from, $until),
            'payouts' => DB::table('payout_requests')->where('requested_at', '>=', $from)->where('requested_at', '<', $until)->select('id', 'partner_id', 'amount', 'status', 'requested_at')->orderByDesc('id'),
            'activity' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')->where('audit_logs.created_at', '>=', $from)->where('audit_logs.created_at', '<', $until)
                ->select('audit_logs.id', 'users.name', 'action', 'subject_type', 'subject_id', 'audit_logs.created_at')->orderByDesc('audit_logs.id'),
            default => abort(404),
        };
    }

    public function csv(User $user, string $type, string $from, string $until): StreamedResponse
    {
        Gate::forUser($user)->authorize('reports.view');
        $range = $this->reports->range($from, $until);
        if ($type === 'summary') {
            $stats = $this->totals($user, $range['from'], $range['until']);

            return response()->streamDownload(function () use ($stats, $from, $until): void {
                $stream = fopen('php://output', 'w');
                if ($stream === false) {
                    return;
                }
                fputcsv($stream, ['metric', 'value', 'from', 'until'], ',', '"', '');
                foreach ($stats as $metric => $value) {
                    fputcsv($stream, [$metric, $value, $from, $until], ',', '"', '');
                }
                fclose($stream);
            }, 'honeycommerce-summary.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $query = $this->detailQuery($user, $type, $range['from'], $range['until']);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            $header = false;
            foreach ($query->cursor() as $row) {
                if (! $header) {
                    fputcsv($stream, array_keys((array) $row), ',', '"', '');
                    $header = true;
                }
                fputcsv($stream, array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+@\t\r-]/', $value) && ! is_numeric($value) ? "'".$value : $value, (array) $row), ',', '"', '');
            }
            if (! $header) {
                fputcsv($stream, ['Tidak ada data pada periode ini'], ',', '"', '');
            }
            fclose($stream);
        }, 'honeycommerce-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
