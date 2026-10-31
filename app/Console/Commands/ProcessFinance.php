<?php

namespace App\Console\Commands;

use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Models\Partner;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\CommerceNotifier;
use App\Domains\Shared\Services\FinanceOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessFinance extends Command
{
    protected $signature = 'honey:process-finance {--limit=100}';

    protected $description = 'Process committed order events, catch up direct commissions and release eligible earnings';

    public function handle(FinanceOutbox $outbox, CommissionEngine $commissions, AuditWriter $audit): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $count = $outbox->process($limit);
        $activePartners = Partner::where('status', 'active')->whereHas('user', fn ($query) => $query->where('status', 'active'))->select('id');
        foreach (Order::whereIn('partner_referral_id', $activePartners)->whereNotNull('commission_rule_id')->whereNull('commission_checked_at')->where('fraud_hold', false)->whereIn('status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])->orderBy('id')->limit($limit)->get() as $order) {
            $commissions->accrue($order->id);
        }
        $pendingEntries = DB::table('commission_entries')->join('order_items', 'order_items.id', '=', 'commission_entries.order_item_id')->select('order_items.order_id')
            ->where('commission_entries.status', 'pending')->where(fn ($query) => $query->whereNull('eligible_at')->orWhere('eligible_at', '<=', now()));
        foreach (Order::whereIn('id', $pendingEntries)->whereIn('status', ['delivered', 'completed'])->where('fraud_hold', false)->orderBy('id')->limit($limit)->get() as $order) {
            $commissions->release($order->id);
        }
        $completed = 0;
        foreach (Order::where('status', 'delivered')->where('fraud_hold', false)->orderBy('id')->cursor() as $order) {
            if ($completed >= $limit) {
                break;
            }
            if ($order->delivered_at === null || $order->delivered_at->addDays($order->return_window_days)->isFuture()) {
                continue;
            }
            DB::transaction(function () use ($order, $audit, &$completed): void {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'delivered' || $locked->fraud_hold || $locked->delivered_at === null || $locked->delivered_at->addDays($locked->return_window_days)->isFuture()) {
                    return;
                }
                $locked->update(['status' => 'completed']);
                $audit->record(null, 'order_completed', 'orders', $locked->id, 'order:'.$locked->id.':completed');
                if ($locked->customer->user_id !== null) {
                    app(CommerceNotifier::class)->queue($locked->customer->user_id, 'order:'.$locked->id.':completed', 'Pesanan selesai', $locked->order_number.' telah selesai.', route('orders.show', $locked->id, false));
                }
                $completed++;
            });
        }
        $count += $outbox->process($limit);
        $this->info($count.' committed events processed; eligible commissions and completed orders checked.');

        return self::SUCCESS;
    }
}
