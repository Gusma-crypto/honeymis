<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Partners\Actions\ManagePartner;
use App\Domains\Partners\Actions\ManageRankOverride;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\NetworkPerformance;
use App\Domains\Partners\Services\NetworkSummary;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerPlacementRequest;
use App\Http\Requests\Admin\UpdatePartnerRankRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PartnerManagementController extends Controller
{
    public function rank(UpdatePartnerRankRequest $request, Partner $partner, ManageRankOverride $overrides): RedirectResponse
    {
        $data = $request->validated();
        $overrides->handle($request->user(), $partner, $data['rank'] ?? null, $data['reason']);

        return to_route('admin.partners.index')->with('success', 'Override peringkat berhasil diperbarui.');
    }

    public function index(Request $request, ListFilters $lists, NetworkSummary $networks, NetworkPerformance $performance): Response
    {
        Gate::authorize('partners.manage');

        return Inertia::render('admin/partners', ['performance' => $performance->forUser($request->user()), 'networkSummary' => $networks->forRoot(), 'listFilters' => $lists->values($request), 'partners' => $lists->apply($request, Partner::with('user'), ['referral_code'], ['user' => ['name', 'email']], ['pending', 'active', 'suspended', 'rejected'])->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (Partner $partner): array => [
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

    public function store(StorePartnerPlacementRequest $request, ManagePartner $partners): RedirectResponse
    {
        $data = $request->validated();
        $member = User::where('email', $data['email'])->firstOrFail();
        $partners->addToNetwork($request->user(), $member, $data['placement'], isset($data['relative_partner_id']) ? (int) $data['relative_partner_id'] : null);
        Cache::forget('partners.network.graph.v1');
        Cache::forget('dashboard.members.v2');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mitra ditempatkan di jaringan. Tinjau dan aktifkan setelah persetujuan kemitraan.']);

        return to_route('admin.partners.index');
    }
}
