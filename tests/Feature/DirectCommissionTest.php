<?php

namespace Tests\Feature;

use App\Domains\Commissions\Actions\ManageCommissionRule;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Wallet\Services\PartnerLedger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\FinanceTestCase;

class DirectCommissionTest extends FinanceTestCase
{
    public function test_paid_sale_accrues_once_and_only_releases_after_delivery_return_window(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        app(CommissionEngine::class)->accrue($order->id);
        app(CommissionEngine::class)->release($order->id);
        $this->assertNotNull($order->partner_referral_id, 'Attribution missing');
        $this->assertNotNull($order->commission_rule_id, 'Published rule must be effective at checkout');
        $this->assertNotNull($order->items()->firstOrFail()->commission_rule_id, 'Item rule missing');
        $this->assertTrue($order->items()->firstOrFail()->commission_eligible_snapshot);
        $this->assertDatabaseCount('commission_entries', 1);
        $this->assertSame(10000, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
        $this->assertSame(0, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->assertSame(1, CommissionEntry::firstOrFail()->level);
    }

    public function test_mature_sale_release_is_idempotent(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(true);
        app(CommissionEngine::class)->release($order->id);
        $this->assertSame(['pending' => 0, 'available' => 10000, 'reserved' => 0, 'paid' => 0, 'receivable' => 0], app(PartnerLedger::class)->balances($this->partner->id));
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_self_referral_does_not_create_commission(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(false, $this->partner->user);
        $this->assertNull($order->partner_referral_id);
        $this->assertDatabaseCount('commission_entries', 0);
    }

    public function test_new_rule_does_not_reprice_existing_order_snapshot(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $rule = CommissionRule::factory()->create(['version' => 9999999, 'rate_bps' => 2000]);
        $effective = $rule->valid_from;
        app(ManageCommissionRule::class)->publish($this->finance, $rule);
        $this->assertTrue($effective->equalTo($rule->fresh()->valid_from));
        app(CommissionEngine::class)->accrue($order->id);
        $this->assertSame(10000, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
        $this->assertSame(1000, $order->items()->firstOrFail()->commission_snapshot['rate_bps']);
    }

    public function test_fraud_hold_blocks_release_and_withdrawal_until_reviewed(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(true);
        $order->update(['fraud_hold' => true]);
        $this->assertSame(0, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->patch(route('admin.orders.hold', $order), ['hold' => false, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(10000, app(PartnerLedger::class)->withdrawable($this->partner->id));
    }

    public function test_draft_budget_violations_and_zero_rates_cannot_be_published(): void
    {
        $this->financeActors();
        $rule = CommissionRule::factory()->create(['rate_bps' => 4000]);
        $this->expectException(ValidationException::class);
        app(ManageCommissionRule::class)->publish($this->finance, $rule);
    }

    public function test_noneligible_product_and_unpublished_rule_never_create_commissions(): void
    {
        $this->financeActors();
        $withoutRule = $this->commissionOrder(publishRule: false);
        $noneligible = $this->commissionOrder(eligible: false);
        $this->assertSame($this->partner->id, $withoutRule->partner_referral_id);
        $this->assertNull($withoutRule->commission_rule_id);
        $this->assertNull($noneligible->items()->firstOrFail()->commission_snapshot);
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }
}
