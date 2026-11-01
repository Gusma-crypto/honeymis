<?php

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Shared\Services\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderQuery
{
    public function __construct(private ListFilters $lists) {}

    /** @return Builder<Order> */
    public function filtered(Request $request): Builder
    {
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'], 'payment' => ['nullable', 'in:pending,submitted,verified,rejected,refunded'],
            'shipping' => ['nullable', 'in:unshipped,shipped,delivered'], 'per_page' => ['nullable', 'integer', 'in:8,16,24,48'],
        ]);
        $query = $this->lists->apply($request, Order::query(), ['order_number'], ['customer' => ['name', 'email'], 'items' => ['name_snapshot', 'sku_snapshot']], ['pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded']);
        if ($request->filled('period')) {
            $from = CarbonImmutable::createFromFormat('!Y-m', $request->string('period')->toString(), 'Asia/Jakarta')->startOfMonth();
            $query->where('placed_at', '>=', $from->utc())->where('placed_at', '<', $from->addMonth()->utc());
        }
        if ($request->filled('payment')) {
            $query->whereHas('payments', fn ($payments) => $payments->where('status', $request->string('payment')->toString())->where('id', '=', DB::table('payments as latest_payment')->selectRaw('MAX(id)')->whereColumn('latest_payment.order_id', 'payments.order_id')));
        }
        if ($request->input('shipping') === 'unshipped') {
            $query->whereDoesntHave('shipments');
        } elseif ($request->filled('shipping')) {
            $query->whereHas('shipments', fn ($shipments) => $shipments->where('status', $request->string('shipping')->toString()));
        }

        return $query->orderByDesc('id');
    }
}
