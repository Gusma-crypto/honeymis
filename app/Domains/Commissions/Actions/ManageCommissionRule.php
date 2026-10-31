<?php

namespace App\Domains\Commissions\Actions;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Services\DirectCommissionCalculator;
use App\Domains\Commissions\Services\MlmCommissionBudget;
use App\Domains\Commissions\Services\MlmProgramSettings;
use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\ValueObjects\Idr;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageCommissionRule
{
    public function __construct(private DirectCommissionCalculator $calculator, private AuditWriter $audit, private MlmCommissionBudget $budget, private MlmProgramSettings $settings) {}

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
            $this->settings->lockCurrent();
            $locked = CommissionRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'published') {
                return;
            }
            $percentageRateValid = $locked->fixed_amount === 0 && $locked->rate_bps >= 1500 && $locked->rate_bps <= 2000;
            $legacyFixedRateValid = $locked->rate_bps === 0 && $locked->fixed_amount > 0;
            if ($locked->status !== 'draft' || $locked->cap_amount < 1 || (! $percentageRateValid && ! $legacyFixedRateValid) || $this->preview($locked)['violations'] > 0) {
                throw ValidationException::withMessages(['rule' => 'Aturan melebihi budget atau tidak dapat dipublish.']);
            }
            $locked->update(['status' => 'published', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record($actor->id, 'commission_rule_published', 'commission_rules', $locked->id, 'rule:'.$locked->id.':published', ['version' => $locked->version]);
        }, attempts: 3);
    }

    /** @return array{checked: int, violations: int, examples: list<array{sku: string, base: int, commission: int, network: int, margin: int}>} */
    public function preview(CommissionRule $rule): array
    {
        $version = new RuleVersion($rule->version, $rule->rate_bps, $rule->fixed_amount, $rule->cap_amount, $rule->valid_from, $rule->valid_until);
        $settings = $this->settings->current();
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
        $violations = $this->budget->violations($settings->generation_rates, $settings->referral_first_order_bps, $rule);
        $examples = [];
        foreach ($query->orderBy('id')->cursor() as $variant) {
            $base = new Idr($variant->price - $variant->discount_amount);
            $commission = $this->calculator->calculate($base, $version, $rule->valid_from);
            $networkRates = [max($settings->generation_rates[0] ?? 0, $settings->referral_first_order_bps), ...array_slice($settings->generation_rates, 1)];
            $network = array_sum(array_map(fn (int $rate): int => $base->basisPoints($rate)->amount, $networkRates));
            $margin = max(0, $base->amount - $variant->cost);
            $count++;
            if (count($examples) < 10) {
                $examples[] = ['sku' => $variant->sku, 'base' => $base->amount, 'commission' => $commission->amount, 'network' => $network, 'margin' => $margin];
            }
        }

        return ['checked' => $count, 'violations' => $violations, 'examples' => $examples];
    }
}
