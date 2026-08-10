<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Domain\Money\Money;
use Hashieban\Tests\TestCase;
use InvalidArgumentException;
use LogicException;

final class MoneyTest extends TestCase
{
    public function testNormalizesCurrencyAndFormatsDecimal(): void
    {
        $money = new Money(12345, ' irr ', 2);

        $this->assertSame('IRR', $money->currency());
        $this->assertSame('123.45', $money->toDecimalString());
        $this->assertSame('-123.45', $money->negate()->toDecimalString());
    }

    public function testAddsAndSubtractsCompatibleMoney(): void
    {
        $a = new Money(1000, 'IRR', 0);
        $b = new Money(250, 'IRR', 0);

        $this->assertSame(1250, $a->add($b)->minorAmount());
        $this->assertSame(750, $a->subtract($b)->minorAmount());
        $this->assertTrue($a->isPositive());
        $this->assertTrue(Money::zero('IRR', 0)->isZero());
    }

    public function testRejectsDifferentCurrenciesAndPrecision(): void
    {
        $this->expectException(
            LogicException::class,
            static function (): void {
                (new Money(100, 'IRR', 0))->add(new Money(100, 'USD', 0));
            }
        );

        $this->expectException(
            LogicException::class,
            static function (): void {
                (new Money(100, 'IRR', 0))->add(new Money(100, 'IRR', 2));
            }
        );

        $this->expectException(
            InvalidArgumentException::class,
            static function (): void {
                new Money(100, '', 0);
            }
        );
    }
}
