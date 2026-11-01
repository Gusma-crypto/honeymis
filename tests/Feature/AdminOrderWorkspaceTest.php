<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Orders\Actions\ManageOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Models\Partner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class AdminOrderWorkspaceTest extends FinanceTestCase
{
    public function test_staff_orders_show_real_customer_products_and_filters(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        Order::factory()->create(['status' => 'cancelled']);
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.orders.index', ['search' => $order->items()->firstOrFail()->name_snapshot, 'payment' => 'verified', 'period' => $order->placed_at->setTimezone('Asia/Jakarta')->format('Y-m')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/orders')->where('total', 2)->where('counts.cancelled', 1)
            ->has('orders.data', 1)->where('orders.data.0.id', $order->id)->where('orders.data.0.payment_status', 'verified')
            ->where('orders.data.0.customer.name', $order->customer->name)->where('orders.data.0.item_count', 1)->where('orders.data.0.items.0.image_url', null));
    }

    public function test_tracking_import_uses_shipping_workflow_and_is_idempotent(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        app(ManageOrder::class)->handle($this->store, $order, 'process', (string) Str::uuid());
        $contents = "order_number,courier,tracking_number\n".$order->order_number.",JNE,TEST-RESI-001\n";
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.orders.import-tracking'), ['file' => UploadedFile::fake()->createWithContent('resi.csv', $contents)])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.orders.import-tracking'), ['file' => UploadedFile::fake()->createWithContent('resi.csv', $contents)])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'courier' => 'JNE', 'tracking_number' => 'TEST-RESI-001']);
        $this->assertSame(9, InventoryBalance::where('variant_id', $order->items()->firstOrFail()->variant_id)->firstOrFail()->on_hand);
    }

    public function test_one_invalid_import_row_reverses_all_shipping_changes(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        app(ManageOrder::class)->handle($this->store, $order, 'process', (string) Str::uuid());
        $pending = Order::factory()->create(['order_number' => 'ZZZ-PENDING']);
        $contents = "order_number,courier,tracking_number\n".$order->order_number.",JNE,TEST-RESI-001\n".$pending->order_number.",JNE,INVALID\n";
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.orders.import-tracking'), ['file' => UploadedFile::fake()->createWithContent('resi.csv', $contents)])->assertSessionHasErrors('file');
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('pending_payment', $pending->fresh()->status);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_customer_cannot_read_staff_orders_or_import_shipments(): void
    {
        $this->financeActors();
        $this->actingAs($this->partner->user)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.orders.export'))->assertForbidden();
        $this->post(route('admin.orders.import-tracking'), [])->assertForbidden();
    }

    public function test_partner_network_is_scoped_and_does_not_expose_other_members_finances(): void
    {
        $this->financeActors();
        $child = Partner::factory()->active()->create(['sponsor_partner_id' => $this->partner->id]);
        $outside = Partner::factory()->active()->create();
        $this->actingAs($this->partner->user)->get(route('partner.network', ['partner_id' => $outside->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('partner/network')->where('networkSummary.root_id', $this->partner->id)
                ->has('networkSummary.nodes', 2)->where('networkSummary.nodes.1.id', $child->id)->where('ownSummary.commissions', 0)->missing('performance')->missing('balances'));
        $this->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.partners.index'))->assertForbidden();
    }
}
