<?php

namespace Tests\Feature;

use App\Domains\Wallet\Services\PartnerLedger;
use App\Models\User;
use App\Notifications\CommerceUpdate;
use Illuminate\Support\Facades\Notification;
use Tests\FinanceTestCase;

class MvpJourneyTest extends FinanceTestCase
{
    public function test_delivered_referral_order_completes_and_outbox_retry_does_not_duplicate_notifications_or_money(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder(true);
        $this->artisan('honey:process-finance')->assertSuccessful();
        $this->assertSame('completed', $order->fresh()->status);
        $balance = app(PartnerLedger::class)->balances($this->partner->id);
        $notifications = Notification::sent(User::findOrFail($order->customer->user_id), CommerceUpdate::class)->count();
        $this->assertGreaterThan(0, $notifications);
        $this->artisan('honey:process-finance')->assertSuccessful();
        $this->assertSame($notifications, Notification::sent(User::findOrFail($order->customer->user_id), CommerceUpdate::class)->count());
        $this->assertSame($balance, app(PartnerLedger::class)->balances($this->partner->id));
        $this->assertDatabaseCount('commission_entries', 2);
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }
}
