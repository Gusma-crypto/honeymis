<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\NetworkSummary;
use App\Domains\Reports\Services\DashboardSummary;
use App\Domains\Wallet\Services\PartnerLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummary $summary, PartnerLedger $ledger, NetworkSummary $networks): Response
    {
        $user = $request->user();
        if ($summary->isAdministrator($user)) {
            return Inertia::render('dashboard', ['networkSummary' => $user->can('partners.manage') ? $networks->forRoot() : null, 'admin' => $summary->forUser($user), 'stats' => [], 'wallet' => null]);
        }

        $counts = Order::whereHas('customer', fn ($query) => $query->where('user_id', $user->id))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $partner = Partner::where('user_id', $user->id)->first(['id']);

        return Inertia::render('dashboard', [
            'admin' => null,
            'networkSummary' => $partner === null ? null : $networks->forRoot($partner->id),
            'stats' => ['Pesanan saya' => (int) $counts->sum(), 'Menunggu pembayaran' => (int) $counts->get('pending_payment', 0)],
            'wallet' => $partner === null ? null : $ledger->balances($partner->id),
        ]);
    }
}
