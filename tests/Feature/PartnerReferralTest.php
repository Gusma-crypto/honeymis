<?php

namespace Tests\Feature;

use App\Domains\Partners\Actions\ManagePartner;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\ReferralCapture;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\FinanceTestCase;

class PartnerReferralTest extends FinanceTestCase
{
    public function test_application_and_approval_create_no_recruitment_commission(): void
    {
        $this->financeActors();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('partner.apply'), ['sponsor_code' => $this->partner->referral_code, 'accept_terms' => true])->assertRedirect(route('partner.dashboard'));
        $partner = Partner::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $partner->status);
        $this->assertNull(app(ReferralCapture::class)->capture($partner->referral_code, null, null));
        $key = (string) Str::uuid();
        app(ManagePartner::class)->decide($this->store, $partner, 'active', $key);
        app(ManagePartner::class)->decide($this->store, $partner, 'active', $key);
        $this->assertTrue($user->fresh()->hasRole('partner'));
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    public function test_first_valid_referral_wins_until_it_expires_and_suspended_partner_is_invalid(): void
    {
        $this->financeActors();
        $other = Partner::factory()->active()->create();
        $capture = app(ReferralCapture::class);
        $first = $capture->capture($this->partner->referral_code, null, null);
        $this->assertSame($first, $capture->capture($other->referral_code, $first, null));
        $this->travel(31)->days();
        $next = $capture->capture($other->referral_code, $first, null);
        $this->assertNotSame($first, $next);
        $other->update(['status' => 'suspended']);
        $this->assertNull($capture->valid($next));
    }

    public function test_customer_cannot_approve_partner_or_read_finance_pages(): void
    {
        $this->financeActors();
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->patch(route('admin.partners.update', $this->partner), ['status' => 'active', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        foreach (['admin.commission-rules.index', 'admin.payouts.index', 'admin.refunds.index', 'admin.reports.index', 'admin.audit.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }
}
