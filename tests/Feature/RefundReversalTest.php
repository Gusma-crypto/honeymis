<?php

namespace Tests\Feature;

use App\Domains\Orders\Actions\ProcessRefund;
use App\Domains\Payouts\Actions\ManagePayout;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\FinanceTestCase;

class RefundReversalTest extends FinanceTestCase
{
    public function test_partial_refund_reverses_pending_commission_once_with_cumulative_rounding(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $item = $order->items()->firstOrFail();
        $service = app(ProcessRefund::class);
        $key = (string) Str::uuid();
        $service->handle($this->finance, $order, $item->id, 33333, 'Partial', $key, true);
        $service->handle($this->finance, $order, $item->id, 33333, 'Partial', $key, true);
        $this->assertSame(6666, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
        $service->handle($this->finance, $order, $item->id, 66667, 'Remaining', (string) Str::uuid(), true);
        $this->assertSame(0, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
        $this->assertSame('paid', $order->fresh()->status);
        $service->handle($this->finance, $order, null, $order->shipping_total, 'Shipping', (string) Str::uuid(), true);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $item->variant_id, 'reserved' => 0, 'on_hand' => 10]);
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_refund_cancels_open_payout_before_debiting_available_balance(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(true);
        $payout = app(ManagePayout::class)->request($this->partner->user, $this->partner, 8000, $this->bank(), (string) Str::uuid());
        app(ProcessRefund::class)->handle($this->finance, $order, $order->items()->firstOrFail()->id, 50000, 'Partial', (string) Str::uuid(), true);
        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertSame(['pending' => 0, 'available' => 5000, 'reserved' => 0, 'paid' => 0, 'receivable' => 0], app(PartnerLedger::class)->balances($this->partner->id));
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_refund_after_paid_payout_creates_receivable_then_offsets_future_earnings(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(true);
        $service = app(ManagePayout::class);
        $payout = $service->request($this->partner->user, $this->partner, 10000, $this->bank(), (string) Str::uuid());
        $service->decide($this->finance, $payout, 'approve', (string) Str::uuid());
        $service->decide($this->finance, $payout, 'paid', (string) Str::uuid(), reference: 'PAID-TEST', proof: UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), confirmed: true);
        app(ProcessRefund::class)->handle($this->finance, $order, $order->items()->firstOrFail()->id, 100000, 'Return', (string) Str::uuid(), true);
        $this->assertSame(10000, app(PartnerLedger::class)->balances($this->partner->id)['receivable']);
        $this->assertSame(0, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->commissionOrder(true);
        $this->assertSame(['pending' => 0, 'available' => 0, 'reserved' => 0, 'paid' => 10000, 'receivable' => 0], app(PartnerLedger::class)->balances($this->partner->id));
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_customer_request_does_not_move_money_until_finance_processes_it(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $buyer = User::findOrFail($order->customer->user_id);
        $this->actingAs($buyer)->post(route('orders.refund', $order), ['order_item_id' => $order->items()->firstOrFail()->id, 'amount' => 10000, 'reason' => 'Return', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseHas('refunds', ['status' => 'requested', 'amount' => 10000]);
        $this->assertSame(10000, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
    }

    public function test_fixed_commission_is_prorated_for_partial_refund(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(ruleAttributes: ['rate_bps' => 0, 'fixed_amount' => 10000]);
        app(ProcessRefund::class)->handle($this->finance, $order, $order->items()->firstOrFail()->id, 50000, 'Partial fixed refund', (string) Str::uuid(), true);
        $this->assertSame(5000, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_capped_commission_is_prorated_for_partial_refund(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(ruleAttributes: ['rate_bps' => 2000, 'cap_amount' => 10000]);
        app(ProcessRefund::class)->handle($this->finance, $order, $order->items()->firstOrFail()->id, 50000, 'Partial capped refund', (string) Str::uuid(), true);
        $this->assertSame(5000, app(PartnerLedger::class)->balances($this->partner->id)['pending']);
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }
}
