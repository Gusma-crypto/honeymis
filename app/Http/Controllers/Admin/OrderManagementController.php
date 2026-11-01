<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Actions\ImportTrackingNumbers;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\AdminOrderQuery;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderManagementController extends Controller
{
    public function index(Request $request, ListFilters $lists, AdminOrderQuery $query): Response
    {
        abort_unless(auth()->user()?->can('orders.manage') || auth()->user()?->can('payments.verify'), 403);

        $orders = $query->filtered($request)->with(['customer', 'payments', 'shipments'])->withCount('items')->paginate($request->integer('per_page', 8))->withQueryString();
        $users = User::whereIn('id', $orders->getCollection()->pluck('customer.user_id')->filter())->get(['id', 'gender', 'profile_photo_path'])->keyBy('id');
        $members = DB::table('partners')->leftJoin('partner_monthly_ranks as ranks', function ($join): void {
            $join->on('ranks.partner_id', '=', 'partners.id')->where('ranks.period_start', now('Asia/Jakarta')->startOfMonth()->toDateString());
        })->whereIn('user_id', $users->keys())->get(['user_id', 'partners.status', 'ranks.rank'])->keyBy('user_id');
        $items = DB::table('order_items')->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')->whereIn('order_id', $orders->getCollection()->pluck('id'))
            ->select('order_items.id', 'order_id', 'name_snapshot', 'quantity')->addSelect(['image_id' => DB::table('product_images')->select('id')->whereColumn('product_images.product_id', 'product_variants.product_id')->orderBy('sort_order')->orderByDesc('id')->limit(1)])
            ->orderBy('order_items.id')->get()->groupBy('order_id');
        $counts = Order::select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/orders', [
            'listFilters' => $lists->values($request),
            'filters' => [...$lists->values($request), 'period' => $request->input('period', ''), 'payment' => $request->input('payment', ''), 'shipping' => $request->input('shipping', ''), 'per_page' => $request->integer('per_page', 8)],
            'counts' => $counts, 'total' => (int) $counts->sum(),
            'orders' => $orders->through(function (Order $order) use ($users, $members, $items): array {
                $user = $users->get($order->customer->user_id);
                $member = $members->get($order->customer->user_id);
                $payment = $order->payments->sortByDesc('id')->first();
                $shipment = $order->shipments->sortByDesc('id')->first();

                return ['id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status, 'grand_total' => $order->grand_total, 'placed_at' => $order->placed_at->toIso8601String(),
                    'customer' => ['name' => $order->customer->name, 'gender' => $user?->gender, 'avatar' => $user?->avatar, 'rank' => $member !== null && $member->status === 'active' ? ($member->rank ?? 'Member') : null],
                    'payment_status' => $payment->status ?? 'pending', 'shipment_status' => $shipment?->status,
                    'courier' => $shipment?->courier, 'tracking_number' => $shipment?->tracking_number,
                    'item_count' => (int) $order->getAttribute('items_count'), 'fraud_hold' => $order->fraud_hold,
                    'items' => ($items->get($order->id) ?? collect())->take(2)->map(static fn ($item): array => ['name' => $item->name_snapshot, 'quantity' => (int) $item->quantity, 'image_url' => $item->image_id === null ? null : route('catalog.image', $item->image_id)])->values()->all(),
                ];
            }),
        ]);
    }

    public function export(Request $request, AdminOrderQuery $query): StreamedResponse
    {
        abort_unless($request->user()->can('orders.manage') || $request->user()->can('payments.verify'), 403);
        $orders = $query->filtered($request)->with('customer');

        return response()->streamDownload(function () use ($orders, $request): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Export pesanan gagal.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            if ($request->boolean('template')) {
                fputcsv($stream, ['order_number', 'courier', 'tracking_number'], escape: '');
            } else {
                fputcsv($stream, ['Nomor Pesanan', 'Tanggal', 'Pelanggan', 'Total', 'Status'], escape: '');
                foreach ($orders->lazy(200) as $order) {
                    $row = [$order->order_number, $order->placed_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'), $order->customer->name, $order->grand_total, $order->status];
                    fputcsv($stream, array_map(static fn ($value) => is_string($value) && preg_match('/^\s*[=+@\-]/u', $value) ? "'".$value : $value, $row), escape: '');
                }
            }
            fclose($stream);
        }, $request->boolean('template') ? 'template-resi.csv' : 'pesanan.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importTracking(Request $request, ImportTrackingNumbers $tracking): RedirectResponse
    {
        abort_unless($request->user()->can('orders.manage'), 403);
        $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:csv,txt']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $count = $tracking->handle($request->user(), $file);

        return back()->with('success', $count.' resi berhasil diproses.');
    }
}
