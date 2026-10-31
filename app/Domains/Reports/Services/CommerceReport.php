<?php

namespace App\Domains\Reports\Services;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommerceReport
{
    /** @return array{from: CarbonImmutable, until: CarbonImmutable} */
    public function range(string $from, string $until): array
    {
        return ['from' => CarbonImmutable::parse($from, 'Asia/Jakarta')->startOfDay()->utc(), 'until' => CarbonImmutable::parse($until, 'Asia/Jakarta')->addDay()->startOfDay()->utc()];
    }

    /** @return array<string, mixed> */
    public function summary(User $user, string $from, string $until): array
    {
        Gate::forUser($user)->authorize('reports.view');
        $range = $this->range($from, $until);
        $orders = Order::where('placed_at', '>=', $range['from'])->where('placed_at', '<', $range['until']);
        $paid = (clone $orders)->whereHas('payments', fn ($query) => $query->whereIn('status', ['verified', 'refunded']));
        $ids = (clone $paid)->select('id');
        $refunds = (int) Refund::whereIn('order_id', $ids)->where('status', 'processed')->sum('amount');
        $gross = (int) (clone $paid)->sum('grand_total');
        $result = ['orders' => (clone $orders)->count(), 'paidOrders' => (clone $paid)->count(), 'pendingOrders' => (clone $orders)->where('status', 'pending_payment')->count(),
            'gross' => $gross, 'refunds' => $refunds, 'net' => $gross - $refunds, 'activePartners' => Partner::where('status', 'active')->count(),
            'partnerSales' => (clone $paid)->whereNotNull('partner_referral_id')->selectRaw('partner_referral_id, COUNT(*) as orders, SUM(grand_total) as total')->groupBy('partner_referral_id')->get()->toArray()];
        if ($user->can('wallets.view')) {
            $entries = CommissionEntry::whereHas('item', fn ($query) => $query->whereIn('order_id', $ids));
            $commissions = (int) (clone $entries)->selectRaw('COALESCE(SUM(amount - reversed_amount), 0) as total')->value('total');
            $cost = (int) DB::table('order_items')->whereIn('order_id', $ids)->selectRaw('COALESCE(SUM(cost_snapshot * quantity), 0) as total')->value('total');
            $result['finance'] = ['commissions' => $commissions, 'estimatedMargin' => $gross - $refunds - $cost - $commissions,
                'payoutReserved' => (int) PayoutRequest::whereIn('status', ['requested', 'approved'])->sum('amount'), 'payoutPaid' => (int) PayoutRequest::where('status', 'paid')->sum('amount')];
        }

        return $result;
    }

    public function csv(User $user, string $type, string $from, string $until, ?int $partnerId = null): StreamedResponse
    {
        if ($partnerId === null) {
            Gate::forUser($user)->authorize('reports.view');
            abort_unless($type === 'orders' || $user->can('wallets.view'), 403);
        } else {
            $partner = Partner::findOrFail($partnerId);
            abort_unless($partner->user_id === $user->id && $user->isActive() && $user->can('commissions.view-own') && $type === 'commissions', 403);
        }
        $range = $this->range($from, $until);
        [$table, $columns] = match ($type) {
            'orders' => ['orders', ['order_number', 'status', 'grand_total', 'placed_at']],
            'commissions' => ['commission_entries', ['id', 'beneficiary_partner_id', 'order_item_id', 'amount', 'reversed_amount', 'status', 'created_at']],
            'wallet' => ['wallet_ledger_entries', ['id', 'partner_id', 'bucket', 'entry_type', 'amount_signed', 'created_at']],
            'payouts' => ['payout_requests', ['id', 'partner_id', 'amount', 'status', 'requested_at']],
            default => abort(404),
        };
        $dateColumn = $type === 'orders' ? 'placed_at' : ($type === 'payouts' ? 'requested_at' : 'created_at');
        $query = DB::table($table)->select($columns)->where($dateColumn, '>=', $range['from'])->where($dateColumn, '<', $range['until'])->orderBy('id');
        if ($partnerId !== null) {
            $query->where('beneficiary_partner_id', $partnerId);
        }

        return response()->streamDownload(function () use ($query, $columns): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fputcsv($stream, $columns, ',', '"', '');
            foreach ($query->cursor() as $record) {
                $row = [];
                foreach ($columns as $column) {
                    $value = $record->{$column};
                    $row[] = is_string($value) && preg_match('/^[=+@\t\r-]/', $value) && ! is_numeric($value) ? "'".$value : $value;
                }
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, 'honeycommerce-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
