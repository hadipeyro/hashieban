<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Domain\Money\Money;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Tests\TestCase;

final class ProfitEngineTest extends TestCase
{
    public function testCalculatesStoreNetProfitAndMargin(): void
    {
        $engine = new ProfitEngine();
        $result = $engine->calculateStore(
            new Money(1000000, 'IRR', 0),
            new Money(550000, 'IRR', 0),
            new Money(50000, 'IRR', 0),
            new Money(25000, 'IRR', 0),
            new Money(75000, 'IRR', 0)
        );

        $this->assertSame(300000, $result->profit()->minorAmount());
        $this->assertFloatEquals(30.0, $result->marginPercentage());
        $this->assertTrue($result->isProfit());
        $this->assertTrue($result->completeness()->isComplete());
        $this->assertSame(700000, $result->breakdown()->totalExpenses()->minorAmount());
    }

    public function testZeroRevenueHasNoMargin(): void
    {
        $engine = new ProfitEngine();
        $zero = Money::zero('IRR', 0);
        $result = $engine->calculateStore($zero, $zero, $zero, $zero, $zero);

        $this->assertNull($result->marginPercentage());
        $this->assertTrue($result->isBreakEven());
    }

    public function testIncompleteOrdersMarkResultIncomplete(): void
    {
        $engine = new ProfitEngine();
        $zero = Money::zero('IRR', 0);
        $result = $engine->calculateStore($zero, $zero, $zero, $zero, $zero, 2);

        $this->assertFalse($result->completeness()->isComplete());
        $this->assertSame(1, count($result->completeness()->missingData()));
    }
}
