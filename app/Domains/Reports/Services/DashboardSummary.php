<?php

namespace App\Domains\Reports\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardSummary
{
    public function __construct(private AdminDashboardDetails $details) {}

    private const CACHE_SECONDS = 15;

    private const ADMIN_PERMISSIONS = [
        'orders.manage', 'payments.verify', 'catalog.manage', 'partners.manage',
        'payouts.review', 'reports.view', 'users.manage-access', 'audit.view',
        'inventory.manage', 'refunds.manage', 'commission-rules.manage',
    ];

    public function isAdministrator(User $user): bool
    {
        return $user->canAny(self::ADMIN_PERMISSIONS);
    }

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $summary = [];
        if ($user->canAny(['orders.manage', 'payments.verify'])) {
            $summary['orders'] = Cache::remember('dashboard.orders.v1', self::CACHE_SECONDS, $this->orders(...));
        }
        if ($user->can('catalog.manage')) {
            $summary['activeProducts'] = Cache::remember('dashboard.products.v1', self::CACHE_SECONDS, fn (): int => Product::where('status', 'active')->count());
        }
        if ($user->can('inventory.manage')) {
            $summary['lowStock'] = Cache::remember('dashboard.stock.v1', self::CACHE_SECONDS, $this->lowStock(...));
        }
        if ($user->can('partners.manage')) {
            $summary['pendingPartners'] = Cache::remember('dashboard.partners.v1', self::CACHE_SECONDS, fn (): int => Partner::where('status', 'pending')->count());
        }
        if ($user->can('payouts.review')) {
            $summary['payouts'] = Cache::remember('dashboard.payouts.v1', self::CACHE_SECONDS, $this->payouts(...));
        }
        if ($user->can('refunds.manage')) {
            $summary['refundRequests'] = Cache::remember('dashboard.refunds.v1', self::CACHE_SECONDS, fn (): int => Refund::where('status', 'requested')->count());
        }
        if ($user->can('reports.view') && $user->can('wallets.view')) {
            $month = now()->setTimezone('Asia/Jakarta')->startOfMonth()->utc();
            $summary['finance'] = Cache::remember('dashboard.finance.v1.'.$month->format('Y-m-d'), self::CACHE_SECONDS, $this->finance(...));
        }

        return [...$summary, ...$this->details->forUser($user)];
    }

    /** @return array<string, mixed> */
    private function orders(): array
    {
        $counts = Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)->all();
        $recent = Order::with(['customer:id,name', 'items:id,order_id,name_snapshot'])->orderByDesc('id')->limit(6)->get(['id', 'customer_id', 'order_number', 'status', 'grand_total', 'placed_at'])
            ->map(fn (Order $order): array => [
                'id' => $order->id, 'number' => $order->order_number, 'status' => $order->status,
                'total' => $order->grand_total, 'placedAt' => $order->placed_at->toIso8601String(),
                'customer' => $order->customer->name, 'product' => $order->items->first()->name_snapshot ?? '—',
            ])->all();

        return ['counts' => $counts, 'reviewPayments' => Payment::where('status', 'submitted')->count(), 'recent' => $recent];
    }

    /** @return list<array{id: int, name: string, sku: string, available: int}> */
    private function lowStock(): array
    {
        $balances = InventoryBalance::with('variant:id,product_id,sku', 'variant.product:id,name')
            ->whereRaw('on_hand - reserved <= ?', [5])
            ->whereHas('variant', fn ($query) => $query->where('status', 'active')->whereHas('product', fn ($query) => $query->where('status', 'active')))
            ->orderByRaw('on_hand - reserved')->orderBy('id')->limit(5)->get()
            ->map(fn (InventoryBalance $balance): array => [
                'id' => $balance->id, 'name' => $balance->variant->product->name,
                'sku' => $balance->variant->sku, 'available' => $balance->on_hand - $balance->reserved,
            ])->all();

        return array_values($balances);
    }

    /** @return array{requested: int, approved: int, reserved: int} */
    private function payouts(): array
    {
        $totals = DB::table('payout_requests')->whereIn('status', ['requested', 'approved'])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END), 0) AS requested")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved")
            ->selectRaw('COALESCE(SUM(amount), 0) AS reserved')->first();

        return ['requested' => (int) $totals->requested, 'approved' => (int) $totals->approved, 'reserved' => (int) $totals->reserved];
    }

    /** @return array{verified: int, refunded: int} */
    private function finance(): array
    {
        $month = now()->setTimezone('Asia/Jakarta')->startOfMonth()->utc();
        $nextMonth = $month->setTimezone('Asia/Jakarta')->addMonth()->utc();

        return [
            'verified' => (int) Payment::whereIn('status', ['verified', 'refunded'])->where('verified_at', '>=', $month)->where('verified_at', '<', $nextMonth)->sum('amount'),
            'refunded' => (int) Refund::where('status', 'processed')->where('processed_at', '>=', $month)->where('processed_at', '<', $nextMonth)->sum('amount'),
        ];
    }
}
