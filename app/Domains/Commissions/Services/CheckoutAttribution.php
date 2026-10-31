<?php

namespace App\Domains\Commissions\Services;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Models\MlmProgramSetting;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\ReferralCapture;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CheckoutAttribution
{
    public function __construct(private ReferralCapture $referrals, private MlmProgramSettings $settings) {}

    /**
     * @param  list<int>  $variantIds
     * @return array{partner_id: int|null, first_rule_id: int|null, referral: array<string, mixed>|null, rules: array<string, mixed>|null, items: array<int, array<string, mixed>>}
     */
    public function snapshot(User $user, ?string $token, array $variantIds): array
    {
        $attribution = $this->referrals->valid($token, $user->id);
        if ($attribution === null) {
            return ['partner_id' => null, 'first_rule_id' => null, 'referral' => null, 'rules' => null, 'items' => []];
        }

        $program = $this->settings->lockCurrent();
        $generationChain = $this->generationChain($attribution->partner_id);
        $variants = ProductVariant::with('product:id,category_id,commission_eligible')->whereIn('id', $variantIds)
            ->get(['id', 'product_id'])->filter(fn (ProductVariant $variant): bool => $variant->product->commission_eligible);
        $rules = $this->applicableRules($variants);
        $items = [];
        foreach ($variants as $variant) {
            $rule = $rules->first(fn (CommissionRule $rule): bool => $rule->product_id !== null
                ? $rule->product_id === $variant->product_id
                : ($rule->category_id === null || $rule->category_id === $variant->product->category_id));
            if ($rule !== null) {
                $items[$variant->id] = $this->ruleSnapshot($rule, $program);
            }
        }

        return [
            'partner_id' => $attribution->partner_id,
            'first_rule_id' => $items === [] ? null : reset($items)['rule_id'],
            'referral' => ['attribution_id' => $attribution->id, 'beneficiary_partner_id' => $attribution->partner_id, 'level' => 1, 'generation_chain' => $generationChain],
            'rules' => [
                'strategy' => 'mlm_v1',
                'program_version' => $program->version,
                'generation_rates' => $program->generation_rates,
                'first_order_referral_bps' => $program->referral_first_order_bps,
                'return_days' => config('partners.return_window_days'),
                'items' => $items,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, CommissionRule>
     */
    private function applicableRules(Collection $variants): Collection
    {
        if ($variants->isEmpty()) {
            return new Collection;
        }
        $productIds = $variants->pluck('product_id')->unique()->all();
        $categoryIds = $variants->map(fn (ProductVariant $variant): int => $variant->product->category_id)->unique()->all();
        $effectiveAt = now();

        return CommissionRule::where('status', 'published')->where('level', 1)->where('strategy', 'direct_v1')
            ->where('valid_from', '<=', $effectiveAt)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', $effectiveAt))
            ->where(fn ($query) => $query->whereIn('product_id', $productIds)
                ->orWhere(fn ($query) => $query->whereNull('product_id')->whereIn('category_id', $categoryIds))
                ->orWhere(fn ($query) => $query->whereNull('product_id')->whereNull('category_id')))
            ->orderByRaw('CASE WHEN product_id IS NOT NULL THEN 0 WHEN category_id IS NOT NULL THEN 1 ELSE 2 END')
            ->orderByDesc('version')
            ->get(['id', 'product_id', 'category_id', 'version', 'rate_bps', 'fixed_amount', 'cap_amount', 'valid_from', 'valid_until']);
    }

    /** @return array<string, mixed> */
    private function ruleSnapshot(CommissionRule $rule, MlmProgramSetting $program): array
    {
        return [
            'rule_id' => $rule->id, 'version' => $rule->version, 'rate_bps' => $rule->rate_bps,
            'fixed_amount' => $rule->fixed_amount, 'cap_amount' => $rule->cap_amount,
            'valid_from' => $rule->valid_from->toIso8601String(), 'valid_until' => $rule->valid_until?->toIso8601String(),
            'budget_bps' => config('partners.commission_budget_bps'), 'return_days' => config('partners.return_window_days'), 'level' => 1,
            'program_version' => $program->version, 'generation_rates' => $program->generation_rates,
            'first_order_referral_bps' => $program->referral_first_order_bps,
        ];
    }

    /** @return list<array{partner_id: int, level: int}> */
    private function generationChain(int $partnerId): array
    {
        $chain = [];
        $currentId = $partnerId;
        $seen = [];

        for ($level = 1; $level <= 5 && ! isset($seen[$currentId]); $level++) {
            $partner = Partner::whereKey($currentId)->first(['id', 'sponsor_partner_id']);
            if ($partner === null) {
                break;
            }
            $seen[$currentId] = true;
            $chain[] = ['partner_id' => $currentId, 'level' => $level];
            $currentId = (int) $partner->sponsor_partner_id;
            if ($currentId < 1) {
                break;
            }
        }

        return $chain;
    }
}
