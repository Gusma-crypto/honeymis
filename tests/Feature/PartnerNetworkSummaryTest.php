<?php

namespace Tests\Feature;

use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\NetworkSummary;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerNetworkSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_sees_own_legs_and_depth_without_unrelated_branches_or_extra_commissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $root = Partner::factory()->active()->create();
        $root->user->assignRole('partner');
        $first = Partner::factory()->active()->create(['sponsor_partner_id' => $root->id]);
        Partner::factory()->create(['sponsor_partner_id' => $root->id, 'status' => 'pending']);
        $second = Partner::factory()->active()->create(['sponsor_partner_id' => $first->id]);
        Partner::factory()->create(['sponsor_partner_id' => $second->id, 'status' => 'suspended']);
        $outsider = Partner::factory()->active()->create(['user_id' => User::factory()->create(['name' => 'Unrelated private branch'])->id]);
        Partner::factory()->active()->create(['sponsor_partner_id' => $outsider->id]);

        $this->actingAs($root->user)->get(route('partner.dashboard', ['root_id' => $outsider->id]))->assertOk()->assertDontSee('Unrelated private branch')->assertInertia(fn (Assert $page) => $page
            ->where('networkSummary.scope', 'own')->has('networkSummary.nodes', 5)->where('networkSummary.nodes.0.parent_id', null)->where('networkSummary.direct_count', 2)->where('networkSummary.max_depth', 3)
            ->where('networkSummary.total_members', 4)->where('networkSummary.active_members', 2)
            ->where('networkSummary.levels.0.members', 2)->where('networkSummary.levels.1.members', 1)->where('networkSummary.levels.2.suspended', 1)
            ->where('networkSummary.legs.0.members', 3)->where('networkSummary.legs.0.max_depth', 3)->has('networkSummary.legs', 2));
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    public function test_administrator_can_see_company_roots_and_all_levels(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $first = Partner::factory()->active()->create();
        $child = Partner::factory()->active()->create(['sponsor_partner_id' => $first->id]);
        Partner::factory()->create(['sponsor_partner_id' => $child->id]);
        Partner::factory()->active()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('networkSummary.scope', 'company')->where('networkSummary.direct_count', 2)
            ->where('networkSummary.total_members', 4)->where('networkSummary.max_depth', 3)->where('networkSummary.has_cycles', false));
    }

    public function test_finance_without_partner_management_does_not_receive_company_network(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        Partner::factory()->active()->create();

        $this->actingAs($finance)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('networkSummary', null));
    }

    public function test_empty_personal_network_has_zero_depth_and_no_legs(): void
    {
        $root = Partner::factory()->active()->create();

        $summary = app(NetworkSummary::class)->forRoot($root->id);

        $this->assertSame(0, $summary['max_depth']);
        $this->assertSame(0, $summary['total_members']);
        $this->assertSame([], $summary['levels']);
        $this->assertSame([], $summary['legs']);
    }

    public function test_cycles_are_reported_and_never_cause_infinite_traversal(): void
    {
        $first = Partner::factory()->create();
        $second = Partner::factory()->create(['sponsor_partner_id' => $first->id]);
        $first->update(['sponsor_partner_id' => $second->id]);

        $summary = app(NetworkSummary::class)->forRoot($first->id);

        $this->assertTrue($summary['has_cycles']);
        $this->assertSame(1, $summary['total_members']);
        $this->assertSame(1, $summary['max_depth']);
        $this->assertTrue(app(NetworkSummary::class)->forRoot()['has_cycles']);
    }

    public function test_deep_network_summary_uses_one_cached_graph_query_instead_of_per_level_queries(): void
    {
        $root = Partner::factory()->active()->create();
        $parent = $root;
        for ($level = 0; $level < 8; $level++) {
            $parent = Partner::factory()->active()->create(['sponsor_partner_id' => $parent->id]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        $summary = app(NetworkSummary::class)->forRoot($root->id);
        app(NetworkSummary::class)->forRoot($parent->id);

        $this->assertSame(8, $summary['max_depth']);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
