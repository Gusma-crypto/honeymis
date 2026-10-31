<?php

namespace Tests\Feature;

use App\Domains\Payouts\Actions\ManagePayout;
use App\Domains\Wallet\Services\PartnerLedger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\FinanceTestCase;

class WalletPayoutTest extends FinanceTestCase
{
    public function test_reservation_approval_manual_payment_and_retry_keep_balances_consistent(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $service = app(ManagePayout::class);
        $key = (string) Str::uuid();
        $payout = $service->request($this->partner->user, $this->partner, 6000, $this->bank(), $key);
        $this->assertSame($payout->id, $service->request($this->partner->user, $this->partner, 6000, $this->bank(), $key)->id);
        $service->decide($this->finance, $payout, 'approve', (string) Str::uuid());
        $proof = UploadedFile::fake()->create('transfer.pdf', 10, 'application/pdf');
        $paidKey = (string) Str::uuid();
        $service->decide($this->finance, $payout, 'paid', $paidKey, reference: 'TEST-REF-001', proof: $proof, confirmed: true);
        $service->decide($this->finance, $payout, 'paid', $paidKey, reference: 'TEST-REF-001', proof: $proof, confirmed: true);
        $this->assertSame(['pending' => 0, 'available' => 4000, 'reserved' => 0, 'paid' => 6000, 'receivable' => 0], app(PartnerLedger::class)->balances($this->partner->id));
        $this->assertStringNotContainsString('1234567890', $payout->getRawOriginal('bank_account_encrypted'));
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_rejected_payout_restores_reservation_once(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $service = app(ManagePayout::class);
        $payout = $service->request($this->partner->user, $this->partner, 10000, $this->bank(), (string) Str::uuid());
        $key = (string) Str::uuid();
        $service->decide($this->finance, $payout, 'reject', $key, 'Bank tidak sesuai');
        $service->decide($this->finance, $payout, 'reject', $key, 'Bank tidak sesuai');
        $this->assertSame(10000, app(PartnerLedger::class)->withdrawable($this->partner->id));
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function test_cannot_reserve_more_than_unallocated_balance(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $service = app(ManagePayout::class);
        $service->request($this->partner->user, $this->partner, 8000, $this->bank(), (string) Str::uuid());
        $this->expectException(ValidationException::class);
        $service->request($this->partner->user, $this->partner, 3000, $this->bank(), (string) Str::uuid());
    }

    public function test_paid_requires_approval_proof_and_explicit_manual_confirmation(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $service = app(ManagePayout::class);
        $payout = $service->request($this->partner->user, $this->partner, 5000, $this->bank(), (string) Str::uuid());
        $service->decide($this->finance, $payout, 'approve', (string) Str::uuid());
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->post(route('admin.payouts.update', $payout), ['operation' => 'paid', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors(['proof', 'transfer_reference', 'confirmed_transfer']);
        $this->assertSame('approved', $payout->fresh()->status);
        $this->assertSame(5000, app(PartnerLedger::class)->balances($this->partner->id)['reserved']);
    }

    public function test_finance_cannot_review_own_payout_even_with_finance_role(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $service = app(ManagePayout::class);
        $payout = $service->request($this->partner->user, $this->partner, 5000, $this->bank(), (string) Str::uuid());
        $this->partner->user->assignRole('finance');
        $this->actingAs($this->partner->user)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->post(route('admin.payouts.update', $payout), ['operation' => 'approve', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->assertSame('requested', $payout->fresh()->status);
    }

    public function test_reconciliation_reports_unbalanced_payout_allocation(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $payout = app(ManagePayout::class)->request($this->partner->user, $this->partner, 5000, $this->bank(), (string) Str::uuid());
        DB::table('payout_allocations')->where('payout_id', $payout->id)->update(['amount' => 4000]);
        $this->artisan('honey:reconcile-finance')->assertFailed();
    }
}
