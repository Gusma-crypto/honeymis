<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Commissions\Services\MlmProgramSettings;
use App\Domains\Partners\Services\PartnerRankService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMlmProgramRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MlmProgramController extends Controller
{
    public function index(MlmProgramSettings $settings): Response
    {
        Gate::authorize('commission-rules.manage');
        $program = $settings->current();

        return Inertia::render('admin/mlm-settings', [
            'version' => $program->version,
            'referralFirstOrderBps' => $program->referral_first_order_bps,
            'generationRates' => $program->generation_rates,
            'rankRequirements' => $program->rank_requirements,
        ]);
    }

    public function update(UpdateMlmProgramRequest $request, MlmProgramSettings $settings, PartnerRankService $ranks): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $input = $request->validated();
        $rankRequirements = [];
        foreach (['Member', 'Bronze', 'Silver', 'Gold', 'Platinum'] as $rank) {
            $rankRequirements[$rank] = [
                'personal_sales' => (int) $input['rank_requirements'][$rank]['personal_sales'],
                'team_sales' => (int) $input['rank_requirements'][$rank]['team_sales'],
            ];
        }
        $settings->update($actor, [
            'referral_first_order_bps' => (int) $input['referral_first_order_bps'],
            'generation_rates' => array_values(array_map('intval', $input['generation_rates'])),
            'rank_requirements' => $rankRequirements,
        ]);
        $ranks->recalculateCurrentMonth();

        return to_route('admin.mlm-settings.index')->with('success', 'Konfigurasi MLM berhasil disimpan.');
    }
}
