<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Partners\Actions\ManagePartner;
use App\Domains\Partners\Models\Partner;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PartnerManagementController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('partners.manage');

        return Inertia::render('admin/partners', ['listFilters' => $lists->values($request), 'partners' => $lists->apply($request, Partner::with('user'), ['referral_code'], ['user' => ['name', 'email']], ['pending', 'active', 'suspended', 'rejected'])->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (Partner $partner): array => [
            'id' => $partner->id, 'name' => $partner->user->name, 'email' => $partner->user->email, 'status' => $partner->status,
            'referral_code' => $partner->referral_code, 'sponsor_partner_id' => $partner->sponsor_partner_id])]);
    }

    public function update(Request $request, Partner $partner, ManagePartner $partners): RedirectResponse
    {
        Gate::authorize('partners.manage');
        $data = $request->validate(['status' => ['required', 'in:active,suspended,rejected'], 'idempotency_key' => ['required', 'uuid']]);
        $partners->decide($request->user(), $partner, $data['status'], $data['idempotency_key']);

        return to_route('admin.partners.index');
    }
}
