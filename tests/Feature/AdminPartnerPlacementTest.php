<?php

namespace Tests\Feature;

use App\Domains\Partners\Models\Partner;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\FinanceTestCase;

class AdminPartnerPlacementTest extends FinanceTestCase
{
    #[TestWith(['below'])]
    #[TestWith(['sibling'])]
    #[TestWith(['root'])]
    public function test_admin_places_new_pending_partner_at_the_selected_position(string $placement): void
    {
        $this->financeActors();
        $relative = Partner::factory()->active()->create(['sponsor_partner_id' => $this->partner->id]);
        $member = User::factory()->create();
        $expectedSponsor = match ($placement) {
            'below' => $relative->id, 'sibling' => $this->partner->id, 'root' => null
        };

        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $member->email, 'placement' => $placement, 'relative_partner_id' => $placement === 'root' ? null : $relative->id])
            ->assertRedirect(route('admin.partners.index'))->assertSessionHasNoErrors();

        $partner = Partner::where('user_id', $member->id)->firstOrFail();
        $this->assertSame($expectedSponsor, $partner->sponsor_partner_id);
        $this->assertSame('pending', $partner->status);
        $this->assertNotEmpty($partner->referral_code);
        $this->assertFalse($member->fresh()->hasRole('partner'));
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->store->id, 'subject_id' => $partner->id, 'action' => 'partner_placed_by_admin']);
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    public function test_sibling_of_a_root_partner_is_also_a_root_partner(): void
    {
        $this->financeActors();
        $member = User::factory()->create();

        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $member->email, 'placement' => 'sibling', 'relative_partner_id' => $this->partner->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partners', ['user_id' => $member->id, 'sponsor_partner_id' => null]);
    }

    #[TestWith(['unverified'])]
    #[TestWith(['suspended'])]
    public function test_admin_cannot_place_an_ineligible_account(string $state): void
    {
        $this->financeActors();
        $member = $state === 'unverified' ? User::factory()->unverified()->create() : User::factory()->suspended()->create();

        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $member->email, 'placement' => 'root'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('partners', ['user_id' => $member->id]);
    }

    public function test_existing_partner_cannot_be_moved_or_duplicated(): void
    {
        $this->financeActors();
        $relative = Partner::factory()->active()->create();

        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $this->partner->user->email, 'placement' => 'below', 'relative_partner_id' => $relative->id])
            ->assertSessionHasErrors('email');

        $this->assertSame(null, $this->partner->fresh()->sponsor_partner_id);
        $this->assertSame(1, Partner::where('user_id', $this->partner->user_id)->count());
    }

    public function test_inactive_sponsor_is_rejected(): void
    {
        $this->financeActors();
        $member = User::factory()->create();
        $this->partner->update(['status' => 'suspended']);

        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $member->email, 'placement' => 'below', 'relative_partner_id' => $this->partner->id])
            ->assertSessionHasErrors('placement');

        $this->assertDatabaseMissing('partners', ['user_id' => $member->id]);
    }

    public function test_customer_cannot_place_members(): void
    {
        $this->financeActors();
        $customer = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($customer)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $member->email, 'placement' => 'root'])->assertForbidden();

        $this->assertDatabaseMissing('partners', ['user_id' => $member->id]);
    }

    public function test_below_placement_requires_a_valid_relative(): void
    {
        $this->financeActors();
        $member = User::factory()->create();

        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.partners.store'), ['email' => $member->email, 'placement' => 'below'])
            ->assertSessionHasErrors('relative_partner_id');

        $this->assertDatabaseMissing('partners', ['user_id' => $member->id]);
    }
}
