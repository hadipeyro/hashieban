<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Domain\Money\Money;
use Hashieban\Integration\WooCommerce\Order\OrderFinancialData;
use Hashieban\Tests\TestCase;

final class OrderFinancialDataTest extends TestCase
{
    public function testRevenueAndProfitDoNotDoubleCountTax(): void
    {
        $financial = $this->makeFinancial(
            1000000,
            100000,
            50000,
            20000,
            130000,
            600000,
            50000
        );

        $this->assertSame(1000000, $financial->revenueBeforeDirectCosts()->minorAmount());
        $this->assertSame(350000, $financial->profitAfterDirectCosts()->minorAmount());
    }

    public function testRefundFlagsAndMissingData(): void
    {
        $financial = $this->makeFinancial(100000, 0, 0, 0, 10000, 50000, 0, array('cogs'));

        $this->assertTrue($financial->hasRefund());
        $this->assertTrue($financial->hasMissingData());
        $this->assertSame(array('cogs'), $financial->missingData());
    }

    private function makeFinancial(
        int $productRevenue,
        int $shippingRevenue,
        int $feeRevenue,
        int $feeDiscounts,
        int $refundAmount,
        int $cogs,
        int $directCosts,
        array $missingData = array()
    ): OrderFinancialData {
        $m = static function (int $amount): Money {
            return new Money($amount, 'IRR', 0);
        };

        return new OrderFinancialData(
            10,
            '10',
            'completed',
            'IRR',
            $m($productRevenue),
            $m($shippingRevenue),
            $m($feeRevenue),
            $m($feeDiscounts),
            $m($refundAmount),
            $m(0),
            $m(90000),
            $m(1090000),
            $m($cogs),
            $m($cogs),
            $m(0),
            $m(0),
            $m(0),
            $m(0),
            $m($directCosts),
            $refundAmount > 0 ? 1 : 0,
            0,
            0,
            array(),
            array(),
            array(),
            $missingData
        );
    }
}
