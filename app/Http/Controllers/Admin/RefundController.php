<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Actions\ProcessRefund;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('refunds.manage');

        return Inertia::render('admin/refunds', ['listFilters' => $lists->values($request), 'refunds' => $lists->apply($request, Refund::query(), ['id', 'order_id', 'reason'], [], ['requested', 'processed', 'rejected'])->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (Refund $refund): array => [
            'id' => $refund->id, 'order_id' => $refund->order_id, 'order_item_id' => $refund->order_item_id, 'amount' => $refund->amount, 'reason' => $refund->reason, 'status' => $refund->status])]);
    }

    public function store(RefundRequest $request, Order $order, ProcessRefund $refunds): RedirectResponse
    {
        $refunds->handle($request->user(), $order, $request->filled('order_item_id') ? $request->integer('order_item_id') : null, $request->integer('amount'), $request->string('reason')->toString(), $request->string('idempotency_key')->toString(), $request->boolean('confirmed_refund'));

        return to_route('orders.show', $order);
    }

    public function decide(Request $request, Refund $refund, ProcessRefund $refunds, AuditWriter $audit): RedirectResponse
    {
        Gate::authorize('refunds.manage');
        $request->validate(['operation' => ['required', 'in:process,reject'], 'confirmed_refund' => ['exclude_unless:operation,process', 'required', 'accepted']]);
        if ($request->input('operation') === 'process') {
            $refunds->handle($request->user(), Order::findOrFail($refund->order_id), $refund->order_item_id, $refund->amount, $refund->reason, $refund->idempotency_key, $request->boolean('confirmed_refund'));
        } else {
            DB::transaction(function () use ($request, $refund, $audit): void {
                Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
                $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'rejected') {
                    return;
                }
                abort_unless($locked->status === 'requested', 422);
                $locked->update(['status' => 'rejected', 'processed_by' => $request->user()->id, 'processed_at' => now()]);
                $audit->record($request->user()->id, 'refund_rejected', 'refunds', $locked->id, 'refund:'.$locked->id.':rejected');
            });
        }

        return to_route('admin.refunds.index');
    }

    public function hold(Request $request, Order $order, AuditWriter $audit): RedirectResponse
    {
        Gate::authorize('refunds.manage');
        $request->validate(['hold' => ['required', 'boolean'], 'idempotency_key' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $order, $audit): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->partner_referral_id !== null) {
                Partner::whereKey($locked->partner_referral_id)->lockForUpdate()->firstOrFail();
            }
            $key = 'order-hold:'.$request->string('idempotency_key');
            $existing = DB::table('audit_logs')->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                abort_unless((int) $existing->subject_id === $locked->id && (int) $existing->actor_id === $request->user()->id && json_decode($existing->after_json, true, flags: JSON_THROW_ON_ERROR)['hold'] === $request->boolean('hold'), 422);

                return;
            }
            $locked->update(['fraud_hold' => $request->boolean('hold')]);
            $audit->record($request->user()->id, 'order_fraud_hold', 'orders', $locked->id, $key, ['hold' => $locked->fraud_hold]);
            if (! $locked->fraud_hold) {
                DB::table('outbox_messages')->insert(['event_key' => $key, 'event_type' => 'orders.state_changed', 'aggregate_type' => 'orders', 'aggregate_id' => $locked->id, 'payload' => json_encode(['to' => $locked->status], JSON_THROW_ON_ERROR), 'available_at' => now(), 'created_at' => now()]);
            }
        });

        return to_route('orders.show', $order);
    }
}
