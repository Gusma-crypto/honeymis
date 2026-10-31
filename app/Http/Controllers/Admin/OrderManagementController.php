<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Models\Order;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderManagementController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        abort_unless(auth()->user()?->can('orders.manage') || auth()->user()?->can('payments.verify'), 403);

        return Inertia::render('commerce/orders', ['listFilters' => $lists->values($request), 'orders' => $lists->apply($request, Order::query(), ['order_number'], ['customer' => ['name', 'email']], ['pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'])->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Order $order): array => ['id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status, 'grand_total' => $order->grand_total])]);
    }
}
