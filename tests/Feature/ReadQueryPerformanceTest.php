<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Actions\ManageCommissionRule;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Services\CheckoutAttribution;
use App\Domains\Partners\Services\ReferralCapture;
use App\Domains\Payouts\Actions\ManagePayout;
use App\Domains\Reports\Services\DashboardSummary;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class ReadQueryPerformanceTest extends FinanceTestCase
{
    public function test_withdrawable_uses_two_queries_with_multiple_commissions_and_excludes_held_funds(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $this->commissionOrder(true);
        $held = $this->commissionOrder(true);
        $held->update(['fraud_hold' => true]);
        $payout = app(ManagePayout::class)->request($this->partner->user, $this->partner, 5000, $this->bank(), (string) Str::uuid());
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame(15000, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
        app(ManagePayout::class)->decide($this->finance, $payout, 'reject', (string) Str::uuid(), 'Uji pengembalian reservasi');
        $this->assertSame(20000, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_checkout_fetches_rules_once_and_preserves_product_category_global_precedence(): void
    {
        $this->financeActors();
        $buyer = User::factory()->create();
        $category = Category::factory()->create();
        $specific = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['category_id' => $category->id, 'commission_eligible' => true])->id]);
        $categoryVariant = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['category_id' => $category->id, 'commission_eligible' => true])->id]);
        $globalVariant = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['commission_eligible' => true])->id]);
        $ineligible = ProductVariant::factory()->create();
        $productRule = $this->publish(['version' => 1, 'product_id' => $specific->product_id]);
        $categoryRule = $this->publish(['version' => 2, 'category_id' => $category->id]);
        $globalRule = $this->publish(['version' => 3]);
        $this->publish(['version' => 4, 'product_id' => $specific->product_id, 'valid_from' => now()->addDay()]);
        $this->publish(['version' => 5, 'product_id' => $specific->product_id, 'valid_from' => now()->subDays(2), 'valid_until' => now()->subDay()]);
        CommissionRule::factory()->create(['version' => 6, 'product_id' => $specific->product_id]);
        $token = app(ReferralCapture::class)->capture($this->partner->referral_code, null, $buyer->id);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $snapshot = app(CheckoutAttribution::class)->snapshot($buyer, $token, [$specific->id, $categoryVariant->id, $globalVariant->id, $ineligible->id]);
        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `commission_rules`'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($productRule->id, $snapshot['items'][$specific->id]['rule_id']);
        $this->assertSame($categoryRule->id, $snapshot['items'][$categoryVariant->id]['rule_id']);
        $this->assertSame($globalRule->id, $snapshot['items'][$globalVariant->id]['rule_id']);
        $this->assertArrayNotHasKey($ineligible->id, $snapshot['items']);
    }

    public function test_catalog_does_not_fetch_inventory_for_inactive_variants(): void
    {
        $product = Product::factory()->create();
        $active = ProductVariant::factory()->withStock()->create(['product_id' => $product->id]);
        ProductVariant::factory()->count(20)->inactive()->withStock()->create(['product_id' => $product->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get(route('catalog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->has('products.data.0.variants', 1)->where('products.data.0.variants.0.id', $active->id));
        $stockQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `inventory_balances`'));
        DB::disableQueryLog();
        $this->assertCount(1, $stockQueries);
        $this->assertStringContainsString("in ({$active->id})", $stockQueries->first()['query']);
    }

    public function test_dashboard_aggregates_payout_counts_and_reserve_in_one_query(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $payouts = app(ManagePayout::class);
        $payouts->request($this->partner->user, $this->partner, 3000, $this->bank(), (string) Str::uuid());
        $approved = $payouts->request($this->partner->user, $this->partner, 2000, $this->bank(), (string) Str::uuid());
        $payouts->decide($this->finance, $approved, 'approve', (string) Str::uuid());
        DB::enableQueryLog();
        DB::flushQueryLog();

        $summary = app(DashboardSummary::class)->forUser($this->finance);
        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `payout_requests`'));
        DB::disableQueryLog();
        $this->assertSame(['requested' => 1, 'approved' => 1, 'reserved' => 5000], $summary['payouts']);
        $this->assertCount(1, $queries);
    }

    /** @param array<string, mixed> $attributes */
    private function publish(array $attributes): CommissionRule
    {
        $rule = CommissionRule::factory()->create($attributes);
        app(ManageCommissionRule::class)->publish($this->finance, $rule);

        return $rule;
    }
}
