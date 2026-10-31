<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Models\MlmProgramSetting;
use App\Domains\Commissions\Services\MlmProgramSettings;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\PartnerRankService;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\FinanceTestCase;

class MlmProgramSettingsTest extends FinanceTestCase
{
    public function test_settings_changes_preserve_each_configuration_version(): void
    {
        $this->financeActors();
        $settings = app(MlmProgramSettings::class);
        $original = $settings->current();
        $oldRates = $original->generation_rates;
        $data = [
            'referral_first_order_bps' => 500,
            'generation_rates' => [350, 200, 100, 50, 50],
            'rank_requirements' => $original->rank_requirements,
        ];

        $settings->update($this->finance, $data);
        $data['generation_rates'][0] = 300;
        $settings->update($this->finance, $data);

        $versions = DB::table('mlm_program_versions')->orderBy('version')->get();
        $this->assertSame([1, 2, 3], $versions->pluck('version')->all());
        $this->assertSame($oldRates, json_decode($versions[0]->configuration, true, flags: JSON_THROW_ON_ERROR)['generation_rates']);
        $this->assertSame([350, 200, 100, 50, 50], json_decode($versions[1]->configuration, true, flags: JSON_THROW_ON_ERROR)['generation_rates']);
        $this->assertSame([300, 200, 100, 50, 50], json_decode($versions[2]->configuration, true, flags: JSON_THROW_ON_ERROR)['generation_rates']);
        $this->assertSame($this->finance->id, $versions[2]->changed_by);
        $this->assertDatabaseHas('mlm_program_settings', ['version' => 3]);
    }

    #[TestWith(['update'])]
    #[TestWith(['delete'])]
    public function test_recorded_configuration_cannot_be_changed_or_deleted(string $operation): void
    {
        $original = DB::table('mlm_program_versions')->first();

        try {
            DB::transaction(function () use ($operation): void {
                $query = DB::table('mlm_program_versions');
                if ($operation === 'update') {
                    $query->update(['configuration' => '{}']);
                } else {
                    $query->delete();
                }
            });
            $this->fail('Recorded configuration must remain immutable.');
        } catch (QueryException) {
            $this->assertSame($original->configuration, DB::table('mlm_program_versions')->first()->configuration);
        }
    }

    public function test_admin_cannot_save_commissions_above_sixty_percent_even_without_products(): void
    {
        $this->financeActors();

        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.mlm-settings.update'), [
                'referral_first_order_bps' => 500,
                'generation_rates' => [4000, 200, 100, 50, 50],
                'rank_requirements' => config('mlm.rank_requirements'),
            ])->assertSessionHasErrors([
                'generation_rates' => 'Total komisi langsung maksimum dan bonus jaringan tidak boleh melebihi 60% nilai penjualan produk.',
            ]);

        $this->assertDatabaseHas('mlm_program_settings', ['id' => 1, 'version' => 1]);
        $this->assertDatabaseCount('mlm_program_versions', 1);
    }

    public function test_generation_rates_reject_named_keys(): void
    {
        $this->financeActors();

        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.mlm-settings.update'), [
                'referral_first_order_bps' => 500,
                'generation_rates' => ['one' => 400, 'two' => 200, 'three' => 100, 'four' => 50, 'five' => 50],
                'rank_requirements' => config('mlm.rank_requirements'),
            ])->assertSessionHasErrors('generation_rates');

        $this->assertDatabaseHas('mlm_program_settings', ['id' => 1, 'version' => 1]);
    }

    public function test_finance_can_view_and_update_monthly_rank_and_generation_rates(): void
    {
        $this->financeActors();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->get(route('admin.mlm-settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/mlm-settings')
                ->where('generationRates', [400, 200, 100, 50, 50])
                ->where('rankRequirements.Bronze.personal_sales', 1_000_000));

        $response = $this->put(route('admin.mlm-settings.update'), [
            'referral_first_order_bps' => 500,
            'generation_rates' => [350, 200, 100, 50, 50],
            'rank_requirements' => [
                'Member' => ['personal_sales' => 0, 'team_sales' => 0],
                'Bronze' => ['personal_sales' => 1_000_000, 'team_sales' => 3_000_000],
                'Silver' => ['personal_sales' => 3_000_000, 'team_sales' => 10_000_000],
                'Gold' => ['personal_sales' => 7_500_000, 'team_sales' => 25_000_000],
                'Platinum' => ['personal_sales' => 15_000_000, 'team_sales' => 60_000_000],
            ],
        ]);

        $response->assertRedirect(route('admin.mlm-settings.index'));
        $this->assertDatabaseHas('mlm_program_settings', [
            'id' => 1,
            'version' => 2,
            'updated_by' => $this->finance->id,
        ]);
        $this->assertSame([350, 200, 100, 50, 50], MlmProgramSetting::findOrFail(1)->generation_rates);
    }

    public function test_configuration_is_rejected_if_rates_exceed_product_margin(): void
    {
        $this->financeActors();
        $this->commissionOrder();
        $variant = ProductVariant::firstOrFail();
        $variant->update(['cost' => 95000]);

        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.mlm-settings.update'), [
                'referral_first_order_bps' => 500,
                'generation_rates' => [400, 200, 100, 50, 50],
                'rank_requirements' => config('mlm.rank_requirements'),
            ])
            ->assertSessionHasErrors([
                'generation_rates' => 'Tarif komisi melampaui margin produk pada 1 varian aktif. Sesuaikan tarif langsung atau margin produknya sebelum menyimpan.',
            ]);

        $this->assertSame([400, 200, 100, 50, 50], MlmProgramSetting::findOrFail(1)->generation_rates);
    }

    public function test_configuration_is_rejected_if_a_scheduled_rule_will_exceed_product_margin(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $variant = ProductVariant::findOrFail($order->items()->firstOrFail()->variant_id);
        $variant->update(['cost' => 75_000]);
        CommissionRule::factory()->create([
            'product_id' => $variant->product_id,
            'rate_bps' => 1_600,
            'valid_from' => now()->addMonth(),
            'status' => 'published',
            'approved_by' => $this->finance->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.mlm-settings.update'), [
                'referral_first_order_bps' => 600,
                'generation_rates' => [400, 200, 100, 50, 50],
                'rank_requirements' => config('mlm.rank_requirements'),
            ])
            ->assertSessionHasErrors([
                'generation_rates' => 'Tarif komisi melampaui margin produk pada 1 varian aktif. Sesuaikan tarif langsung atau margin produknya sebelum menyimpan.',
            ]);

        $this->assertDatabaseHas('mlm_program_settings', ['id' => 1, 'version' => 1]);
    }

    public function test_rank_thresholds_must_not_decrease_between_ranks(): void
    {
        $this->financeActors();

        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.mlm-settings.update'), [
                'referral_first_order_bps' => 500,
                'generation_rates' => [400, 200, 100, 50, 50],
                'rank_requirements' => [
                    'Member' => ['personal_sales' => 0, 'team_sales' => 0],
                    'Bronze' => ['personal_sales' => 1_000_000, 'team_sales' => 3_000_000],
                    'Silver' => ['personal_sales' => 500_000, 'team_sales' => 10_000_000],
                    'Gold' => ['personal_sales' => 7_500_000, 'team_sales' => 25_000_000],
                    'Platinum' => ['personal_sales' => 15_000_000, 'team_sales' => 60_000_000],
                ],
            ])
            ->assertSessionHasErrors('rank_requirements.Silver.personal_sales');

        $this->assertDatabaseHas('mlm_program_settings', ['id' => 1, 'version' => 1]);
    }

    public function test_customer_cannot_open_admin_program_settings(): void
    {
        $this->financeActors();
        $customer = User::factory()->create();

        $this->actingAs($customer)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->get(route('admin.mlm-settings.index'))
            ->assertForbidden();

        $this->assertDatabaseHas('mlm_program_settings', ['id' => 1, 'version' => 1]);
    }

    public function test_direct_commission_rule_rejects_rates_below_fifteen_percent(): void
    {
        $this->financeActors();

        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post(route('admin.commission-rules.store'), [
                'version' => 1,
                'rate_bps' => 1499,
                'fixed_amount' => 0,
                'cap_amount' => 100000,
                'valid_from' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('rate_bps');

        $this->assertDatabaseMissing('commission_rules', ['version' => 1]);
    }

    public function test_dashboard_recalculates_a_partner_rank_from_current_month_sales(): void
    {
        $this->financeActors();
        $partner = Partner::factory()->active()->create();

        $summary = app(PartnerRankService::class)->currentSummary($partner);

        $this->assertSame('Member', $summary['rank']);
        $this->assertSame(0, $summary['personal_sales']);
        $this->assertSame(0, $summary['team_sales']);
    }

    public function test_partner_dashboard_shows_current_rank_and_recent_payout_history(): void
    {
        $this->financeActors();
        PayoutRequest::factory()->create(['partner_id' => $this->partner->id, 'amount' => 25_000]);

        $this->actingAs($this->partner->user)->get(route('partner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('partner/dashboard')
                ->where('monthlyRank.rank', 'Member')
                ->has('monthlyRank.personal_sales')
                ->has('monthlyRank.team_sales')
                ->has('payoutHistory', 1)
                ->where('payoutHistory.0.amount', 25_000));
    }
}
