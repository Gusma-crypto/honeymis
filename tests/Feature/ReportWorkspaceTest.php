<?php

namespace Tests\Feature;

use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class ReportWorkspaceTest extends FinanceTestCase
{
    public function test_empty_report_has_zero_metrics_and_zero_filled_trend(): void
    {
        $this->financeActors();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('admin.reports.index', ['period' => now('Asia/Jakarta')->format('Y-m')]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/reports')->where('workspace.stats.sales', 0)->where('workspace.stats.commissions', 0)
                ->has('workspace.trend', 6)->where('workspace.trend.5.orders', 0)->has('workspace.performers', 0)->has('workspace.distribution', 0));
    }

    public function test_month_filter_and_trend_use_jakarta_midnight_boundaries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'));
        config(['commerce.shipping_flat_idr' => 0]);
        $this->financeActors();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 16:59:59', 'UTC'));
        $this->commissionOrder();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00:00', 'UTC'));
        $this->commissionOrder();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('admin.reports.index', ['period' => '2026-10']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.stats.sales', 100000)->where('workspace.trend.5.period', '2026-10')->where('workspace.trend.5.orders', 1)->where('workspace.trend.5.sales', 100000));
        $this->get(route('admin.reports.index', ['period' => '2026-11', 'trend' => '12']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.trend', 12)->where('workspace.trend.10.orders', 1)->where('workspace.trend.11.orders', 1));
    }

    public function test_processed_refunds_reduce_product_sales_and_held_orders_are_excluded(): void
    {
        config(['commerce.shipping_flat_idr' => 0]);
        $this->financeActors();
        $order = $this->commissionOrder();
        $held = $this->commissionOrder();
        $held->update(['fraud_hold' => true]);
        Refund::factory()->create(['order_id' => $order->id, 'order_item_id' => $order->items()->firstOrFail()->id, 'amount' => 25000, 'status' => 'processed']);
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('admin.reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.stats.sales', 75000)
            ->where('workspace.distribution.0.sales', 75000)->has('workspace.distribution', 1)->where('report.net', 75000));
    }

    public function test_team_turnover_excludes_personal_sales_and_includes_descendant_sales(): void
    {
        config(['commerce.shipping_flat_idr' => 0]);
        $this->financeActors();
        $child = Partner::factory()->active()->create(['sponsor_partner_id' => $this->partner->id]);
        $this->commissionOrder();
        $this->commissionOrder(referralPartner: $child);
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('admin.reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.performers.0.id', $this->partner->id)->where('workspace.performers.0.personal_sales', 100000)->where('workspace.performers.0.team_sales', 100000)
            ->where('workspace.performers.1.id', $child->id)->where('workspace.performers.1.team_sales', 0)->where('workspace.stats.downline', 1));
    }

    public function test_store_staff_cannot_receive_financial_or_audit_data_through_tabs_or_exports(): void
    {
        $this->financeActors();
        $this->commissionOrder();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);
        $this->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page
            ->missing('workspace.stats.commissions')->missing('workspace.trend.5.commissions')->missing('workspace.performers.0.commissions'));

        foreach (['commissions', 'payouts', 'activity'] as $tab) {
            $this->get(route('admin.reports.index', ['tab' => $tab]))->assertForbidden();
            $this->get(route('admin.reports.export', ['type' => $tab, 'from' => now('Asia/Jakarta')->startOfMonth()->toDateString(), 'until' => now('Asia/Jakarta')->endOfMonth()->toDateString()]))->assertForbidden();
        }
    }

    public function test_report_details_are_paginated_and_export_contains_the_matching_period(): void
    {
        $this->financeActors();
        Partner::factory()->active()->count(17)->create();
        Partner::factory()->active()->create(['referral_code' => 'OUTSIDE-PERIOD', 'created_at' => now('Asia/Jakarta')->startOfMonth()->subDay()->utc()]);
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('admin.reports.index', ['tab' => 'members', 'page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.details.total', 18)->where('workspace.details.current_page', 2)->has('workspace.details.data', 3));
        $query = ['from' => now('Asia/Jakarta')->startOfMonth()->toDateString(), 'until' => now('Asia/Jakarta')->endOfMonth()->toDateString()];
        foreach (['orders', 'commissions', 'network', 'members', 'products', 'payouts', 'activity', 'summary'] as $type) {
            $response = $this->get(route('admin.reports.export', ['type' => $type, ...$query]))->assertOk()->assertDownload('honeycommerce-'.$type.'.csv');
            $this->assertNotEmpty($response->streamedContent());
            if ($type === 'members') {
                $this->assertStringContainsString($this->partner->referral_code, $response->streamedContent());
                $this->assertStringNotContainsString('OUTSIDE-PERIOD', $response->streamedContent());
            }
            $this->get(route('admin.reports.index', ['tab' => $type]))->assertOk();
        }
    }

    public function test_invalid_report_filters_are_rejected(): void
    {
        $this->financeActors();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->get(route('admin.reports.index', ['period' => '2026-13', 'trend' => '1000', 'tab' => 'unknown']))->assertSessionHasErrors(['period', 'trend', 'tab']);
        $this->get(route('admin.reports.index', ['from' => '2025-01-01', 'until' => '2026-12-31']))->assertSessionHasErrors('until');
        $this->get(route('admin.reports.index', ['from' => '2026-10-31', 'until' => '2026-10-01']))->assertSessionHasErrors('until');
    }

    public function test_customers_cannot_view_reports(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->get(route('admin.reports.index'))->assertForbidden();
    }
}
