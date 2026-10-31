<?php

namespace App\Domains\Shared\Services;

use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Models\User;
use App\Notifications\CommerceUpdate;
use Illuminate\Support\Facades\DB;

class FinanceOutbox
{
    public function __construct(private CommissionEngine $commissions) {}

    public function process(int $limit = 100): int
    {
        $count = 0;
        $failed = 0;
        $ids = DB::table('outbox_messages')->whereNull('processed_at')->where('available_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, &$count): void {
                    $message = DB::table('outbox_messages')->where('id', $id)->lockForUpdate()->first();
                    if ($message === null || $message->processed_at !== null) {
                        return;
                    }
                    if (! in_array($message->event_type, ['orders.state_changed', 'payments.state_changed', 'commission_entries.state_changed', 'payout_requests.state_changed', 'users.commerce_update'], true)) {
                        throw new \RuntimeException('Unsupported commerce outbox event.');
                    }
                    if ($message->event_type === 'orders.state_changed') {
                        $payload = json_decode($message->payload, true, flags: JSON_THROW_ON_ERROR);
                        $order = Order::where('id', $message->aggregate_id)->first();
                        if ($order !== null) {
                            $this->commissions->accrue($order->id);
                            $this->commissions->release($order->id);
                            $order->customer->user_id === null ? null : User::where('id', $order->customer->user_id)->first()?->notify((new CommerceUpdate('Status pesanan diperbarui', $order->order_number.': '.($payload['to'] ?? $order->status), route('orders.show', $order->id, false)))->beforeCommit());
                        }
                    }
                    if ($message->event_type === 'payments.state_changed') {
                        $payload = json_decode($message->payload, true, flags: JSON_THROW_ON_ERROR);
                        $payment = Payment::with('order.customer')->where('id', $message->aggregate_id)->first();
                        if ($payment !== null && $payment->order->customer->user_id !== null) {
                            User::where('id', $payment->order->customer->user_id)->first()?->notify((new CommerceUpdate('Status pembayaran', $payment->order->order_number.': '.($payload['to'] ?? $payment->status), route('orders.show', $payment->order_id, false)))->beforeCommit());
                        }
                    }
                    if ($message->event_type === 'users.commerce_update') {
                        $payload = json_decode($message->payload, true, flags: JSON_THROW_ON_ERROR);
                        User::where('id', $message->aggregate_id)->first()?->notify((new CommerceUpdate($payload['title'], $payload['detail'], $payload['path']))->beforeCommit());
                    }
                    DB::table('outbox_messages')->where('id', $id)->update(['processed_at' => now(), 'attempts' => $message->attempts + 1]);
                    $count++;
                }, attempts: 3);
            } catch (\Throwable $exception) {
                $message = DB::table('outbox_messages')->where('id', $id)->whereNull('processed_at')->first();
                if ($message !== null) {
                    DB::table('outbox_messages')->where('id', $id)->whereNull('processed_at')->update([
                        'attempts' => $message->attempts + 1,
                        'available_at' => now()->addSeconds(min(300, 5 * (2 ** min(6, $message->attempts)))),
                    ]);
                }
                report($exception);
                $failed++;
            }
        }
        if ($failed > 0) {
            throw new \RuntimeException($failed.' outbox events failed and were deferred for retry.');
        }

        return $count;
    }
}
