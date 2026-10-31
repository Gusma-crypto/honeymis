<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Actions\ManageOrder;
use App\Domains\Orders\Actions\ProcessRefund;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Requests\Commerce\OrderTransitionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        return Inertia::render('commerce/orders', ['listFilters' => $lists->values($request), 'orders' => $lists->apply($request, Order::whereHas('customer', fn ($query) => $query->where('user_id', $request->user()->id)), ['order_number'], [], ['pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'])
            ->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Order $order): array => ['id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status, 'grand_total' => $order->grand_total])]);
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);
        $order->load('items', 'payments', 'shipments');

        return Inertia::render('commerce/order', ['order' => [
            'id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status, 'subtotal' => $order->subtotal,
            'fraud_hold' => $order->fraud_hold, 'refunds' => Refund::where('order_id', $order->id)->get(['id', 'order_item_id', 'amount', 'status', 'reason']),
            'discount_total' => $order->discount_total, 'shipping_total' => $order->shipping_total, 'grand_total' => $order->grand_total,
            'address' => $order->shipping_address_snapshot, 'shipping' => $order->getAttribute('shipping_snapshot'), 'expires_at' => $order->expires_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name_snapshot, 'sku' => $item->sku_snapshot, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price_snapshot, 'discount' => $item->discount_snapshot])->all(),
            'payments' => $order->payments->map(fn ($payment): array => ['id' => $payment->id, 'status' => $payment->status,
                'proof_url' => $payment->proof_storage_key !== null && Gate::allows('viewProof', $payment) ? route('payments.proof', $payment) : null])->all(),
            'shipments' => $order->shipments->map(fn ($shipment): array => ['courier' => $shipment->courier, 'tracking_number' => $shipment->tracking_number, 'status' => $shipment->status])->all(),
        ], 'can' => ['refund' => $order->customer->user_id === $request->user()->id && in_array($order->status, ['paid', 'processing', 'shipped', 'delivered', 'completed'], true), 'financeRefund' => Gate::allows('refunds.manage'), 'cancel' => Gate::allows('cancel', $order), 'upload' => Gate::allows('submitProof', $order), 'verify' => Gate::allows('verifyPayment', $order), 'manage' => Gate::allows('manage', $order)],
            'paymentInstructions' => config('commerce.manual_payment_instructions')]);
    }

    public function update(OrderTransitionRequest $request, Order $order, ManageOrder $orders): RedirectResponse
    {
        $orders->handle($request->user(), $order, $request->string('operation')->toString(), $request->string('idempotency_key')->toString(), $request->safe()->only(['courier', 'tracking_number']));

        return to_route('orders.show', $order);
    }

    public function refund(Request $request, Order $order, ProcessRefund $refunds): RedirectResponse
    {
        Gate::authorize('view', $order);
        $request->validate(['order_item_id' => ['nullable', 'integer'], 'amount' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $refunds->request($request->user(), $order, $request->filled('order_item_id') ? $request->integer('order_item_id') : null, $request->integer('amount'), $request->string('reason')->toString(), $request->string('idempotency_key')->toString());

        return to_route('orders.show', $order);
    }
}
