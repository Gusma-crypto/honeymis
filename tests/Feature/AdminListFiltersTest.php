<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Orders\Models\Customer;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Wallet\Models\WalletLedgerEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        return $admin;
    }

    /** @return array<string, array{string, string, string}> */
    public static function lists(): array
    {
        return [
            'catalog' => ['admin.catalog.index', 'products', 'active'],
            'inventory' => ['admin.inventory.index', 'variants', 'active'],
            'partners' => ['admin.partners.index', 'partners', 'active'],
            'orders' => ['admin.orders.index', 'orders', 'paid'],
            'payouts' => ['admin.payouts.index', 'payouts', 'requested'],
            'refunds' => ['admin.refunds.index', 'refunds', 'requested'],
            'rules' => ['admin.commission-rules.index', 'rules', 'draft'],
            'users' => ['admin.users.index', 'users', 'active'],
            'customers' => ['admin.customers.index', 'customers', 'linked'],
            'audit' => ['admin.audit.index', 'logs', 'users'],
        ];
    }

    #[DataProvider('lists')]
    public function test_each_administrator_list_accepts_and_preserves_filters(string $route, string $prop, string $status): void
    {
        $this->administrator();

        $this->get(route($route, ['search' => 'No matching record 739125', 'status' => $status]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('listFilters.search', 'No matching record 739125')
            ->where('listFilters.status', $status)->has($prop.'.data', 0));
    }

    #[DataProvider('lists')]
    public function test_unknown_status_is_rejected_in_each_list(string $route, string $prop, string $status): void
    {
        $this->administrator();

        $this->getJson(route($route, ['status' => 'not-a-valid-status']))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_product_search_covers_all_pages_and_combines_status_and_variant_sku(): void
    {
        $this->administrator();
        Product::factory()->count(18)->create(['name' => 'Azure honey']);
        Product::factory()->inactive()->create(['name' => 'Azure honey']);
        Product::factory()->count(16)->create(['name' => 'Unrelated product']);
        $variant = ProductVariant::factory()->create(['sku' => 'AZURE-SPECIAL']);

        $this->get(route('admin.catalog.index', ['search' => 'Azure', 'status' => 'active']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 19)->has('products.data', 8)
            ->where('products.next_page_url', fn (?string $url): bool => $url !== null && str_contains($url, 'search=Azure') && str_contains($url, 'status=active')));
        $this->get(route('admin.catalog.index', ['search' => 'AZURE-SPECIAL', 'status' => 'active']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $variant->product_id));
    }

    public function test_partner_relation_search_does_not_bypass_status_filter(): void
    {
        $this->administrator();
        $match = Partner::factory()->active()->create(['user_id' => User::factory()->create(['name' => 'Azure Partner'])->id]);
        Partner::factory()->create(['user_id' => User::factory()->create(['name' => 'Azure Partner'])->id]);
        Partner::factory()->active()->create();

        $this->get(route('admin.partners.index', ['search' => 'Azure Partner', 'status' => 'active']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('partners.data', 1)->where('partners.data.0.id', $match->id));
    }

    public function test_search_treats_wildcards_literally_and_validates_input_shape(): void
    {
        $this->administrator();
        $match = Product::factory()->create(['name' => 'Honey 100% Pure']);
        Product::factory()->create(['name' => 'Honey 1000 Pure']);

        $this->get(route('admin.catalog.index', ['search' => '100%']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $match->id));
        $this->getJson(route('admin.users.index', ['search' => ['unexpected']]))->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson(route('admin.users.index', ['search' => str_repeat('a', 101)]))->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_order_filters_cannot_expose_another_customers_order(): void
    {
        $user = User::factory()->create();
        $own = Order::factory()->create(['customer_id' => Customer::factory()->create(['user_id' => $user->id])->id, 'order_number' => 'AZURE-OWN']);
        Order::factory()->create(['order_number' => 'AZURE-OTHER']);

        $this->actingAs($user)->get(route('orders.index', ['search' => 'AZURE', 'status' => 'pending_payment']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $own->id));
    }

    public function test_partner_wallet_and_payout_filters_keep_owner_scope_and_balance_totals(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $partner = Partner::factory()->active()->create();
        $partner->user->assignRole('partner');
        $other = Partner::factory()->active()->create();
        $own = PayoutRequest::factory()->create(['partner_id' => $partner->id]);
        PayoutRequest::factory()->create(['partner_id' => $other->id]);
        WalletLedgerEntry::factory()->create(['partner_id' => $partner->id, 'commission_entry_id' => CommissionEntry::factory()->create(['beneficiary_partner_id' => $partner->id, 'amount' => 1000])->id, 'entry_type' => 'commission', 'amount_signed' => 1000]);
        WalletLedgerEntry::factory()->create(['partner_id' => $other->id, 'commission_entry_id' => CommissionEntry::factory()->create(['beneficiary_partner_id' => $other->id, 'amount' => 9000])->id, 'entry_type' => 'commission', 'amount_signed' => 9000]);

        $this->actingAs($partner->user)->get(route('payouts.index', ['status' => 'requested']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('payouts.data', 1)->where('payouts.data.0.id', $own->id));
        $this->get(route('wallet.index', ['search' => 'commission', 'status' => 'pending']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('balances.pending', 1000));
        $this->get(route('wallet.index', ['search' => 'does-not-exist']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('entries.data', 0)->where('balances.pending', 1000));
    }

    public function test_contact_filter_distinguishes_linked_accounts(): void
    {
        $this->administrator();
        Customer::factory()->create(['name' => 'Azure contact', 'user_id' => User::factory()->create()->id]);
        $guest = Customer::factory()->create(['name' => 'Azure contact', 'user_id' => null]);

        $this->get(route('admin.customers.index', ['search' => 'Azure', 'status' => 'guest']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.id', $guest->id));
    }

    public function test_inventory_payout_users_rules_refunds_and_audit_match_real_records(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create(['name' => 'Azure Operator']);
        $user->assignRole('finance');
        $partner = Partner::factory()->active()->create(['user_id' => $user->id]);
        $payout = PayoutRequest::factory()->create(['partner_id' => $partner->id]);
        $variant = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['name' => 'Azure inventory'])->id]);
        $rule = CommissionRule::factory()->create(['version' => 112233]);
        $refund = Refund::factory()->create(['reason' => 'Azure refund']);
        app(AuditWriter::class)->record($admin->id, 'azure_checked', 'users', $user->id, 'azure-check');
        app(AuditWriter::class)->record($admin->id, 'azure_checked', 'orders', 999, 'azure-other-check');
        app(AuditWriter::class)->record($admin->id, 'commission_checked', 'commission_entries', 999, 'azure-commission-check');

        $cases = [
            ['admin.inventory.index', 'variants', 'Azure inventory', 'active', $variant->id],
            ['admin.payouts.index', 'payouts', 'Azure Operator', 'requested', $payout->id],
            ['admin.users.index', 'users', 'finance', 'active', $user->id],
            ['admin.commission-rules.index', 'rules', '112233', 'draft', $rule->id],
            ['admin.refunds.index', 'refunds', 'Azure refund', 'requested', $refund->id],
        ];
        foreach ($cases as [$route, $prop, $search, $status, $id]) {
            $this->get(route($route, ['search' => $search, 'status' => $status]))->assertOk()->assertInertia(fn (Assert $page) => $page->has($prop.'.data', 1)->where($prop.'.data.0.id', $id));
        }
        $this->get(route('admin.audit.index', ['search' => 'azure', 'status' => 'users', 'action' => 'azure_checked']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.subject_type', 'users'));
        $this->get(route('admin.audit.index', ['status' => 'commission_entries']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.subject_type', 'commission_entries'));
    }
}
