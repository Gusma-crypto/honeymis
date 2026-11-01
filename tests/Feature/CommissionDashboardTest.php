<?php

namespace Tests\Feature;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Domains\Wallet\Services\PayoutAvailabilitySummary;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class CommissionDashboardTest extends FinanceTestCase
{
    public function test_finance_sees_real_ledger_totals_and_can_filter_referral_commissions(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.commissions.index', ['period' => $order->placed_at->setTimezone('Asia/Jakarta')->format('Y-m'), 'type' => 'referral']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/commissions')->where('stats.total', 20000)->where('stats.pending', 20000)->where('stats.available', 0)
            ->has('history.data', 1)->where('history.data.0.type', 'referral')->where('history.data.0.amount', 5000)->where('simulation.0.amount', 10000)->where('simulation.4.amount', 1250));
    }

    public function test_simulator_replaces_first_generation_and_never_posts_money(): void
    {
        $this->financeActors();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.commissions.index', ['base' => 250000, 'first_order' => 1]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('simulation.0.amount', 12500)->where('simulation.0.type', 'referral')
            ->where('simulation.1.amount', 5000)->where('simulation.2.amount', 2500)->where('simulation.3.amount', 1250)->where('simulation.4.amount', 1250));
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    public function test_bulk_payout_availability_matches_wallet_and_excludes_fraud_holds(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(true);
        $this->assertSame(20000, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->assertSame(20000, app(PayoutAvailabilitySummary::class)->total());
        $order->update(['fraud_hold' => true]);
        $this->assertSame(0, app(PayoutAvailabilitySummary::class)->total());
        $this->assertSame(2, CommissionEntry::count());
    }

    public function test_member_cannot_view_financial_data_of_all_partners(): void
    {
        $this->financeActors();
        $this->actingAs($this->partner->user)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.commissions.index'))->assertForbidden();
    }
}
