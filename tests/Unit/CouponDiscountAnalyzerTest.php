<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Integration\WooCommerce\Analytics\CouponDiscountAnalyzer;
use Hashieban\Tests\TestCase;

final class CouponDiscountAnalyzerTest extends TestCase
{
    public function testSummaryComparesCouponAndRegularOrders(): void
    {
        $summary = $this->analyzer()->analyzeSummary(
            array(
                'order_count' => 4,
                'coupon_order_count' => 2,
                'coupon_loss_order_count' => 1,
                'coupon_revenue_minor' => 8000,
                'coupon_profit_minor' => 2000,
                'coupon_discount_minor' => 1000,
                'no_coupon_order_count' => 2,
                'no_coupon_revenue_minor' => 6000,
                'no_coupon_profit_minor' => 3000,
            )
        );

        $this->assertFloatEquals(50.0, $summary['coupon_order_share_percentage']);
        $this->assertFloatEquals(25.0, $summary['coupon_margin_percentage']);
        $this->assertFloatEquals(50.0, $summary['no_coupon_margin_percentage']);
        $this->assertFloatEquals(-25.0, $summary['margin_gap_points']);
        $this->assertSame(4000, $summary['coupon_average_order_minor']);
        $this->assertSame(1000, $summary['coupon_profit_per_order_minor']);
        $this->assertSame(3000, $summary['coupon_pre_discount_profit_minor']);
        $this->assertFloatEquals(50.0, $summary['coupon_loss_order_rate_percentage']);
    }

    public function testCouponRowFlagsHighDiscountPressure(): void
    {
        $rows = $this->analyzer()->enrichCouponRows(
            array(
                array(
                    'coupon_code' => 'WELCOME',
                    'order_count' => 5,
                    'revenue_minor' => 10000,
                    'profit_minor' => 1200,
                    'coupon_discount_minor' => 2000,
                    'loss_order_count' => 0,
                ),
            )
        );

        $this->assertSame('WELCOME', $rows[0]['coupon_code']);
        $this->assertSame('pressure', $rows[0]['status']);
        $this->assertSame(2000, $rows[0]['average_order_minor']);
        $this->assertSame(400, $rows[0]['average_discount_minor']);
        $this->assertFloatEquals(12.0, $rows[0]['margin_percentage']);
    }

    public function testLossMakingCouponIsMarkedAsLoss(): void
    {
        $rows = $this->analyzer()->enrichCouponRows(
            array(
                array(
                    'coupon_code' => 'RISKY',
                    'order_count' => 2,
                    'revenue_minor' => 3000,
                    'profit_minor' => -500,
                    'coupon_discount_minor' => 900,
                    'loss_order_count' => 2,
                ),
            )
        );

        $this->assertSame('loss', $rows[0]['status']);
        $this->assertFloatEquals(100.0, $rows[0]['loss_order_rate_percentage']);
    }

    public function testBestCouponCanDifferByMetric(): void
    {
        $rows = array(
            array('coupon_code' => 'A', 'order_count' => 4, 'revenue_minor' => 9000, 'profit_minor' => 1000),
            array('coupon_code' => 'B', 'order_count' => 3, 'revenue_minor' => 7000, 'profit_minor' => 2500),
        );

        $bestSales = $this->analyzer()->bestBy($rows, 'revenue_minor');
        $bestProfit = $this->analyzer()->bestBy($rows, 'profit_minor');

        $this->assertSame('A', $bestSales['coupon_code']);
        $this->assertSame('B', $bestProfit['coupon_code']);
    }

    private function analyzer(): CouponDiscountAnalyzer
    {
        return new CouponDiscountAnalyzer();
    }
}
