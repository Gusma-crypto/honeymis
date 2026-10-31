<?php

namespace Tests\Unit;

use App\Domains\Catalog\Services\Purchasability;
use App\Domains\Commissions\Enums\CommissionStatus;
use App\Domains\Commissions\Services\DirectCommissionCalculator;
use App\Domains\Commissions\ValueObjects\RuleVersion;
use App\Domains\Inventory\Services\StockAvailability;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Services\ManualPaymentAdapter;
use App\Domains\Orders\Services\OrderTotals;
use App\Domains\Partners\Services\ReferralEligibility;
use App\Domains\Payouts\Enums\PayoutStatus;
use App\Domains\Payouts\Services\PayoutEligibility;
use App\Domains\Reports\Services\DateRange;
use App\Domains\Shared\Contracts\TransitionState;
use App\Domains\Shared\ValueObjects\Idr;
use App\Domains\Wallet\Services\BalanceCalculator;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HoneyCommerceRulesTest extends TestCase
{
    public function test_totals_use_integer_idr_and_commission_excludes_shipping_and_tax(): void
    {
        $total = (new OrderTotals)->calculate(new Idr(150000), new Idr(10000), new Idr(20000), new Idr(5000));
        $net = (new Idr(150000))->subtract(new Idr(10000));
        $rule = new RuleVersion(1, 1000, 0, 50000, new DateTimeImmutable('2026-01-01'));
        $commission = (new DirectCommissionCalculator)->calculate($net, $rule, new DateTimeImmutable('2026-10-26'));

        $this->assertSame(165000, $total->amount);
        $this->assertSame(14000, $commission->amount);
        $this->assertSame(33, (new Idr(101))->basisPoints(3333)->amount);
        $this->assertSame(PHP_INT_MAX, (new Idr(PHP_INT_MAX))->basisPoints(10000)->amount);
    }

    public function test_fixed_commission_never_exceeds_net_merchandise_or_cap(): void
    {
        $rule = new RuleVersion(2, 0, 30000, 20000, new DateTimeImmutable('2026-01-01'));
        $calculator = new DirectCommissionCalculator;

        $this->assertSame(20000, $calculator->calculate(new Idr(100000), $rule, new DateTimeImmutable('2026-10-26'))->amount);
        $this->assertSame(5000, $calculator->calculate(new Idr(5000), $rule, new DateTimeImmutable('2026-10-26'))->amount);
        $this->assertSame(0, $calculator->calculate(new Idr(0), $rule, new DateTimeImmutable('2026-10-26'))->amount);
    }

    /** @return iterable<string, array{int, int, int, int, int, string}> */
    public static function invalidRules(): iterable
    {
        yield 'multilevel denied' => [1, 1000, 0, 20000, 2, 'direct_v1'];
        yield 'unknown strategy denied' => [1, 1000, 0, 20000, 1, 'recruitment'];
        yield 'missing version' => [0, 1000, 0, 20000, 1, 'direct_v1'];
        yield 'over 100 percent' => [1, 10001, 0, 20000, 1, 'direct_v1'];
        yield 'negative rate' => [1, -1, 0, 20000, 1, 'direct_v1'];
        yield 'negative fixed' => [1, 0, -1, 20000, 1, 'direct_v1'];
        yield 'negative cap' => [1, 1000, 0, -1, 1, 'direct_v1'];
        yield 'ambiguous rate and fixed' => [1, 1000, 5000, 20000, 1, 'direct_v1'];
    }

    #[DataProvider('invalidRules')]
    public function test_invalid_or_multilevel_rule_is_rejected(int $version, int $rate, int $fixed, int $cap, int $level, string $strategy): void
    {
        $this->expectException(DomainException::class);

        new RuleVersion($version, $rate, $fixed, $cap, new DateTimeImmutable('2026-01-01'), level: $level, strategy: $strategy);
    }

    public function test_rule_effective_window_uses_placement_date_and_excludes_expiry(): void
    {
        $rule = new RuleVersion(1, 1000, 0, 50000, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'));

        $this->assertTrue($rule->effectiveAt(new DateTimeImmutable('2026-01-01')));
        $this->assertFalse($rule->effectiveAt(new DateTimeImmutable('2025-12-31')));
        $this->expectException(DomainException::class);

        (new DirectCommissionCalculator)->calculate(new Idr(150000), $rule, new DateTimeImmutable('2026-02-01'));
    }

    public function test_invalid_rule_window_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        new RuleVersion(1, 1000, 0, 50000, new DateTimeImmutable('2026-02-01'), new DateTimeImmutable('2026-01-01'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMoneyOperations(): iterable
    {
        foreach (['negative', 'add_overflow', 'multiply_overflow', 'negative_quantity', 'over_discount', 'invalid_rate'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('invalidMoneyOperations')]
    public function test_invalid_money_operation_is_rejected(string $operation): void
    {
        $this->expectException(DomainException::class);

        match ($operation) {
            'negative' => new Idr(-1),
            'add_overflow' => (new Idr(PHP_INT_MAX))->add(new Idr(1)),
            'multiply_overflow' => (new Idr(PHP_INT_MAX))->multiply(2),
            'negative_quantity' => (new Idr(1))->multiply(-1),
            'over_discount' => (new Idr(1))->subtract(new Idr(2)),
            'invalid_rate' => (new Idr(1))->basisPoints(10001),
        };
    }

    /** @return iterable<string, array{TransitionState, TransitionState, bool}> */
    public static function transitions(): iterable
    {
        yield 'payment before fulfillment' => [OrderStatus::PendingPayment, OrderStatus::Paid, true];
        yield 'cannot ship unpaid' => [OrderStatus::PendingPayment, OrderStatus::Shipped, false];
        yield 'delivered can refund' => [OrderStatus::Delivered, OrderStatus::Refunded, true];
        yield 'cancelled is terminal' => [OrderStatus::Cancelled, OrderStatus::Paid, false];
        yield 'proof required before verification' => [PaymentStatus::Pending, PaymentStatus::Verified, false];
        yield 'submitted can verify' => [PaymentStatus::Submitted, PaymentStatus::Verified, true];
        yield 'no double verification' => [PaymentStatus::Verified, PaymentStatus::Verified, false];
        yield 'pending can cancel' => [CommissionStatus::Pending, CommissionStatus::Cancelled, true];
        yield 'no payout from pending' => [CommissionStatus::Pending, CommissionStatus::PaidOut, false];
        yield 'reserve before payout' => [CommissionStatus::Available, CommissionStatus::Reserved, true];
        yield 'failed payout release' => [CommissionStatus::Reserved, CommissionStatus::Available, true];
        yield 'post payout refund' => [CommissionStatus::PaidOut, CommissionStatus::Reversed, true];
        yield 'approval before payout' => [PayoutStatus::Requested, PayoutStatus::Paid, false];
        yield 'approved can pay' => [PayoutStatus::Approved, PayoutStatus::Paid, true];
        yield 'no duplicate payout' => [PayoutStatus::Paid, PayoutStatus::Paid, false];
        yield 'incompatible aggregate' => [OrderStatus::Paid, PaymentStatus::Verified, false];
    }

    #[DataProvider('transitions')]
    public function test_state_machine_accepts_only_valid_transitions(TransitionState $from, TransitionState $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function invalidStock(): iterable
    {
        yield 'oversold' => [10, 9, 2];
        yield 'zero quantity' => [10, 0, 0];
        yield 'invalid balance' => [10, 11, 1];
        yield 'negative balance' => [-1, 0, 1];
        yield 'negative reservation' => [10, -1, 1];
    }

    #[DataProvider('invalidStock')]
    public function test_invalid_stock_reservation_is_rejected(int $onHand, int $reserved, int $requested): void
    {
        $this->expectException(DomainException::class);

        (new StockAvailability)->ensureReservable($onHand, $reserved, $requested);
    }

    public function test_stock_reservation_allows_exact_remaining_quantity(): void
    {
        (new StockAvailability)->ensureReservable(10, 8, 2);

        $this->expectNotToPerformAssertions();
    }

    public function test_referral_requires_active_other_user_and_unexpired_attribution(): void
    {
        $eligibility = new ReferralEligibility;
        $now = new DateTimeImmutable('2026-10-26');
        $expires = new DateTimeImmutable('2026-11-26');

        $this->assertTrue($eligibility->eligible(1, 2, 'active', $expires, $now));
        $this->assertFalse($eligibility->eligible(1, 1, 'active', $expires, $now));
        $this->assertFalse($eligibility->eligible(1, 2, 'suspended', $expires, $now));
        $this->assertFalse($eligibility->eligible(1, 2, 'active', $now, $now));
    }

    /** @return iterable<string, array{int, int, int, bool}> */
    public static function invalidPayouts(): iterable
    {
        yield 'insufficient available' => [10001, 10000, 1000, true];
        yield 'below minimum' => [999, 10000, 1000, true];
        yield 'zero request' => [0, 10000, 0, true];
        yield 'suspended partner' => [1000, 10000, 1000, false];
        yield 'debt outstanding' => [1000, -1000, 1000, true];
    }

    #[DataProvider('invalidPayouts')]
    public function test_invalid_payout_is_rejected(int $amount, int $available, int $minimum, bool $active): void
    {
        $this->expectException(DomainException::class);

        (new PayoutEligibility)->ensureRequestable(new Idr($amount), $available, new Idr($minimum), $active);
    }

    public function test_wallet_balance_comes_from_signed_ledger_entries(): void
    {
        $this->assertSame(7000, (new BalanceCalculator)->sum([15000, -10000, 2000]));
        (new PayoutEligibility)->ensureRequestable(new Idr(7000), 7000, new Idr(1000), true);
    }

    public function test_ledger_sum_rejects_overflow(): void
    {
        $this->expectException(DomainException::class);

        (new BalanceCalculator)->sum([PHP_INT_MAX, 1]);
    }

    public function test_inactive_product_is_unpurchasable(): void
    {
        $this->expectException(DomainException::class);

        (new Purchasability)->ensureActive('active', 'inactive', 'active');
    }

    public function test_manual_payment_never_auto_verifies_payment(): void
    {
        $adapter = new ManualPaymentAdapter;

        $this->assertSame('manual', $adapter->provider());
        $this->assertSame(PaymentStatus::Pending, $adapter->initialStatus(new Idr(10000)));
    }

    public function test_manual_payment_rejects_zero_amount(): void
    {
        $this->expectException(DomainException::class);

        (new ManualPaymentAdapter)->initialStatus(new Idr(0));
    }

    public function test_report_rejects_reversed_date_range(): void
    {
        $this->expectException(DomainException::class);

        new DateRange(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-10-01'));
    }
}
