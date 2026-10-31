<?php

namespace Tests\Feature;

use App\Domains\Partners\Services\PartnerRankService;
use Tests\FinanceTestCase;

class PartnerRankOverrideTest extends FinanceTestCase
{
    public function test_override_persists_across_months_until_revoked_and_is_audited(): void
    {
        $this->financeActors();
        $this->finance->givePermissionTo('partners.manage');
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.partners.rank', $this->partner), ['rank' => 'Gold', 'reason' => 'Penetapan khusus disetujui admin.'])
            ->assertRedirect(route('admin.partners.index'));

        $this->travel(2)->months();
        $this->assertSame('Gold', app(PartnerRankService::class)->currentSummary($this->partner)['rank']);
        $this->assertDatabaseHas('partner_rank_overrides', ['partner_id' => $this->partner->id, 'rank' => 'Gold', 'revoked_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'partner_rank_overridden', 'actor_id' => $this->finance->id]);

        $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.partners.rank', $this->partner), ['rank' => null, 'reason' => 'Kembali ke evaluasi omzet bulanan.'])
            ->assertRedirect(route('admin.partners.index'));

        $this->assertSame('Member', app(PartnerRankService::class)->currentSummary($this->partner)['rank']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'partner_rank_override_revoked', 'actor_id' => $this->finance->id]);
    }

    public function test_override_requires_both_network_and_commission_permissions(): void
    {
        $this->financeActors();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.partners.rank', $this->partner), ['rank' => 'Gold', 'reason' => 'Percobaan tanpa otorisasi.'])
            ->assertForbidden();
        $this->assertDatabaseCount('partner_rank_overrides', 0);
    }

    public function test_invalid_rank_and_missing_reason_do_not_change_rank(): void
    {
        $this->financeActors();
        $this->finance->givePermissionTo('partners.manage');
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.partners.rank', $this->partner), ['rank' => 'Diamond', 'reason' => ''])
            ->assertSessionHasErrors(['rank', 'reason']);
        $this->assertDatabaseCount('partner_rank_overrides', 0);
    }

    public function test_revoke_without_active_override_is_rejected(): void
    {
        $this->financeActors();
        $this->finance->givePermissionTo('partners.manage');
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->put(route('admin.partners.rank', $this->partner), ['rank' => null, 'reason' => 'Mencabut override yang tidak ada.'])
            ->assertSessionHasErrors(['rank' => 'Tidak ada override aktif untuk dicabut.']);
        $this->assertDatabaseCount('partner_rank_overrides', 0);
    }
}
