<?php

namespace App\Http\Controllers;

use App\Domains\Partners\Models\Partner;
use App\Domains\Shared\Services\ListFilters;
use App\Domains\Wallet\Models\WalletLedgerEntry;
use App\Domains\Wallet\Services\PartnerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request, PartnerLedger $ledger, ListFilters $lists): Response
    {
        $partner = Partner::where('user_id', $request->user()->id)->firstOrFail();
        Gate::authorize('view', $partner);

        return Inertia::render('partner/wallet', ['listFilters' => $lists->values($request), 'balances' => $ledger->balances($partner->id), 'withdrawable' => $ledger->withdrawable($partner->id),
            'entries' => $lists->apply($request, WalletLedgerEntry::where('partner_id', $partner->id), ['id', 'entry_type'], [], ['pending', 'available', 'reserved', 'paid', 'receivable'], 'bucket')->orderByDesc('id')->paginate(25)->withQueryString()->through(fn ($entry): array => [
                'id' => $entry->id, 'bucket' => $entry->bucket, 'entry_type' => $entry->entry_type, 'amount_signed' => $entry->amount_signed, 'created_at' => $entry->created_at->toIso8601String()])]);
    }
}
