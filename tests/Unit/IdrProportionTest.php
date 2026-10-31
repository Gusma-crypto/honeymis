<?php

namespace Tests\Unit;

use App\Domains\Shared\ValueObjects\Idr;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdrProportionTest extends TestCase
{
    #[DataProvider('proportions')]
    public function test_prorates_idr_without_float_or_intermediate_overflow(int $amount, int $part, int $whole, int $expected): void
    {
        $this->assertSame($expected, (new Idr($amount))->proportion($part, $whole)->amount);
    }

    public static function proportions(): array
    {
        return [[10000, 66667, 100000, 6666], [10000, 1, 2, 5000], [10000, 0, 100000, 0], [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX], [PHP_INT_MAX, 1, PHP_INT_MAX, 1], [PHP_INT_MAX, PHP_INT_MAX - 1, PHP_INT_MAX, PHP_INT_MAX - 1], [PHP_INT_MAX, 1, 2, 4611686018427387903]];
    }

    public function test_rejects_refund_ratios_outside_the_original_basis(): void
    {
        $this->expectException(DomainException::class);
        (new Idr(10000))->proportion(101, 100);
    }
}
