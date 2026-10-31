<?php

namespace App\Console\Commands;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Domains\Wallet\Services\PartnerLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileFinance extends Command
{
    protected $signature = 'honey:reconcile-finance';

    protected $description = 'Read-only reconciliation of commission liabilities, wallet buckets, payout reserves and order snapshots';

    public function handle(PartnerLedger $ledger): int
    {
        $errors = 0;
        foreach (Partner::orderBy('id')->cursor() as $partner) {
            $errors += DB::transaction(function () use ($partner, $ledger): int {
                Partner::whereKey($partner->id)->sharedLock()->firstOrFail();
                $balance = $ledger->balances($partner->id);
                $earned = (int) CommissionEntry::where('beneficiary_partner_id', $partner->id)->selectRaw('COALESCE(SUM(amount - reversed_amount), 0) as total')->value('total');
                $actual = $balance['pending'] + $balance['available'] + $balance['reserved'] + $balance['paid'] - $balance['receivable'];
                $reserved = (int) PayoutRequest::where('partner_id', $partner->id)->whereIn('status', ['requested', 'approved'])->sum('amount');
                $paid = (int) PayoutRequest::where('partner_id', $partner->id)->where('status', 'paid')->sum('amount');
                if ($earned !== $actual || $reserved !== $balance['reserved'] || $paid !== $balance['paid'] || min($balance) < 0) {
                    $this->error('Partner '.$partner->id.' financial totals do not reconcile.');

                    return 1;
                }

                return 0;
            });
        }
        foreach (PayoutRequest::orderBy('id')->cursor() as $payout) {
            $errors += DB::transaction(function () use ($payout): int {
                Partner::whereKey($payout->partner_id)->sharedLock()->firstOrFail();
                $allocated = (int) DB::table('payout_allocations')->where('payout_id', $payout->id)->sum('amount');
                if ($allocated !== $payout->amount) {
                    $this->error('Payout '.$payout->id.' allocations do not reconcile.');

                    return 1;
                }

                return 0;
            });
        }
        foreach (Order::with('items')->orderBy('id')->cursor() as $order) {
            if ($order->items->isEmpty()) {
                continue;
            }
            $subtotal = $order->items->sum(fn ($item): int => $item->unit_price_snapshot * $item->quantity);
            if ($subtotal !== $order->subtotal || $order->items->sum('discount_snapshot') !== $order->discount_total) {
                $this->error('Order '.$order->id.' item snapshots do not reconcile.');
                $errors++;
            }
        }
        $this->info($errors.' reconciliation discrepancies.');

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
