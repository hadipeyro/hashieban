<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

final class CouponDiscountAnalyzer
{
    public function analyzeSummary(array $row): array
    {
        $orderCount = (int) ($row['order_count'] ?? 0);
        $couponOrderCount = (int) ($row['coupon_order_count'] ?? 0);
        $noCouponOrderCount = (int) ($row['no_coupon_order_count'] ?? 0);
        $couponRevenue = (int) ($row['coupon_revenue_minor'] ?? 0);
        $couponProfit = (int) ($row['coupon_profit_minor'] ?? 0);
        $couponDiscount = (int) ($row['coupon_discount_minor'] ?? 0);
        $noCouponRevenue = (int) ($row['no_coupon_revenue_minor'] ?? 0);
        $noCouponProfit = (int) ($row['no_coupon_profit_minor'] ?? 0);
        $preDiscountProfit = $couponProfit + $couponDiscount;
        $couponGrossBeforeDiscount = $couponRevenue + $couponDiscount;

        return array(
            'order_count' => $orderCount,
            'revenue_minor' => (int) ($row['revenue_minor'] ?? 0),
            'profit_minor' => (int) ($row['profit_minor'] ?? 0),
            'discount_minor' => (int) ($row['discount_minor'] ?? 0),
            'discounted_order_count' => (int) ($row['discounted_order_count'] ?? 0),
            'coupon_order_count' => $couponOrderCount,
            'coupon_loss_order_count' => (int) ($row['coupon_loss_order_count'] ?? 0),
            'multi_coupon_order_count' => (int) ($row['multi_coupon_order_count'] ?? 0),
            'unattributed_discount_order_count' => (int) ($row['unattributed_discount_order_count'] ?? 0),
            'coupon_revenue_minor' => $couponRevenue,
            'coupon_profit_minor' => $couponProfit,
            'coupon_discount_minor' => $couponDiscount,
            'coupon_pre_discount_profit_minor' => $preDiscountProfit,
            'no_coupon_revenue_minor' => $noCouponRevenue,
            'no_coupon_profit_minor' => $noCouponProfit,
            'no_coupon_order_count' => $noCouponOrderCount,
            'coupon_order_share_percentage' => $orderCount > 0
                ? ($couponOrderCount / $orderCount) * 100
                : 0.0,
            'coupon_margin_percentage' => $couponRevenue !== 0
                ? ($couponProfit / $couponRevenue) * 100
                : null,
            'no_coupon_margin_percentage' => $noCouponRevenue !== 0
                ? ($noCouponProfit / $noCouponRevenue) * 100
                : null,
            'coupon_average_order_minor' => $couponOrderCount > 0
                ? (int) round($couponRevenue / $couponOrderCount)
                : 0,
            'no_coupon_average_order_minor' => $noCouponOrderCount > 0
                ? (int) round($noCouponRevenue / $noCouponOrderCount)
                : 0,
            'coupon_profit_per_order_minor' => $couponOrderCount > 0
                ? (int) round($couponProfit / $couponOrderCount)
                : 0,
            'no_coupon_profit_per_order_minor' => $noCouponOrderCount > 0
                ? (int) round($noCouponProfit / $noCouponOrderCount)
                : 0,
            'coupon_discount_rate_percentage' => $couponGrossBeforeDiscount > 0
                ? ($couponDiscount / $couponGrossBeforeDiscount) * 100
                : null,
            'coupon_profit_retention_percentage' => $preDiscountProfit > 0
                ? ($couponProfit / $preDiscountProfit) * 100
                : null,
            'coupon_loss_order_rate_percentage' => $couponOrderCount > 0
                ? (((int) ($row['coupon_loss_order_count'] ?? 0)) / $couponOrderCount) * 100
                : 0.0,
            'margin_gap_points' => $this->marginGap(
                $couponRevenue,
                $couponProfit,
                $noCouponRevenue,
                $noCouponProfit
            ),
        );
    }

    public function enrichCouponRows(array $rows): array
    {
        $normalized = array();

        foreach ($rows as $row) {
            $orders = (int) ($row['order_count'] ?? 0);
            $revenue = (int) ($row['revenue_minor'] ?? 0);
            $profit = (int) ($row['profit_minor'] ?? 0);
            $discount = max(0, (int) ($row['coupon_discount_minor'] ?? 0));
            $grossBeforeDiscount = $revenue + $discount;
            $preDiscountProfit = $profit + $discount;
            $lossOrders = (int) ($row['loss_order_count'] ?? 0);

            $normalized[] = array(
                'coupon_code' => (string) ($row['coupon_code'] ?? ''),
                'order_count' => $orders,
                'revenue_minor' => $revenue,
                'profit_minor' => $profit,
                'coupon_discount_minor' => $discount,
                'pre_discount_profit_minor' => $preDiscountProfit,
                'loss_order_count' => $lossOrders,
                'incomplete_count' => (int) ($row['incomplete_count'] ?? 0),
                'average_order_minor' => $orders > 0
                    ? (int) round($revenue / $orders)
                    : 0,
                'average_discount_minor' => $orders > 0
                    ? (int) round($discount / $orders)
                    : 0,
                'margin_percentage' => $revenue !== 0
                    ? ($profit / $revenue) * 100
                    : null,
                'discount_rate_percentage' => $grossBeforeDiscount > 0
                    ? ($discount / $grossBeforeDiscount) * 100
                    : null,
                'loss_order_rate_percentage' => $orders > 0
                    ? ($lossOrders / $orders) * 100
                    : 0.0,
                'profit_to_discount_ratio' => $discount > 0
                    ? $profit / $discount
                    : null,
                'status' => $this->statusFor($profit, $discount, $orders),
            );
        }

        return $normalized;
    }

    public function bestBy(array $rows, string $metric): ?array
    {
        $best = null;

        foreach ($rows as $row) {
            if ((int) ($row['order_count'] ?? 0) <= 0) {
                continue;
            }

            if (
                $best === null
                || (int) ($row[$metric] ?? 0) > (int) ($best[$metric] ?? 0)
            ) {
                $best = $row;
            }
        }

        return $best;
    }

    private function statusFor(
        int $profitMinor,
        int $discountMinor,
        int $orders
    ): string {
        if ($orders <= 0) {
            return 'empty';
        }

        if ($profitMinor < 0) {
            return 'loss';
        }

        if ($profitMinor === 0) {
            return 'fragile';
        }

        if ($discountMinor > $profitMinor) {
            return 'pressure';
        }

        return 'healthy';
    }

    private function marginGap(
        int $couponRevenue,
        int $couponProfit,
        int $noCouponRevenue,
        int $noCouponProfit
    ): ?float {
        if ($couponRevenue === 0 || $noCouponRevenue === 0) {
            return null;
        }

        return (($couponProfit / $couponRevenue) * 100)
            - (($noCouponProfit / $noCouponRevenue) * 100);
    }
}
