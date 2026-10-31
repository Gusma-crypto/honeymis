<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Domains\Partners\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class AdministratorDashboardTest extends FinanceTestCase
{
    public function test_administrator_sees_bounded_operational_lists_and_review_counts(): void
    {
        $this->financeActors();
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $orders = Order::factory()->count(9)->create();
        Payment::factory()->create(['order_id' => $orders->first()->id, 'status' => 'submitted']);
        ProductVariant::factory()->count(7)->withStock(3)->create();
        Partner::factory()->count(2)->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')->where('admin.orders.counts.pending_payment', 9)->where('admin.orders.reviewPayments', 1)
            ->has('admin.orders.recent', 6)->has('admin.lowStock', 5)->where('admin.pendingPartners', 2)->where('admin.activeProducts', 7)
            ->has('admin.finance')->where('wallet', null));
    }

    public function test_customer_does_not_receive_cached_admin_data_and_only_sees_own_orders(): void
    {
        $this->financeActors();
        Order::factory()->count(3)->create();
        $this->actingAs($this->finance)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('admin.finance'));
        $buyer = User::factory()->create();
        $order = Order::factory()->create();
        $order->customer->update(['user_id' => $buyer->id]);

        $this->actingAs($buyer)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('admin', null)->where('stats.Pesanan saya', 1)->where('stats.Menunggu pembayaran', 1)->where('wallet', null));
        $this->actingAs($this->store)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('admin.orders')->has('admin.lowStock')->missing('admin.finance')->missing('admin.payouts')->missing('admin.refundRequests'));
    }

    public function test_warm_summary_avoids_order_queries_and_refreshes_after_fifteen_seconds(): void
    {
        $this->financeActors();
        $this->actingAs($this->store)->get(route('dashboard'))->assertOk();
        Order::factory()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('admin.orders.recent', 0));
        $orderQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `orders`'));
        $this->assertCount(0, $orderQueries);
        DB::disableQueryLog();
        $this->travel(16)->seconds();
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('admin.orders.counts.pending_payment', 1)->has('admin.orders.recent', 1));
    }

    public function test_custom_payout_reviewer_cannot_see_refund_or_sales_summary(): void
    {
        $this->financeActors();
        $reviewer = User::factory()->create();
        $reviewer->givePermissionTo('payouts.review');
        $this->actingAs($this->finance)->get(route('dashboard'))->assertOk();

        $this->actingAs($reviewer)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('admin.payouts')->missing('admin.refundRequests')->missing('admin.finance')->missing('admin.orders')->missing('admin.lowStock'));
    }
}
