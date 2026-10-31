<?php

namespace App\Domains\Commissions\Actions;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Services\DirectCommissionCalculator;
use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\ValueObjects\Idr;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageCommissionRule
{
    public function __construct(private DirectCommissionCalculator $calculator, private AuditWriter $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): CommissionRule
    {
        Gate::forUser($actor)->authorize('commission-rules.manage');

        return DB::transaction(function () use ($actor, $data): CommissionRule {
            $rule = CommissionRule::create([...$data, 'status' => 'draft', 'strategy' => 'direct_v1', 'level' => 1]);
            $this->audit->record($actor->id, 'commission_rule_drafted', 'commission_rules', $rule->id, 'rule:'.$rule->id.':draft', ['version' => $rule->version]);

            return $rule;
        });
    }

    public function publish(User $actor, CommissionRule $rule): void
    {
        Gate::forUser($actor)->authorize('commission-rules.manage');
        DB::transaction(function () use ($actor, $rule): void {
            $locked = CommissionRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'published') {
                return;
            }
            if ($locked->status !== 'draft' || $locked->cap_amount < 1 || ($locked->rate_bps === 0 && $locked->fixed_amount === 0) || $locked->rate_bps > config('partners.commission_budget_bps') || $this->preview($locked)['violations'] > 0) {
                throw ValidationException::withMessages(['rule' => 'Aturan melebihi budget atau tidak dapat dipublish.']);
            }
            $locked->update(['status' => 'published', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record($actor->id, 'commission_rule_published', 'commission_rules', $locked->id, 'rule:'.$locked->id.':published', ['version' => $locked->version]);
        }, attempts: 3);
    }

    /** @return array{checked: int, violations: int, examples: list<array{sku: string, base: int, commission: int}>} */
    public function preview(CommissionRule $rule): array
    {
        $version = new RuleVersion($rule->version, $rule->rate_bps, $rule->fixed_amount, $rule->cap_amount, $rule->valid_from, $rule->valid_until);
        $query = ProductVariant::where('status', 'active')->whereHas('product', function ($query) use ($rule): void {
            $query->where('commission_eligible', true)->where('status', 'active');
            if ($rule->product_id !== null) {
                $query->where('id', $rule->product_id);
            }
            if ($rule->category_id !== null) {
                $query->where('category_id', $rule->category_id);
            }
        });
        $count = 0;
        $violations = 0;
        $examples = [];
        foreach ($query->orderBy('id')->cursor() as $variant) {
            $base = new Idr($variant->price - $variant->discount_amount);
            $commission = $this->calculator->calculate($base, $version, $rule->valid_from);
            $violations += $commission->amount > $base->basisPoints(config('partners.commission_budget_bps'))->amount ? 1 : 0;
            $count++;
            if (count($examples) < 10) {
                $examples[] = ['sku' => $variant->sku, 'base' => $base->amount, 'commission' => $commission->amount];
            }
        }

        return ['checked' => $count, 'violations' => $violations, 'examples' => $examples];
    }
}
