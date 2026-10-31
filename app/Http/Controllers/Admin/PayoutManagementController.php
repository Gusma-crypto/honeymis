<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Payouts\Actions\ManagePayout;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayoutDecisionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayoutManagementController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('payouts.review');

        return Inertia::render('admin/payouts', ['listFilters' => $lists->values($request), 'payouts' => $lists->apply($request, PayoutRequest::with('partner.user'), ['id', 'transfer_reference'], ['partner.user' => ['name', 'email']], ['requested', 'approved', 'paid', 'rejected', 'failed'])->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (PayoutRequest $payout): array => [
            'id' => $payout->id, 'partner_name' => $payout->partner->user->name, 'amount' => $payout->amount, 'status' => $payout->status,
            'bank' => $payout->bank_account_encrypted, 'transfer_reference' => $payout->transfer_reference, 'reason' => $payout->rejection_reason,
            'canReview' => Gate::allows('review', $payout), 'proof_url' => $payout->proof_storage_key === null ? null : route('payouts.proof', $payout)])]);
    }

    public function update(PayoutDecisionRequest $request, PayoutRequest $payout, ManagePayout $payouts): RedirectResponse
    {
        $proof = $request->file('proof');
        $payouts->decide($request->user(), $payout, $request->string('operation')->toString(), $request->string('idempotency_key')->toString(),
            $request->string('reason')->toString() ?: null, $request->string('transfer_reference')->toString() ?: null, $proof instanceof UploadedFile ? $proof : null, $request->boolean('confirmed_transfer'));

        return to_route('admin.payouts.index');
    }
}
