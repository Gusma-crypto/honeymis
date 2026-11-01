<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class MarketingWorkspaceTest extends FinanceTestCase
{
    public function test_marketing_dashboard_has_real_empty_state_and_local_qr_and_demo_assets(): void
    {
        $this->financeActors();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.marketing.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/marketing')->where('stats.revenue', 0)->where('stats.registrations', 0)->where('stats.active_campaigns', 0)->where('referralUrl', null));
        $this->get(route('admin.marketing.qr', ['download' => 1]))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
        $this->get(route('admin.marketing.demo', 'flyer'))->assertOk()->assertSee('data:image/jpeg;base64,', false);
    }

    public function test_campaign_visits_keep_first_attribution_and_deduplicate_session(): void
    {
        $this->financeActors();
        $this->createCampaign('Kampanye Pertama');
        $first = DB::table('marketing_campaigns')->first();
        $this->createCampaign('Kampanye Kedua');
        $second = DB::table('marketing_campaigns')->orderByDesc('id')->first();
        auth()->logout();
        $this->get(route('marketing.visit', $first->code))->assertRedirect(route('catalog.index'));
        $visit = DB::table('marketing_visits')->where('campaign_id', $first->id)->first();
        $this->get(route('marketing.visit', $first->code))->assertRedirect();
        $this->assertDatabaseCount('marketing_visits', 1);
        $this->get(route('marketing.visit', $second->code))->assertRedirect()->assertSessionHas('honey_marketing_visit', (int) $visit->id);
        $this->assertDatabaseCount('marketing_visits', 2);
    }

    public function test_qualified_registration_and_paid_order_drive_campaign_metrics_without_recruitment_bonus(): void
    {
        $this->financeActors();
        $this->createCampaign('Penjualan Madu');
        $campaign = DB::table('marketing_campaigns')->first();
        auth()->logout();
        $this->get(route('marketing.visit', $campaign->code))->assertRedirect();
        $this->post(route('register.store'), ['name' => 'Pembeli Kampanye', 'email' => 'campaign@example.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect();
        $buyer = User::where('email', 'campaign@example.test')->firstOrFail();
        $this->assertDatabaseHas('marketing_visits', ['campaign_id' => $campaign->id, 'registered_user_id' => $buyer->id]);
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
        $buyer->forceFill(['email_verified_at' => now()])->save();
        $order = $this->commissionOrder(false, $buyer);
        $this->assertNotNull($order->getAttribute('marketing_visit_id'));
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.marketing.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('stats.registrations', 1)->where('stats.orders', 1)->where('stats.revenue', 100000)->where('stats.conversion_bps', 10000));
        $order->update(['fraud_hold' => true]);
        $this->get(route('admin.marketing.index'))->assertInertia(fn (Assert $page) => $page->where('stats.revenue', 0)->where('stats.orders', 0));
    }

    public function test_paused_campaign_cannot_collect_visits(): void
    {
        $this->financeActors();
        $this->createCampaign('Kampanye Dijeda');
        $campaign = DB::table('marketing_campaigns')->first();
        $this->put(route('admin.marketing.campaigns.update', $campaign->id), ['name' => 'Kampanye Dijeda', 'status' => 'paused', 'starts_at' => null, 'ends_at' => null])->assertSessionHasNoErrors()->assertRedirect();
        auth()->logout();
        $this->get(route('marketing.visit', $campaign->code))->assertNotFound();
        $this->assertDatabaseCount('marketing_visits', 0);
    }

    public function test_assets_are_private_and_broadcast_is_a_draft_not_a_send_operation(): void
    {
        Storage::fake('local');
        $this->financeActors();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.marketing.assets.store'), ['title' => 'Flyer Madu', 'file' => UploadedFile::fake()->createWithContent('madu.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC6kAAAAASUVORK5CYII='))])->assertSessionHasNoErrors()->assertRedirect();
        $asset = DB::table('marketing_assets')->first();
        Storage::disk('local')->assertExists($asset->storage_key);
        $this->get(route('admin.marketing.assets.show', $asset->id))->assertOk();
        $this->post(route('admin.marketing.broadcast'), ['title' => 'Promosi Madu', 'message' => 'Temukan madu pilihan pada katalog HoneyCommerce.', 'audience' => 'customers'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('marketing_broadcast_drafts', ['created_by' => $this->store->id, 'title' => 'Promosi Madu']);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->actingAs($this->partner->user)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.marketing.assets.show', $asset->id))->assertForbidden();
        $this->get(route('admin.marketing.index'))->assertForbidden();
        $this->post(route('admin.marketing.campaigns.store'), ['name' => 'Tidak Diizinkan', 'status' => 'active'])->assertForbidden();
    }

    private function createCampaign(string $name): void
    {
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.marketing.campaigns.store'), ['name' => $name, 'status' => 'active', 'starts_at' => null, 'ends_at' => null])->assertSessionHasNoErrors()->assertRedirect();
    }
}
