<?php

namespace Tests\Feature;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\MlmProgramSetting;
use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Orders\Actions\ProcessRefund;
use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Actions\ManagePartner;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\PartnerRankService;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\FinanceTestCase;

class MlmCommissionTest extends FinanceTestCase
{
    public function test_recruiting_and_activating_a_partner_does_not_create_a_bonus(): void
    {
        $this->financeActors();
        $recruit = User::factory()->create();
        $administrator = User::factory()->create();
        $administrator->assignRole('super_admin');

        $partner = app(ManagePartner::class)->apply($recruit, $this->partner->referral_code);
        app(ManagePartner::class)->decide($administrator, $partner, 'active', (string) Str::uuid());

        $this->assertSame($this->partner->id, $partner->sponsor_partner_id);
        $this->assertNotSame($this->partner->referral_code, $partner->referral_code);
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    #[TestWith([10, 29])]
    #[TestWith([9, 30])]
    public function test_bronze_requires_both_targets_to_be_met(int $personalQuantity, int $teamQuantity): void
    {
        $this->financeActors();
        $downline = Partner::factory()->active()->create(['sponsor_partner_id' => $this->partner->id]);
        $this->commissionOrder(quantity: $teamQuantity, referralPartner: $downline);
        $this->commissionOrder(quantity: $personalQuantity);

        $summary = app(PartnerRankService::class)->currentSummary($this->partner);

        $this->assertSame('Member', $summary['rank']);
    }

    #[TestWith(['Bronze', [10], [30]])]
    #[TestWith(['Silver', [30], [99, 1]])]
    #[TestWith(['Gold', [75], [99, 99, 52]])]
    #[TestWith(['Platinum', [99, 51], [99, 99, 99, 99, 99, 99, 6]])]
    public function test_rank_is_awarded_at_both_monthly_targets(string $rank, array $personalQuantities, array $teamQuantities): void
    {
        $this->financeActors();
        $sponsor = Partner::factory()->active()->create();
        $downline = Partner::factory()->active()->create(['sponsor_partner_id' => $sponsor->id]);
        foreach ($teamQuantities as $quantity) {
            $this->commissionOrder(quantity: $quantity, referralPartner: $downline);
        }
        foreach ($personalQuantities as $quantity) {
            $this->commissionOrder(quantity: $quantity, referralPartner: $sponsor);
        }

        $summary = app(PartnerRankService::class)->currentSummary($sponsor);

        $this->assertSame($rank, $summary['rank']);
    }

    #[TestWith(['fraud_hold'])]
    #[TestWith(['underpaid'])]
    #[TestWith(['unverified'])]
    public function test_invalid_sales_do_not_contribute_to_personal_or_team_turnover(string $invalidState): void
    {
        $this->financeActors();
        $sponsor = Partner::factory()->active()->create();
        $downline = Partner::factory()->active()->create(['sponsor_partner_id' => $sponsor->id]);
        $order = $this->commissionOrder(referralPartner: $downline);
        if ($invalidState === 'fraud_hold') {
            $order->update(['fraud_hold' => true]);
        } elseif ($invalidState === 'underpaid') {
            $order->payments()->update(['amount' => $order->grand_total - 1]);
        } else {
            $order->payments()->update(['verified_at' => null]);
        }

        $personal = app(PartnerRankService::class)->currentSummary($downline);
        $team = app(PartnerRankService::class)->currentSummary($sponsor);

        $this->assertSame(0, $personal['personal_sales']);
        $this->assertSame(0, $team['team_sales']);
    }

    public function test_scheduled_recalculation_starts_a_new_month_without_previous_month_turnover(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
        $this->financeActors();
        $this->commissionOrder(quantity: 10);

        $this->travelTo(now('Asia/Jakarta')->addMonth()->startOfMonth());
        $this->artisan('honey:recalculate-ranks')->assertSuccessful();

        $this->assertDatabaseHas('partner_monthly_ranks', [
            'partner_id' => $this->partner->id, 'period_start' => '2026-11-01',
            'rank' => 'Member', 'personal_sales' => 0, 'team_sales' => 0,
        ]);
        $this->assertDatabaseHas('partner_monthly_ranks', [
            'partner_id' => $this->partner->id, 'period_start' => '2026-10-01', 'personal_sales' => 1_000_000,
        ]);
    }

    public function test_retry_after_refunding_an_earlier_order_does_not_add_a_first_order_bonus(): void
    {
        $this->financeActors();
        $buyer = User::factory()->create();
        $first = $this->commissionOrder(buyer: $buyer);
        $repeat = $this->commissionOrder(buyer: $buyer);
        app(ProcessRefund::class)->handle($this->finance, $first, $first->items()->firstOrFail()->id, 100000, 'Refund produk', (string) Str::uuid(), true);
        app(ProcessRefund::class)->handle($this->finance, $first->fresh(), null, $first->shipping_total + $first->tax_total, 'Refund ongkir', (string) Str::uuid(), true);

        app(CommissionEngine::class)->accrue($repeat->id);

        $this->assertSame(19000, CommissionEntry::whereHas('item', fn ($query) => $query->where('order_id', $repeat->id))->sum('amount'));
        $this->assertDatabaseMissing('commission_entries', [
            'order_item_id' => $repeat->items()->firstOrFail()->id, 'commission_type' => 'referral',
        ]);
    }

    public function test_monthly_rank_and_next_target_ignore_json_key_order(): void
    {
        $this->financeActors();
        $sponsor = Partner::factory()->active()->create();
        $downline = Partner::factory()->active()->create(['sponsor_partner_id' => $sponsor->id]);
        $this->commissionOrder(quantity: 30, referralPartner: $downline);
        $this->commissionOrder(quantity: 10, referralPartner: $sponsor);
        MlmProgramSetting::findOrFail(1)->update([
            'rank_requirements' => array_reverse(config('mlm.rank_requirements'), true),
        ]);

        $summary = app(PartnerRankService::class)->currentSummary($sponsor);

        $this->assertSame('Bronze', $summary['rank']);
        $this->assertSame('Silver', $summary['next_rank']);
        $this->assertSame(3_000_000, $summary['personal_target']);
        $this->assertSame(10_000_000, $summary['team_target']);
    }

    public function test_paid_first_order_pays_direct_and_exactly_five_network_generations(): void
    {
        $this->financeActors();
        $partnersByLevel = [6 => Partner::factory()->active()->create()];
        for ($level = 5; $level >= 1; $level--) {
            $partnersByLevel[$level] = Partner::factory()->active()->create([
                'sponsor_partner_id' => $partnersByLevel[$level + 1]->id,
            ]);
        }

        $this->commissionOrder(referralPartner: $partnersByLevel[1]);

        $entries = CommissionEntry::orderBy('level')->orderBy('commission_type')->get()
            ->map(fn (CommissionEntry $entry): array => [
                'partner_id' => $entry->beneficiary_partner_id,
                'level' => $entry->level,
                'type' => $entry->commission_type,
                'amount' => $entry->amount,
            ])->all();

        $this->assertSame([
            ['partner_id' => $partnersByLevel[1]->id, 'level' => 0, 'type' => 'direct', 'amount' => 15000],
            ['partner_id' => $partnersByLevel[1]->id, 'level' => 1, 'type' => 'referral', 'amount' => 5000],
            ['partner_id' => $partnersByLevel[2]->id, 'level' => 2, 'type' => 'network', 'amount' => 2000],
            ['partner_id' => $partnersByLevel[3]->id, 'level' => 3, 'type' => 'network', 'amount' => 1000],
            ['partner_id' => $partnersByLevel[4]->id, 'level' => 4, 'type' => 'network', 'amount' => 500],
            ['partner_id' => $partnersByLevel[5]->id, 'level' => 5, 'type' => 'network', 'amount' => 500],
        ], $entries);
        $this->assertSame(20000, app(PartnerLedger::class)->balances($partnersByLevel[1]->id)['pending']);
        $this->assertSame(2000, app(PartnerLedger::class)->balances($partnersByLevel[2]->id)['pending']);
        $this->assertDatabaseMissing('commission_entries', [
            'beneficiary_partner_id' => $partnersByLevel[6]->id,
            'commission_type' => 'network',
        ]);
    }

    public function test_five_percent_referral_rate_is_replaced_by_four_percent_on_repeat_orders(): void
    {
        $this->financeActors();
        $customer = User::factory()->create();

        $firstOrder = $this->commissionOrder(buyer: $customer);
        $secondOrder = $this->commissionOrder(buyer: $customer);

        $firstReferral = CommissionEntry::whereHas('item', fn ($query) => $query->where('order_id', $firstOrder->id))
            ->where('level', 1)->firstOrFail();
        $repeatGenerationOne = CommissionEntry::whereHas('item', fn ($query) => $query->where('order_id', $secondOrder->id))
            ->where('level', 1)->firstOrFail();

        $this->assertSame('referral', $firstReferral->commission_type);
        $this->assertSame(5000, $firstReferral->amount);
        $this->assertSame('network', $repeatGenerationOne->commission_type);
        $this->assertSame(4000, $repeatGenerationOne->amount);
    }

    public function test_partial_refund_reverses_direct_and_each_upline_commission_and_monthly_sales(): void
    {
        $this->financeActors();
        $upline = Partner::factory()->active()->create();
        $referralPartner = Partner::factory()->active()->create(['sponsor_partner_id' => $upline->id]);
        $order = $this->commissionOrder(referralPartner: $referralPartner);
        $item = $order->items()->firstOrFail();

        app(ProcessRefund::class)->handle($this->finance, $order, $item->id, 50000, 'Pengembalian sebagian', (string) Str::uuid(), true);

        $this->assertSame(10000, app(PartnerLedger::class)->balances($referralPartner->id)['pending']);
        $this->assertSame(1000, app(PartnerLedger::class)->balances($upline->id)['pending']);
        $this->assertDatabaseHas('partner_monthly_ranks', [
            'partner_id' => $referralPartner->id,
            'personal_sales' => 50000,
        ]);
        $this->assertDatabaseHas('partner_monthly_ranks', [
            'partner_id' => $upline->id,
            'team_sales' => 50000,
        ]);
    }

    public function test_monthly_rank_requires_both_personal_and_downline_turnover(): void
    {
        $this->financeActors();
        $sponsor = Partner::factory()->active()->create();
        $downline = Partner::factory()->active()->create(['sponsor_partner_id' => $sponsor->id]);

        $this->commissionOrder(quantity: 30, referralPartner: $downline);
        $this->commissionOrder(quantity: 10, referralPartner: $sponsor);

        $summary = app(PartnerRankService::class)->currentSummary($sponsor);

        $this->assertSame('Bronze', $summary['rank']);
        $this->assertSame(1_000_000, $summary['personal_sales']);
        $this->assertSame(3_000_000, $summary['team_sales']);
        $this->assertSame('Silver', $summary['next_rank']);
        $this->assertDatabaseHas('partner_monthly_ranks', [
            'partner_id' => $sponsor->id,
            'rank' => 'Bronze',
            'period_start' => now('Asia/Jakarta')->startOfMonth()->toDateString(),
        ]);
    }

    public function test_refund_recalculates_a_monthly_rank_when_team_turnover_falls_below_target(): void
    {
        $this->financeActors();
        $sponsor = Partner::factory()->active()->create();
        $downline = Partner::factory()->active()->create(['sponsor_partner_id' => $sponsor->id]);
        $this->commissionOrder(quantity: 30, referralPartner: $downline);
        $this->commissionOrder(quantity: 10, referralPartner: $sponsor);
        $order = Order::query()->where('partner_referral_id', $downline->id)->firstOrFail();
        $item = $order->items()->firstOrFail();

        $beforeRefund = app(PartnerRankService::class)->currentSummary($sponsor);
        app(ProcessRefund::class)->handle($this->finance, $order, $item->id, 1_500_000, 'Refund penurunan rank', (string) Str::uuid(), true);
        $afterRefund = app(PartnerRankService::class)->currentSummary($sponsor);

        $this->assertSame('Bronze', $beforeRefund['rank']);
        $this->assertSame('Member', $afterRefund['rank']);
        $this->assertSame(1_500_000, $afterRefund['team_sales']);
    }
}
