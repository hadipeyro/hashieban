<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;

final class BusinessKpiService
{
    private AnalyticsService $analytics;

    private CustomerProfitabilityService $customers;

    public function __construct(
        AnalyticsService $analytics,
        CustomerProfitabilityService $customers
    ) {
        $this->analytics = $analytics;
        $this->customers = $customers;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $current = $this->analytics->getDashboardData(
            $start,
            $end
        );

        $durationSeconds = max(
            1,
            ($end->getTimestamp() - $start->getTimestamp()) + 1
        );

        $previousEnd = (new DateTimeImmutable(
            '@' . ($start->getTimestamp() - 1)
        ))->setTimezone(wp_timezone());

        $previousStart = (new DateTimeImmutable(
            '@' . ($previousEnd->getTimestamp() - $durationSeconds + 1)
        ))->setTimezone(wp_timezone());

        $previous = $this->analytics->getDashboardData(
            $previousStart,
            $previousEnd
        );

        $customerReport = $this->customers->getReport(
            $start,
            $end
        );

        $revenueMinor = (int) ($current['revenue_minor'] ?? 0);
        $profitMinor = (int) ($current['net_profit_minor'] ?? 0);
        $orders = (int) ($current['order_count'] ?? 0);
        $customers = (int) ($customerReport['customer_count'] ?? 0);
        $repeatCustomers = (int) ($customerReport['repeat_customer_count'] ?? 0);
        $refundedOrders = (int) ($customerReport['orders_with_refunds'] ?? 0);
        $incompleteOrders = (int) ($current['incomplete_count'] ?? 0);

        $aovMinor = $orders > 0
            ? (int) round($revenueMinor / $orders)
            : 0;

        $profitPerOrderMinor = $orders > 0
            ? (int) round($profitMinor / $orders)
            : 0;

        $profitPerCustomerMinor = $customers > 0
            ? (int) round($profitMinor / $customers)
            : 0;

        $repeatRate = $customers > 0
            ? ($repeatCustomers / $customers) * 100
            : 0.0;

        $refundRate = $orders > 0
            ? ($refundedOrders / $orders) * 100
            : 0.0;

        $incompleteRate = $orders > 0
            ? ($incompleteOrders / $orders) * 100
            : 0.0;

        $cogsMinor = (int) ($current['cogs_minor'] ?? 0);
        $directCostsMinor = (int) ($current['direct_costs_minor'] ?? 0);
        $globalCostsMinor = (int) ($current['global_order_costs_minor'] ?? 0);
        $storeExpensesMinor = (int) ($current['store_expenses_minor'] ?? 0);

        $operatingCostsMinor =
            $directCostsMinor
            + $globalCostsMinor
            + $storeExpensesMinor;

        $totalCostMinor =
            $cogsMinor
            + $operatingCostsMinor;

        $costRatio = $revenueMinor > 0
            ? ($totalCostMinor / $revenueMinor) * 100
            : null;

        $operatingCostRatio = $revenueMinor > 0
            ? ($operatingCostsMinor / $revenueMinor) * 100
            : null;

        $cogsRatio = $revenueMinor > 0
            ? ($cogsMinor / $revenueMinor) * 100
            : null;

        $revenueGrowth = $this->percentageChange(
            $revenueMinor,
            (int) ($previous['revenue_minor'] ?? 0)
        );

        $profitGrowth = $this->percentageChange(
            $profitMinor,
            (int) ($previous['net_profit_minor'] ?? 0)
        );

        $orderGrowth = $this->percentageChange(
            $orders,
            (int) ($previous['order_count'] ?? 0)
        );

        $previousAovMinor = (int) ($previous['order_count'] ?? 0) > 0
            ? (int) round(
                (int) ($previous['revenue_minor'] ?? 0)
                / (int) $previous['order_count']
            )
            : 0;

        $aovGrowth = $this->percentageChange(
            $aovMinor,
            $previousAovMinor
        );

        $topCustomerShare = 0.0;

        $topCustomers = (array) (
            $customerReport['top_by_revenue']
            ?? array()
        );

        if (
            isset($topCustomers[0])
            && is_array($topCustomers[0])
            && isset($topCustomers[0]['sales_share_percentage'])
        ) {
            $topCustomerShare = (float) $topCustomers[0]['sales_share_percentage'];
        }

        $componentScores = $this->buildComponentScores(
            $current['margin_percentage'] ?? null,
            $revenueGrowth,
            $profitGrowth,
            $repeatRate,
            $refundRate,
            $incompleteRate
        );

        $score = (int) round(
            array_sum($componentScores)
            / max(1, count($componentScores))
        );

        $score = max(
            0,
            min(100, $score)
        );

        if ($orders <= 0) {
            $score = 0;
        }

        $insights = $orders > 0
            ? $this->buildInsights(
                $profitMinor,
                $current['margin_percentage'] ?? null,
                $revenueGrowth,
                $profitGrowth,
                $repeatRate,
                $refundRate,
                $incompleteRate,
                $operatingCostRatio,
                $topCustomerShare
            )
            : array(
                array(
                    'type' => 'info',
                    'title' => 'برای این بازه سفارش قابل تحلیل وجود ندارد',
                    'description' => 'بازه زمانی را بزرگ‌تر کن تا KPIها و مقایسه مدیریتی معنا پیدا کنند.',
                    'url' => admin_url('admin.php?page=hashieban-kpis&range=90d'),
                    'action' => 'نمایش ۹۰ روز',
                ),
            );

        return array(
            'currency' => (string) ($current['currency'] ?? get_woocommerce_currency()),
            'precision' => (int) ($current['precision'] ?? wc_get_price_decimals()),
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'revenue_minor' => $revenueMinor,
            'net_profit_minor' => $profitMinor,
            'margin_percentage' => $current['margin_percentage'] ?? null,
            'order_count' => $orders,
            'customer_count' => $customers,
            'repeat_customer_count' => $repeatCustomers,
            'repeat_customer_rate' => $repeatRate,
            'refund_order_rate' => $refundRate,
            'incomplete_order_rate' => $incompleteRate,
            'average_order_value_minor' => $aovMinor,
            'profit_per_order_minor' => $profitPerOrderMinor,
            'profit_per_customer_minor' => $profitPerCustomerMinor,
            'cogs_minor' => $cogsMinor,
            'direct_costs_minor' => $directCostsMinor,
            'global_order_costs_minor' => $globalCostsMinor,
            'store_expenses_minor' => $storeExpensesMinor,
            'operating_costs_minor' => $operatingCostsMinor,
            'total_cost_minor' => $totalCostMinor,
            'cost_ratio_percentage' => $costRatio,
            'operating_cost_ratio_percentage' => $operatingCostRatio,
            'cogs_ratio_percentage' => $cogsRatio,
            'revenue_growth_percentage' => $revenueGrowth,
            'profit_growth_percentage' => $profitGrowth,
            'order_growth_percentage' => $orderGrowth,
            'aov_growth_percentage' => $aovGrowth,
            'top_customer_sales_share_percentage' => $topCustomerShare,
            'performance_score' => $score,
            'performance_status' => $orders > 0
                ? $this->scoreStatus($score)
                : 'داده کافی نیست',
            'component_scores' => $componentScores,
            'insights' => $insights,
            'performance_mode' => (string) ($current['performance_mode'] ?? 'legacy'),
        );
    }

    private function buildComponentScores(
        $margin,
        ?float $revenueGrowth,
        ?float $profitGrowth,
        float $repeatRate,
        float $refundRate,
        float $incompleteRate
    ): array {
        return array(
            'margin' => $this->marginScore(
                is_numeric($margin) ? (float) $margin : null
            ),
            'revenue_growth' => $this->growthScore($revenueGrowth),
            'profit_growth' => $this->growthScore($profitGrowth),
            'repeat' => $this->repeatScore($repeatRate),
            'refund' => $this->refundScore($refundRate),
            'data' => $this->dataScore($incompleteRate),
        );
    }

    private function marginScore(?float $margin): int
    {
        if ($margin === null) {
            return 50;
        }

        if ($margin < 0) {
            return 0;
        }

        if ($margin >= 30) {
            return 100;
        }

        if ($margin >= 20) {
            return 85;
        }

        if ($margin >= 12) {
            return 65;
        }

        if ($margin >= 5) {
            return 45;
        }

        return 25;
    }

    private function growthScore(?float $growth): int
    {
        if ($growth === null) {
            return 50;
        }

        if ($growth >= 20) {
            return 100;
        }

        if ($growth >= 5) {
            return 80;
        }

        if ($growth >= 0) {
            return 65;
        }

        if ($growth >= -10) {
            return 40;
        }

        if ($growth >= -25) {
            return 20;
        }

        return 0;
    }

    private function repeatScore(float $repeatRate): int
    {
        if ($repeatRate >= 40) {
            return 100;
        }

        if ($repeatRate >= 25) {
            return 80;
        }

        if ($repeatRate >= 15) {
            return 60;
        }

        if ($repeatRate >= 5) {
            return 40;
        }

        return 20;
    }

    private function refundScore(float $refundRate): int
    {
        if ($refundRate <= 2) {
            return 100;
        }

        if ($refundRate <= 5) {
            return 80;
        }

        if ($refundRate <= 10) {
            return 55;
        }

        if ($refundRate <= 20) {
            return 30;
        }

        return 10;
    }

    private function dataScore(float $incompleteRate): int
    {
        if ($incompleteRate <= 1) {
            return 100;
        }

        if ($incompleteRate <= 5) {
            return 80;
        }

        if ($incompleteRate <= 15) {
            return 55;
        }

        if ($incompleteRate <= 30) {
            return 30;
        }

        return 10;
    }

    private function scoreStatus(int $score): string
    {
        if ($score >= 80) {
            return 'عالی';
        }

        if ($score >= 65) {
            return 'خوب';
        }

        if ($score >= 50) {
            return 'نیازمند توجه';
        }

        return 'پرریسک';
    }

    private function buildInsights(
        int $profitMinor,
        $margin,
        ?float $revenueGrowth,
        ?float $profitGrowth,
        float $repeatRate,
        float $refundRate,
        float $incompleteRate,
        ?float $operatingCostRatio,
        float $topCustomerShare
    ): array {
        $insights = array();

        $marginValue = is_numeric($margin)
            ? (float) $margin
            : null;

        if ($profitMinor < 0 || ($marginValue !== null && $marginValue < 0)) {
            $insights[] = array(
                'type' => 'danger',
                'title' => 'سودآوری این بازه منفی است',
                'description' => 'فروش را به‌تنهایی معیار موفقیت ندان؛ هزینه‌ها، COGS و سفارش‌های زیان‌ده را بررسی کن.',
                'url' => admin_url('admin.php?page=hashieban-alerts'),
                'action' => 'بررسی هشدارها',
            );
        } elseif (
            $revenueGrowth !== null
            && $profitGrowth !== null
            && $revenueGrowth > 5
            && $profitGrowth < 0
        ) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'فروش رشد کرده اما سود افت کرده',
                'description' => 'احتمالاً رشد فروش با افزایش هزینه، تخفیف یا ترکیب کم‌حاشیه همراه بوده است.',
                'url' => admin_url('admin.php?page=hashieban-time'),
                'action' => 'تحلیل روند',
            );
        }

        if ($repeatRate < 15) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'نرخ مشتری تکراری پایین است',
                'description' => 'فرصت رشد از مشتریان قبلی بالاست؛ مشتریان باارزش و الگوی تکرار خرید را بررسی کن.',
                'url' => admin_url('admin.php?page=hashieban-customers'),
                'action' => 'تحلیل مشتریان',
            );
        }

        if ($refundRate > 10) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'نرخ سفارش‌های دارای مرجوعی بالاست',
                'description' => 'محصولات و سفارش‌هایی که Refund بیشتری دارند می‌توانند حاشیه سود را سریع کاهش دهند.',
                'url' => admin_url('admin.php?page=hashieban-orders'),
                'action' => 'بررسی سفارش‌ها',
            );
        }

        if ($incompleteRate > 5) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'بخشی از داده مالی ناقص است',
                'description' => 'برای تصمیم‌گیری دقیق‌تر، سفارش‌های دارای COGS یا اطلاعات مالی ناقص را تکمیل کن.',
                'url' => admin_url('admin.php?page=hashieban-data-health'),
                'action' => 'سلامت داده',
            );
        }

        if (
            $operatingCostRatio !== null
            && $operatingCostRatio > 25
        ) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'هزینه‌های عملیاتی سهم بالایی از فروش دارند',
                'description' => 'هزینه‌های سفارش و هزینه‌های عمومی فروشگاه را از نظر روند و بودجه بررسی کن.',
                'url' => admin_url('admin.php?page=hashieban-expense-intelligence'),
                'action' => 'هوش هزینه‌ها',
            );
        }

        if ($topCustomerShare >= 35) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'فروش به یک مشتری وابستگی بالایی دارد',
                'description' => 'سهم بالای یک مشتری از فروش می‌تواند ریسک تمرکز ایجاد کند؛ ترکیب مشتریان را متنوع نگه دار.',
                'url' => admin_url('admin.php?page=hashieban-customers'),
                'action' => 'ترکیب مشتریان',
            );
        }

        if ($insights === array()) {
            $insights[] = array(
                'type' => 'success',
                'title' => 'نشانه هشدار برجسته‌ای دیده نشد',
                'description' => 'شاخص‌های این بازه متعادل‌اند؛ برای رشد بیشتر روی محصولات و مشتریان پربازده تمرکز کن.',
                'url' => admin_url('admin.php?page=hashieban-products'),
                'action' => 'محصولات پربازده',
            );
        }

        return array_slice(
            $insights,
            0,
            4
        );
    }

    private function percentageChange(
        int $current,
        int $previous
    ): ?float {
        if ($previous === 0) {
            return $current === 0
                ? 0.0
                : null;
        }

        return (
            ($current - $previous)
            / abs($previous)
        ) * 100;
    }
}
