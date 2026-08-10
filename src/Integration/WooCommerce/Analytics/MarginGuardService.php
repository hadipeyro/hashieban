<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;

final class MarginGuardService
{
    private ProductProfitabilityService $products;
    private OrderProfitCenterService $orders;
    private TimeIntelligenceService $time;

    public function __construct(
        ProductProfitabilityService $products,
        OrderProfitCenterService $orders,
        TimeIntelligenceService $time
    ) {
        $this->products = $products;
        $this->orders = $orders;
        $this->time = $time;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        float $marginThreshold,
        float $profitDropThreshold,
        float $returnRateThreshold
    ): array {
        $productReport = $this->products->getReport($start, $end);
        $orderReport = $this->orders->getReport($start, $end, array());
        $timeReport = $this->time->getReport($start, $end);

        $lowMarginProducts = array_values(
            array_filter(
                (array) $productReport['products'],
                static function (array $row) use ($marginThreshold): bool {
                    return (int) $row['revenue_minor'] > 0
                        && $row['margin_percentage'] !== null
                        && (float) $row['margin_percentage'] < $marginThreshold
                        && (int) $row['profit_minor'] >= 0;
                }
            )
        );

        usort(
            $lowMarginProducts,
            static function (array $a, array $b): int {
                return ((float) $a['margin_percentage']) <=> ((float) $b['margin_percentage']);
            }
        );

        $lossProducts = array_values(
            array_filter(
                (array) $productReport['products'],
                static function (array $row): bool {
                    return (int) $row['profit_minor'] < 0;
                }
            )
        );

        usort(
            $lossProducts,
            static function (array $a, array $b): int {
                return ((int) $a['profit_minor']) <=> ((int) $b['profit_minor']);
            }
        );

        $missingCogsProducts = array_values(
            array_filter(
                (array) $productReport['products'],
                static function (array $row): bool {
                    return ! (bool) $row['cogs_complete'];
                }
            )
        );

        $highReturnProducts = array_values(
            array_filter(
                (array) $productReport['products'],
                static function (array $row) use ($returnRateThreshold): bool {
                    return $row['return_rate_percentage'] !== null
                        && (float) $row['return_rate_percentage'] >= $returnRateThreshold
                        && (int) $row['refunded_quantity'] > 0;
                }
            )
        );

        usort(
            $highReturnProducts,
            static function (array $a, array $b): int {
                return ((float) $b['return_rate_percentage']) <=> ((float) $a['return_rate_percentage']);
            }
        );

        $lossOrders = array_values(
            array_filter(
                (array) $orderReport['orders'],
                static function (array $row): bool {
                    return (int) $row['profit_minor'] < 0;
                }
            )
        );

        usort(
            $lossOrders,
            static function (array $a, array $b): int {
                return ((int) $a['profit_minor']) <=> ((int) $b['profit_minor']);
            }
        );

        $alerts = array();

        if (count($lossOrders) > 0) {
            $alerts[] = array(
                'severity' => 'critical',
                'code' => 'loss_orders',
                'title' => 'سفارش زیان‌ده شناسایی شد',
                'message' => number_format_i18n(count($lossOrders)) . ' سفارش در بازه انتخابی سود منفی دارند.',
                'metric' => number_format_i18n(count($lossOrders)) . ' سفارش',
                'url' => admin_url('admin.php?page=hashieban-orders&profitability=loss'),
            );
        }

        if (count($lossProducts) > 0) {
            $alerts[] = array(
                'severity' => 'critical',
                'code' => 'loss_products',
                'title' => 'محصول زیان‌ده وجود دارد',
                'message' => number_format_i18n(count($lossProducts)) . ' محصول در این بازه سود منفی ساخته‌اند.',
                'metric' => number_format_i18n(count($lossProducts)) . ' محصول',
                'url' => admin_url('admin.php?page=hashieban-products'),
            );
        }

        $profitChange = $timeReport['comparison']['profit_change_percentage'] ?? null;

        if (
            $profitChange !== null
            && (float) $profitChange <= -1 * abs($profitDropThreshold)
        ) {
            $alerts[] = array(
                'severity' => 'critical',
                'code' => 'profit_drop',
                'title' => 'افت معنادار سود نسبت به دوره قبل',
                'message' => 'سود دوره فعلی نسبت به دوره هم‌اندازه قبلی افت کرده است.',
                'metric' => number_format_i18n(abs((float) $profitChange), 1) . '٪ افت',
                'url' => admin_url('admin.php?page=hashieban-time'),
            );
        }

        if (count($lowMarginProducts) > 0) {
            $alerts[] = array(
                'severity' => 'warning',
                'code' => 'low_margin_products',
                'title' => 'محصولات با Margin پایین',
                'message' => number_format_i18n(count($lowMarginProducts)) . ' محصول زیر آستانه ' . number_format_i18n($marginThreshold, 1) . '٪ قرار دارند.',
                'metric' => number_format_i18n(count($lowMarginProducts)) . ' محصول',
                'url' => admin_url('admin.php?page=hashieban-products'),
            );
        }

        if ((int) $orderReport['incomplete_count'] > 0) {
            $alerts[] = array(
                'severity' => 'warning',
                'code' => 'incomplete_orders',
                'title' => 'سفارش با داده مالی ناقص',
                'message' => 'برخی سفارش‌ها برای محاسبه سود قابل اتکا اطلاعات کامل ندارند.',
                'metric' => number_format_i18n((int) $orderReport['incomplete_count']) . ' سفارش',
                'url' => admin_url('admin.php?page=hashieban-orders&profitability=incomplete'),
            );
        }

        if (count($missingCogsProducts) > 0) {
            $alerts[] = array(
                'severity' => 'warning',
                'code' => 'missing_cogs_products',
                'title' => 'COGS ناقص در محصولات',
                'message' => 'برای بعضی محصولات قیمت خرید کامل نیست؛ رتبه سودآوری آن‌ها ممکن است گمراه‌کننده باشد.',
                'metric' => number_format_i18n(count($missingCogsProducts)) . ' محصول',
                'url' => admin_url('admin.php?page=hashieban-products'),
            );
        }

        if (count($highReturnProducts) > 0) {
            $alerts[] = array(
                'severity' => 'warning',
                'code' => 'high_returns',
                'title' => 'نرخ مرجوعی بالا در بعضی محصولات',
                'message' => number_format_i18n(count($highReturnProducts)) . ' محصول نرخ مرجوعی بالاتر از آستانه دارند.',
                'metric' => number_format_i18n(count($highReturnProducts)) . ' محصول',
                'url' => admin_url('admin.php?page=hashieban-products'),
            );
        }

        if ($alerts === array()) {
            $alerts[] = array(
                'severity' => 'good',
                'code' => 'healthy',
                'title' => 'هشدار مهمی در این بازه دیده نشد',
                'message' => 'بر اساس آستانه‌های فعلی، Margin، سود و سلامت داده در وضعیت قابل قبول است.',
                'metric' => 'وضعیت پایدار',
                'url' => '',
            );
        }

        $severityCounts = array(
            'critical' => 0,
            'warning' => 0,
            'good' => 0,
        );

        foreach ($alerts as $alert) {
            $severity = (string) $alert['severity'];
            if (isset($severityCounts[$severity])) {
                $severityCounts[$severity]++;
            }
        }

        $riskRevenueMinor = 0;
        foreach ($lowMarginProducts as $row) {
            $riskRevenueMinor += (int) $row['revenue_minor'];
        }
        foreach ($lossProducts as $row) {
            $riskRevenueMinor += (int) $row['revenue_minor'];
        }

        $score = 100;
        $score -= min(30, count($lossOrders) * 6);
        $score -= min(20, count($lossProducts) * 8);
        $score -= min(20, count($lowMarginProducts) * 3);
        $score -= min(15, (int) $orderReport['incomplete_count'] * 3);
        $score -= min(10, count($highReturnProducts) * 2);

        if (
            $profitChange !== null
            && (float) $profitChange <= -1 * abs($profitDropThreshold)
        ) {
            $score -= 15;
        }

        $score = max(0, min(100, $score));

        return array(
            'currency' => (string) $orderReport['currency'],
            'precision' => (int) $orderReport['precision'],
            'start' => $start,
            'end' => $end,
            'health_score' => $score,
            'alerts' => $alerts,
            'severity_counts' => $severityCounts,
            'margin_threshold' => $marginThreshold,
            'profit_drop_threshold' => $profitDropThreshold,
            'return_rate_threshold' => $returnRateThreshold,
            'profit_change_percentage' => $profitChange,
            'risk_revenue_minor' => $riskRevenueMinor,
            'loss_orders' => array_slice($lossOrders, 0, 8),
            'loss_products' => array_slice($lossProducts, 0, 8),
            'low_margin_products' => array_slice($lowMarginProducts, 0, 10),
            'missing_cogs_products' => array_slice($missingCogsProducts, 0, 8),
            'high_return_products' => array_slice($highReturnProducts, 0, 8),
            'order_report' => $orderReport,
            'product_report' => $productReport,
            'time_report' => $timeReport,
        );
    }
}
