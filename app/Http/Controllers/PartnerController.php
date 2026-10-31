<?php

namespace App\Http\Controllers;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Actions\ManagePartner;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\NetworkSummary;
use App\Domains\Partners\Services\ReferralCapture;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Http\Requests\Partner\PartnerApplicationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function index(Request $request, PartnerLedger $ledger, NetworkSummary $networks): Response
    {
        $partner = Partner::where('user_id', $request->user()->id)->first();
        if ($partner !== null) {
            Gate::authorize('view', $partner);
        }

        return Inertia::render('partner/dashboard', [
            'partner' => $partner === null ? null : [
                'id' => $partner->id,
                'status' => $partner->status,
                'referral_code' => $partner->referral_code,
                'referral_url' => route('referral.visit', $partner->referral_code),
            ],
            'balances' => $partner === null ? null : $ledger->balances($partner->id),
            'commissions' => $partner === null ? [] : $this->commissions($partner),
            'attributedOrders' => $partner === null ? 0 : Order::where('partner_referral_id', $partner->id)->count(),
            'networkSummary' => $partner === null ? null : $networks->forRoot($partner->id),
            'network' => $partner === null ? [] : $this->network($partner),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function commissions(Partner $partner): array
    {
        return CommissionEntry::with('item:id,name_snapshot')
            ->where('beneficiary_partner_id', $partner->id)->orderByDesc('id')->limit(50)
            ->get(['id', 'order_item_id', 'amount', 'reversed_amount', 'offset_amount', 'status', 'eligible_at'])
            ->map(fn (CommissionEntry $entry): array => [
                'id' => $entry->id,
                'product' => $entry->item->name_snapshot,
                'amount' => $entry->amount,
                'reversed_amount' => $entry->reversed_amount,
                'offset_amount' => $entry->offset_amount,
                'status' => $entry->status,
                'eligible_at' => $entry->eligible_at?->toIso8601String(),
            ])->all();
    }

    /** @return array<int, array{name: string, status: string}> */
    private function network(Partner $partner): array
    {
        return Partner::with('user:id,name')->where('sponsor_partner_id', $partner->id)
            ->orderBy('id')->limit(100)->get(['id', 'user_id', 'status'])
            ->map(fn (Partner $child): array => ['name' => $child->user->name, 'status' => $child->status])->all();
    }

    public function store(PartnerApplicationRequest $request, ManagePartner $partners): RedirectResponse
    {
        $partners->apply($request->user(), $request->string('sponsor_code')->toString() ?: null);

        return to_route('partner.dashboard');
    }

    public function referral(Request $request, string $code, ReferralCapture $referrals): RedirectResponse
    {
        $existing = $request->cookie('honey_referral') ?: $request->session()->get('honey_referral');
        $token = $referrals->capture($code, is_string($existing) ? $existing : null, $request->user()?->id);
        if ($token === null) {
            return to_route('catalog.index');
        }
        $request->session()->put('honey_referral', $token);

        return to_route('catalog.index')->withCookie(cookie('honey_referral', $token, config('partners.referral_days') * 1440, '/', null, $request->isSecure(), true, false, 'lax'));
    }
}
