<?php

namespace App\Domains\Wallet\Services;

use Illuminate\Support\Facades\DB;

class PayoutAvailabilitySummary
{
    public function total(): int
    {
        $allocated = DB::table('payout_allocations')->join('payout_requests', 'payout_requests.id', '=', 'payout_allocations.payout_id')
            ->whereIn('payout_requests.status', ['requested', 'approved', 'paid'])->select('commission_entry_id')->selectRaw('SUM(payout_allocations.amount) as allocated')->groupBy('commission_entry_id');
        $remaining = 'entries.amount - entries.reversed_amount - entries.offset_amount - COALESCE(allocations.allocated, 0)';
        $eligible = DB::table('commission_entries as entries')->join('order_items', 'order_items.id', '=', 'entries.order_item_id')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('partners', 'partners.id', '=', 'entries.beneficiary_partner_id')->join('users', 'users.id', '=', 'partners.user_id')
            ->leftJoinSub($allocated, 'allocations', 'allocations.commission_entry_id', '=', 'entries.id')->whereNotNull('entries.available_at')
            ->where('orders.fraud_hold', false)->whereIn('orders.status', ['delivered', 'completed'])->where('partners.status', 'active')->where('users.status', 'active')
            ->select('beneficiary_partner_id')->selectRaw('SUM(CASE WHEN '.$remaining.' > 0 THEN '.$remaining.' ELSE 0 END) as total')->groupBy('beneficiary_partner_id')->pluck('total', 'beneficiary_partner_id');
        $wallets = DB::table('wallet_ledger_entries')->whereIn('bucket', ['available', 'receivable'])->select('partner_id')
            ->selectRaw("SUM(CASE WHEN bucket = 'available' THEN amount_signed ELSE 0 END) as available, SUM(CASE WHEN bucket = 'receivable' THEN amount_signed ELSE 0 END) as debt")->groupBy('partner_id')->get();
        $total = 0;
        foreach ($wallets as $wallet) {
            if ((int) $wallet->debt <= 0) {
                $total += max(0, min((int) $wallet->available, (int) ($eligible[$wallet->partner_id] ?? 0)));
            }
        }

        return $total;
    }
}
