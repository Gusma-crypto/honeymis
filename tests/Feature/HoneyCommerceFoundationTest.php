<?php

namespace Tests\Feature;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Shared\Services\TransitionRecorder;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * @phpstan-type FinancialFixture array{user: int, partner: int, rule: int, commission: int}
 * @phpstan-type DatabaseChanges array{on_hand?: int, reserved?: int, grand_total?: int, discount_total?: int, currency?: string, status?: string, level?: int, strategy?: string, rate_bps?: int, amount?: int}
 */
class HoneyCommerceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_audit_and_outbox_are_atomic_and_retry_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $orderId = $this->createOrder($actor->id);
        $recorder = new TransitionRecorder;

        $recorder->record($orderId, OrderStatus::Cancelled, $actor->id, 'cancel-order-1');
        $recorder->record($orderId, OrderStatus::Cancelled, $actor->id, 'cancel-order-1');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled']);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('outbox_messages', ['event_key' => 'cancel-order-1', 'event_type' => 'orders.state_changed']);
    }

    public function test_reusing_an_idempotency_key_for_another_target_is_rejected(): void
    {
        $actor = User::factory()->create();
        $orderId = $this->createOrder($actor->id);
        $recorder = new TransitionRecorder;
        $recorder->record($orderId, OrderStatus::Cancelled, $actor->id, 'cancel-order-1');
        $this->expectException(DomainException::class);

        $recorder->record($orderId, OrderStatus::Paid, $actor->id, 'cancel-order-1');
    }

    public function test_invalid_transition_writes_no_audit_or_outbox(): void
    {
        $actor = User::factory()->create();
        $orderId = $this->createOrder($actor->id);

        try {
            (new TransitionRecorder)->record($orderId, OrderStatus::Shipped, $actor->id, 'invalid-ship');
            $this->fail('Unpaid orders cannot be shipped.');
        } catch (DomainException) {
            $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending_payment']);
            $this->assertDatabaseCount('audit_logs', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
        }
    }

    public function test_audit_failure_rolls_back_state_change(): void
    {
        $actor = User::factory()->create();
        $orderId = $this->createOrder($actor->id);

        try {
            (new TransitionRecorder)->record($orderId, OrderStatus::Cancelled, 99999999, 'missing-actor');
            $this->fail('Actor FK must reject missing user.');
        } catch (QueryException) {
            $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending_payment']);
            $this->assertDatabaseCount('audit_logs', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
        }
    }

    public function test_order_number_is_unique(): void
    {
        $actor = User::factory()->create();
        $this->createOrder($actor->id);
        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'order_number' => 'HC-TEST-1', 'idempotency_key' => 'another-request',
            'customer_id' => DB::table('customers')->value('id'),
            'shipping_address_snapshot' => '{}', 'subtotal' => 150000, 'grand_total' => 150000,
            'placed_at' => now(),
        ]);
    }

    public function test_missing_variant_foreign_key_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('inventory_balances')->insert(['variant_id' => 99999999, 'on_hand' => 10, 'reserved' => 0]);
    }

    /** @return iterable<string, array{string, DatabaseChanges}> */
    public static function invalidDatabaseValues(): iterable
    {
        yield 'negative stock' => ['inventory_balances', ['on_hand' => -1]];
        yield 'reservation exceeds stock' => ['inventory_balances', ['reserved' => 11]];
        yield 'incorrect order total' => ['orders', ['grand_total' => 1]];
        yield 'negative order discount' => ['orders', ['discount_total' => -1]];
        yield 'non IDR order' => ['orders', ['currency' => 'USD']];
        yield 'invalid order status' => ['orders', ['status' => 'magic']];
        yield 'multilevel rule' => ['commission_rules', ['level' => 2]];
        yield 'recruitment rule' => ['commission_rules', ['strategy' => 'recruitment']];
        yield 'commission over 100 percent' => ['commission_rules', ['rate_bps' => 10001]];
        yield 'multilevel commission' => ['commission_entries', ['level' => 2]];
        yield 'commission exceeds merchandise' => ['commission_entries', ['amount' => 200000]];
        yield 'negative payout' => ['payout_requests', ['amount' => -1]];
        yield 'paid without approval or proof' => ['payout_requests', ['status' => 'paid']];
    }

    /** @param DatabaseChanges $changes */
    #[DataProvider('invalidDatabaseValues')]
    public function test_database_rejects_invalid_financial_and_stock_rows(string $table, array $changes): void
    {
        $this->requireProductionDatabase();
        $this->createFinancialFixture();
        $this->expectException(QueryException::class);

        DB::table($table)->update($changes);
    }

    public function test_commission_recipient_rule_combination_is_unique(): void
    {
        $fixture = $this->createFinancialFixture();
        $existingCommission = (array) DB::table('commission_entries')->where('id', $fixture['commission'])->first();
        unset($existingCommission['id']);
        $existingCommission['unique_key'] = 'different-key-same-entitlement';
        $this->expectException(QueryException::class);

        DB::table('commission_entries')->insert($existingCommission);
    }

    public function test_ledger_idempotency_key_is_unique(): void
    {
        $fixture = $this->createFinancialFixture();
        $entry = $this->ledgerEntry($fixture);
        DB::table('wallet_ledger_entries')->insert($entry);
        $this->expectException(QueryException::class);

        DB::table('wallet_ledger_entries')->insert($entry);
    }

    public function test_database_ledger_is_append_only(): void
    {
        $this->requireProductionDatabase();
        $fixture = $this->createFinancialFixture();
        DB::table('wallet_ledger_entries')->insert($this->ledgerEntry($fixture));
        $this->expectException(QueryException::class);

        DB::table('wallet_ledger_entries')->update(['amount_signed' => 999999]);
    }

    public function test_database_audit_history_cannot_be_deleted(): void
    {
        $this->requireProductionDatabase();
        $actor = User::factory()->create();
        $orderId = $this->createOrder($actor->id);
        (new TransitionRecorder)->record($orderId, OrderStatus::Cancelled, $actor->id, 'cancel-order-1');
        $this->expectException(QueryException::class);

        DB::table('audit_logs')->delete();
    }

    public function test_database_published_rule_is_immutable(): void
    {
        $this->requireProductionDatabase();
        $fixture = $this->createFinancialFixture();
        DB::table('commission_rules')->where('id', $fixture['rule'])->update([
            'status' => 'published', 'approved_by' => $fixture['user'], 'approved_at' => now(),
        ]);
        $this->expectException(QueryException::class);

        DB::table('commission_rules')->where('id', $fixture['rule'])->update(['rate_bps' => 2000]);
    }

    public function test_database_approved_sponsor_is_immutable(): void
    {
        $this->requireProductionDatabase();
        $fixture = $this->createFinancialFixture();
        $otherPartnerUser = User::factory()->create();
        $sponsor = DB::table('partners')->insertGetId(['user_id' => $otherPartnerUser->id, 'referral_code' => 'SPONSOR']);
        $this->expectException(QueryException::class);

        DB::table('partners')->where('id', $fixture['partner'])->update(['sponsor_partner_id' => $sponsor]);
    }

    public function test_database_ledger_cannot_reference_another_partners_commission(): void
    {
        $this->requireProductionDatabase();
        $fixture = $this->createFinancialFixture();
        $otherPartnerUser = User::factory()->create();
        $partner = DB::table('partners')->insertGetId(['user_id' => $otherPartnerUser->id, 'referral_code' => 'OTHER']);
        $entry = $this->ledgerEntry($fixture);
        $entry['partner_id'] = $partner;
        $this->expectException(QueryException::class);

        DB::table('wallet_ledger_entries')->insert($entry);
    }

    public function test_database_rejects_a_second_verified_payment_for_an_order(): void
    {
        $this->requireProductionDatabase();
        $actor = User::factory()->create();
        $orderId = $this->createOrder($actor->id);
        $payment = [
            'order_id' => $orderId, 'provider' => 'manual', 'amount' => 150000,
            'status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now(),
        ];
        DB::table('payments')->insert($payment + ['idempotency_key' => 'payment-1']);
        $this->expectException(QueryException::class);

        DB::table('payments')->insert($payment + ['idempotency_key' => 'payment-2']);
    }

    private function requireProductionDatabase(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->markTestSkipped('MySQL/MariaDB or PostgreSQL is required for production CHECK constraints and triggers.');
        }
    }

    private function createOrder(int $userId): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'user_id' => $userId, 'name' => 'Test Customer', 'email' => 'customer@example.test', 'phone' => '0800000000',
        ]);

        return DB::table('orders')->insertGetId([
            'customer_id' => $customerId, 'order_number' => 'HC-TEST-1', 'idempotency_key' => 'checkout-1',
            'shipping_address_snapshot' => '{}', 'subtotal' => 150000, 'grand_total' => 150000,
            'placed_at' => now(),
        ]);
    }

    /** @return FinancialFixture */
    private function createFinancialFixture(): array
    {
        $user = User::factory()->create();
        $orderId = $this->createOrder($user->id);
        $category = DB::table('categories')->insertGetId(['name' => 'Honey', 'slug' => 'honey']);
        $product = DB::table('products')->insertGetId([
            'category_id' => $category, 'sku' => 'HONEY', 'name' => 'Honey', 'slug' => 'honey', 'weight_g' => 500,
        ]);
        $variant = DB::table('product_variants')->insertGetId([
            'product_id' => $product, 'sku' => 'HONEY-500', 'attributes' => '{}', 'price' => 150000, 'cost' => 80000,
        ]);
        DB::table('inventory_balances')->insert(['variant_id' => $variant, 'on_hand' => 10, 'reserved' => 0]);
        $item = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'variant_id' => $variant, 'sku_snapshot' => 'HONEY-500', 'name_snapshot' => 'Honey',
            'attributes_snapshot' => '{}', 'quantity' => 1, 'unit_price_snapshot' => 150000, 'cost_snapshot' => 80000,
        ]);
        $partnerUser = User::factory()->create();
        $partner = DB::table('partners')->insertGetId([
            'user_id' => $partnerUser->id, 'referral_code' => 'DIRECT1', 'status' => 'active', 'approved_at' => now(),
        ]);
        $rule = DB::table('commission_rules')->insertGetId([
            'version' => 1, 'rate_bps' => 1000, 'cap_amount' => 50000, 'valid_from' => now(),
        ]);
        $commission = DB::table('commission_entries')->insertGetId([
            'order_item_id' => $item, 'beneficiary_partner_id' => $partner, 'rule_id' => $rule,
            'base_amount' => 150000, 'amount' => 15000, 'unique_key' => 'item:partner:1:rule:1',
        ]);
        DB::table('payout_requests')->insert([
            'partner_id' => $partner, 'idempotency_key' => 'payout-1', 'amount' => 15000,
            'bank_account_encrypted' => 'test-ciphertext', 'requested_at' => now(),
        ]);

        return ['user' => $user->id, 'partner' => $partner, 'rule' => $rule, 'commission' => $commission];
    }

    /**
     * @param  FinancialFixture  $fixture
     * @return array{partner_id: int, commission_entry_id: int, transaction_key: string, idempotency_key: string, bucket: string, entry_type: string, amount_signed: int, created_at: CarbonInterface}
     */
    private function ledgerEntry(array $fixture): array
    {
        return [
            'partner_id' => $fixture['partner'], 'commission_entry_id' => $fixture['commission'],
            'transaction_key' => 'commission-1', 'idempotency_key' => 'commission-1:pending',
            'bucket' => 'pending', 'entry_type' => 'commission', 'amount_signed' => 15000, 'created_at' => now(),
        ];
    }
}
