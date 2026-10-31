<?php

namespace App\Http\Controllers;

use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Actions\ManagePayout;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Domains\Shared\Services\ListFilters;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Http\Requests\Partner\PayoutRequest as StorePayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutController extends Controller
{
    public function index(Request $request, PartnerLedger $ledger, ListFilters $lists): Response
    {
        $partner = Partner::where('user_id', $request->user()->id)->firstOrFail();
        Gate::authorize('view', $partner);

        return Inertia::render('partner/payouts', ['listFilters' => $lists->values($request), 'withdrawable' => $ledger->withdrawable($partner->id), 'minimum' => config('partners.minimum_payout_idr'), 'canRequest' => Gate::allows('requestPayout', $partner),
            'payouts' => $lists->apply($request, PayoutRequest::where('partner_id', $partner->id), ['id', 'bank_last4'], [], ['requested', 'approved', 'paid', 'rejected', 'failed'])->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (PayoutRequest $payout): array => [
                'id' => $payout->id, 'amount' => $payout->amount, 'status' => $payout->status, 'bank_last4' => $payout->bank_last4, 'reason' => $payout->rejection_reason,
                'proof_url' => $payout->proof_storage_key === null ? null : route('payouts.proof', $payout)])]);
    }

    public function store(StorePayoutRequest $request, ManagePayout $payouts): RedirectResponse
    {
        $partner = Partner::where('user_id', $request->user()->id)->firstOrFail();
        $payouts->request($request->user(), $partner, $request->integer('amount'), ['bank_name' => $request->string('bank_name')->toString(), 'account_name' => $request->string('account_name')->toString(), 'account_number' => $request->string('account_number')->toString()], $request->string('idempotency_key')->toString());

        return to_route('payouts.index');
    }

    public function proof(PayoutRequest $payout): StreamedResponse
    {
        Gate::authorize('view', $payout);
        abort_if($payout->proof_storage_key === null, 404);

        return Storage::disk('local')->download($payout->proof_storage_key, 'bukti-payout-'.$payout->id, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
