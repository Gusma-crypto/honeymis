<?php

namespace Tests\Feature;

use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Actions\ManagePayout;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FinanceTestCase;

class ReportsSecurityTest extends FinanceTestCase
{
    public function test_partner_wallet_and_payout_pages_only_contain_own_data(): void
    {
        $this->financeActors();
        $this->commissionOrder(true);
        $payout = app(ManagePayout::class)->request($this->partner->user, $this->partner, 5000, $this->bank(), (string) Str::uuid());
        $other = Partner::factory()->active()->create();
        $other->user->assignRole('partner');
        $this->actingAs($other->user)->get(route('wallet.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('partner/wallet')->where('balances.available', 0)->has('entries.data', 0));
        $this->get(route('payouts.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('payouts.data', 0));
        $this->get(route('payouts.proof', $payout))->assertForbidden();
    }

    public function test_store_reports_exclude_financial_details_and_deny_financial_csv(): void
    {
        $this->financeActors();
        $this->commissionOrder();
        $this->actingAs($this->store)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->get(route('admin.reports.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->missing('report.finance')->where('financialAccess', false));
        $query = ['type' => 'payouts', 'from' => now()->subMonth()->format('Y-m-d'), 'until' => now()->format('Y-m-d')];
        $this->get(route('admin.reports.export', $query))->assertForbidden();
        $this->actingAs($this->finance)->get(route('admin.reports.export', $query))->assertOk()->assertDownload('honeycommerce-payouts.csv');
    }

    public function test_report_export_escapes_spreadsheet_formulas(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $order->update(['order_number' => '=HYPERLINK(1)']);
        $response = $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->get(route('admin.reports.export', ['type' => 'orders', 'from' => now('Asia/Jakarta')->subDay()->format('Y-m-d'), 'until' => now('Asia/Jakarta')->format('Y-m-d')]));
        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK(1)", $response->streamedContent());
    }

    public function test_all_finance_pages_render_for_authorized_staff(): void
    {
        $this->financeActors();
        $this->actingAs($this->finance)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);
        foreach (['admin.commission-rules.index', 'admin.payouts.index', 'admin.refunds.index', 'admin.reports.index', 'admin.audit.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->actingAs($this->store)->get(route('admin.partners.index'))->assertOk();
    }

    public function test_public_information_pages_work_without_authentication(): void
    {
        $this->financeActors();
        foreach (['about', 'contact', 'faq', 'privacy', 'terms', 'returns'] as $page) {
            $this->get(route('info.'.$page))->assertOk();
        }
    }

    public function test_customer_information_is_restricted_to_store_operations(): void
    {
        $this->financeActors();
        $order = $this->commissionOrder();
        $this->actingAs($this->partner->user)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->get(route('admin.customers.show', $order->customer_id))->assertForbidden();
        $this->actingAs($this->store)->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.customers.show', $order->customer_id))->assertOk();
        $this->get(route('admin.commerce-settings'))->assertOk();
    }
}
