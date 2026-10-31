<?php

namespace App\Domains\Commissions\Services;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Shared\ValueObjects\Idr;
use Carbon\CarbonImmutable;

class MlmCommissionBudget
{
    /** @param list<int> $generationRates */
    public function violations(array $generationRates, int $firstReferralRateBps, ?CommissionRule $override = null): int
    {
        $rules = array_values(CommissionRule::where('status', 'published')->get()->values()->all());
        if ($override !== null) {
            $rules[] = $override;
        }
        $effectiveFrom = $override === null ? CarbonImmutable::now() : $override->valid_from;
        $effectiveTimes = [$effectiveFrom];
        foreach ($rules as $rule) {
            if ($rule->valid_from > $effectiveFrom) {
                $effectiveTimes[] = $rule->valid_from;
            }
            if ($rule->valid_until !== null && $rule->valid_until > $effectiveFrom) {
                $effectiveTimes[] = $rule->valid_until;
            }
        }
        $variants = ProductVariant::with('product:id,category_id,commission_eligible')
            ->where('status', 'active')
            ->whereHas('product', fn ($query) => $query->where('status', 'active')->where('commission_eligible', true))
            ->orderBy('id')
            ->get();
        $violations = 0;

        foreach ($variants as $variant) {
            $base = max(0, $variant->price - $variant->discount_amount);
            $margin = max(0, $base - $variant->cost);
            $networkRates = [max($generationRates[0] ?? 0, $firstReferralRateBps), ...array_slice($generationRates, 1)];
            $network = array_sum(array_map(
                static fn (int $rate): int => (new Idr($base))->basisPoints($rate)->amount,
                $networkRates,
            ));

            foreach ($effectiveTimes as $effectiveAt) {
                $rule = $this->ruleFor($variant->product_id, $variant->product->category_id, $rules, $override, $effectiveAt);
                if ($rule === null) {
                    continue;
                }
                $direct = $rule->fixed_amount > 0
                    ? min($rule->fixed_amount, $rule->cap_amount, $base)
                    : min((new Idr($base))->basisPoints($rule->rate_bps)->amount, $rule->cap_amount, $base, (new Idr($base))->basisPoints((int) config('partners.commission_budget_bps'))->amount);
                $maximumCommission = min($margin, (new Idr($base))->basisPoints((int) config('mlm.maximum_total_commission_bps'))->amount);
                if ($direct + $network > $maximumCommission) {
                    $violations++;
                    break;
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<CommissionRule>  $rules
     */
    private function ruleFor(int $productId, int $categoryId, array $rules, ?CommissionRule $override, CarbonImmutable $at): ?CommissionRule
    {
        $matching = array_values(array_filter($rules, function (CommissionRule $rule) use ($productId, $categoryId, $at, $override): bool {
            if ($override !== null && $rule->is($override)) {
                return $rule->valid_from <= $at && ($rule->valid_until === null || $rule->valid_until > $at)
                    && ($rule->product_id === null || $rule->product_id === $productId)
                    && ($rule->category_id === null || $rule->category_id === $categoryId);
            }

            return $rule->status === 'published' && $rule->valid_from <= $at && ($rule->valid_until === null || $rule->valid_until > $at)
                && ($rule->product_id === null || $rule->product_id === $productId)
                && ($rule->category_id === null || $rule->category_id === $categoryId);
        }));
        usort($matching, function (CommissionRule $first, CommissionRule $second): int {
            $firstSpecificity = $first->product_id !== null ? 2 : ($first->category_id !== null ? 1 : 0);
            $secondSpecificity = $second->product_id !== null ? 2 : ($second->category_id !== null ? 1 : 0);

            return ($secondSpecificity <=> $firstSpecificity) ?: ($second->version <=> $first->version);
        });

        return $matching[0] ?? null;
    }
}
