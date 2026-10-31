<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Commissions\Actions\ManageCommissionRule;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionRuleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommissionRuleController extends Controller
{
    public function index(Request $request, ManageCommissionRule $rules, ListFilters $lists): Response
    {
        Gate::authorize('commission-rules.manage');

        return Inertia::render('admin/commission-rules', ['listFilters' => $lists->values($request), 'rules' => $lists->apply($request, CommissionRule::query(), ['version'], [], ['draft', 'published', 'retired'])->orderByDesc('version')->paginate(20)->withQueryString()->through(fn (CommissionRule $rule): array => [
            'id' => $rule->id, 'version' => $rule->version, 'rate_bps' => $rule->rate_bps, 'fixed_amount' => $rule->fixed_amount, 'cap_amount' => $rule->cap_amount,
            'status' => $rule->status, 'product_id' => $rule->product_id, 'category_id' => $rule->category_id, 'valid_from' => $rule->valid_from->toIso8601String(),
            'preview' => $rule->status === 'draft' ? $rules->preview($rule) : null]), 'budgetBps' => config('partners.commission_budget_bps'),
            'nextVersion' => ((int) CommissionRule::max('version')) + 1, 'products' => Product::orderBy('name')->get(['id', 'name']), 'categories' => Category::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(CommissionRuleRequest $request, ManageCommissionRule $rules): RedirectResponse
    {
        $rules->create($request->user(), $request->validated());

        return to_route('admin.commission-rules.index');
    }

    public function publish(Request $request, CommissionRule $rule, ManageCommissionRule $rules): RedirectResponse
    {
        Gate::authorize('commission-rules.manage');
        $request->validate(['acknowledge_terms' => ['required', 'accepted']]);
        $rules->publish($request->user(), $rule);

        return to_route('admin.commission-rules.index');
    }

    public function preview(CommissionRule $rule, ManageCommissionRule $rules): JsonResponse
    {
        Gate::authorize('commission-rules.manage');

        return response()->json($rules->preview($rule));
    }

    public function retire(Request $request, CommissionRule $rule, AuditWriter $audit): RedirectResponse
    {
        Gate::authorize('commission-rules.manage');
        DB::transaction(function () use ($request, $rule, $audit): void {
            $locked = CommissionRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['published', 'retired'], true), 422);
            if ($locked->status === 'retired') {
                return;
            }
            $locked->update(['status' => 'retired']);
            $audit->record($request->user()->id, 'commission_rule_retired', 'commission_rules', $locked->id, 'rule:'.$locked->id.':retired');
        });

        return to_route('admin.commission-rules.index');
    }
}
