<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Performance\OrderMetricsRepository;

final class SalesChannelIntelligenceService
{
    private OrderMetricsRepository $metrics;

    public function __construct(
        OrderMetricsRepository $metrics
    ) {
        $this->metrics = $metrics;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();
        $ready = $this->metrics->isReady();

        $base = array(
            'currency' => $currency,
            'precision' => $precision,
            'start' => $start,
            'end' => $end,
            'index_ready' => $ready,
            'order_count' => 0,
            'revenue_minor' => 0,
            'profit_minor' => 0,
            'margin_percentage' => null,
            'attributed_order_count' => 0,
            'unknown_order_count' => 0,
            'attribution_coverage_percentage' => 0.0,
            'coverage_status' => 'داده کافی نیست',
            'channels' => array(),
            'campaigns' => array(),
            'referrers' => array(),
            'best_sales_channel' => null,
            'best_profit_channel' => null,
            'insights' => array(),
        );

        if (! $ready) {
            $base['insights'][] = array(
                'type' => 'info',
                'title' => 'شاخص سریع در حال آماده‌سازی است',
                'description' => 'برای جلوگیری از گزارش ناقص، تحلیل کانال‌ها بعد از پایان بازسازی خودکار نمایش داده می‌شود.',
            );

            return $base;
        }

        $summary = $this->metrics->summaryBetween(
            $start,
            $end,
            $currency
        );

        $rawChannels = $this->metrics->channelSummaryBetween(
            $start,
            $end,
            $currency
        );

        $orderCount = (int) ($summary['order_count'] ?? 0);
        $revenueMinor = (int) ($summary['revenue_minor'] ?? 0);
        $profitMinor = (int) ($summary['profit_minor'] ?? 0);

        $channels = array();
        $attributedOrders = 0;
        $unknownOrders = 0;

        foreach ($rawChannels as $row) {
            $rowRevenue = (int) ($row['revenue_minor'] ?? 0);
            $rowProfit = (int) ($row['profit_minor'] ?? 0);
            $rowOrders = (int) ($row['order_count'] ?? 0);
            $rowAttributed = (int) ($row['attributed_order_count'] ?? 0);
            $channelKey = (string) ($row['channel_key'] ?? 'unknown');

            $attributedOrders += $rowAttributed;

            if ($channelKey === 'unknown') {
                $unknownOrders += $rowOrders;
            }

            $channels[] = array(
                'channel_key' => $channelKey,
                'channel_name' => (string) ($row['channel_name'] ?? 'بدون داده منبع'),
                'channel_group' => (string) ($row['channel_group'] ?? 'unknown'),
                'order_count' => $rowOrders,
                'revenue_minor' => $rowRevenue,
                'profit_minor' => $rowProfit,
                'sales_share_percentage' => $revenueMinor !== 0
                    ? ($rowRevenue / $revenueMinor) * 100
                    : 0.0,
                'margin_percentage' => $rowRevenue !== 0
                    ? ($rowProfit / $rowRevenue) * 100
                    : null,
                'incomplete_count' => (int) ($row['incomplete_count'] ?? 0),
                'attribution_known' => $rowAttributed > 0,
            );
        }

        $coverage = $orderCount > 0
            ? ($attributedOrders / $orderCount) * 100
            : 0.0;

        $campaigns = $this->normalizeBreakdownRows(
            $this->metrics->campaignSummaryBetween(
                $start,
                $end,
                $currency,
                12
            )
        );

        $referrers = $this->normalizeBreakdownRows(
            $this->metrics->referrerSummaryBetween(
                $start,
                $end,
                $currency,
                12
            )
        );

        $bestSales = $this->bestChannel(
            $channels,
            'revenue_minor'
        );

        $bestProfit = $this->bestChannel(
            $channels,
            'profit_minor'
        );

        $insights = $this->buildInsights(
            $orderCount,
            $coverage,
            $unknownOrders,
            $channels,
            $bestSales,
            $bestProfit
        );

        return array_merge(
            $base,
            array(
                'order_count' => $orderCount,
                'revenue_minor' => $revenueMinor,
                'profit_minor' => $profitMinor,
                'margin_percentage' => $revenueMinor !== 0
                    ? ($profitMinor / $revenueMinor) * 100
                    : null,
                'attributed_order_count' => $attributedOrders,
                'unknown_order_count' => $unknownOrders,
                'attribution_coverage_percentage' => $coverage,
                'coverage_status' => $this->coverageStatus(
                    $orderCount,
                    $coverage
                ),
                'channels' => $channels,
                'campaigns' => $campaigns,
                'referrers' => $referrers,
                'best_sales_channel' => $bestSales,
                'best_profit_channel' => $bestProfit,
                'insights' => $insights,
            )
        );
    }

    public function isAttributionDisabled(): bool
    {
        return (string) get_option(
            'woocommerce_feature_order_attribution_enabled',
            'yes'
        ) === 'no';
    }

    private function normalizeBreakdownRows(array $rows): array
    {
        $normalized = array();

        foreach ($rows as $row) {
            $revenue = (int) ($row['revenue_minor'] ?? 0);
            $profit = (int) ($row['profit_minor'] ?? 0);
            $row['order_count'] = (int) ($row['order_count'] ?? 0);
            $row['revenue_minor'] = $revenue;
            $row['profit_minor'] = $profit;
            $row['margin_percentage'] = $revenue !== 0
                ? ($profit / $revenue) * 100
                : null;
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function bestChannel(
        array $channels,
        string $metric
    ): ?array {
        $best = null;

        foreach ($channels as $channel) {
            if (
                (string) ($channel['channel_key'] ?? '') === 'unknown'
                || (int) ($channel['order_count'] ?? 0) <= 0
            ) {
                continue;
            }

            if (
                $best === null
                || (int) ($channel[$metric] ?? 0)
                    > (int) ($best[$metric] ?? 0)
            ) {
                $best = $channel;
            }
        }

        return $best;
    }

    private function buildInsights(
        int $orders,
        float $coverage,
        int $unknownOrders,
        array $channels,
        ?array $bestSales,
        ?array $bestProfit
    ): array {
        if ($orders <= 0) {
            return array(
                array(
                    'type' => 'info',
                    'title' => 'در این بازه سفارش قابل تحلیل وجود ندارد',
                    'description' => 'بازه را بزرگ‌تر کن تا منبع فروش و کیفیت هر کانال قابل مقایسه شود.',
                ),
            );
        }

        $insights = array();

        if ($coverage < 50.0) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'پوشش داده منبع پایین است',
                'description' => 'برای بخش زیادی از سفارش‌ها داده منبع ذخیره نشده؛ سفارش‌های قدیمی حدس زده نمی‌شوند.',
            );
        } elseif ($coverage < 80.0) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'بخشی از سفارش‌ها منبع ندارند',
                'description' => 'گزارش قابل استفاده است، اما برای تصمیم تبلیغاتی بهتر است پوشش منبع بالاتر برود.',
            );
        }

        if (
            $bestSales !== null
            && $bestProfit !== null
            && (string) $bestSales['channel_key']
                !== (string) $bestProfit['channel_key']
        ) {
            $insights[] = array(
                'type' => 'success',
                'title' => 'کانال پرفروش و پرسود یکسان نیستند',
                'description' => (string) $bestSales['channel_name']
                    . ' بیشترین فروش را دارد، اما '
                    . (string) $bestProfit['channel_name']
                    . ' بیشترین سود سفارش را ساخته است.',
            );
        }

        foreach ($channels as $channel) {
            if (
                (string) ($channel['channel_key'] ?? '') !== 'unknown'
                && (int) ($channel['order_count'] ?? 0) >= 2
                && (int) ($channel['profit_minor'] ?? 0) < 0
            ) {
                $insights[] = array(
                    'type' => 'danger',
                    'title' => 'یک کانال فروش زیان‌ده دیده شد',
                    'description' => (string) $channel['channel_name']
                        . ' در این بازه فروش داشته اما مجموع سود سفارش‌های آن منفی است.',
                );
                break;
            }
        }

        if ($unknownOrders > 0 && $coverage >= 80.0) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'چند سفارش بدون منبع باقی مانده است',
                'description' => number_format_i18n($unknownOrders)
                    . ' سفارش داده کافی برای تشخیص منبع نداشته‌اند.',
            );
        }

        if ($insights === array()) {
            $insights[] = array(
                'type' => 'success',
                'title' => 'داده کانال‌ها برای مقایسه آماده است',
                'description' => 'فروش و سود سفارش هر منبع را کنار هم ببین؛ کانال پرفروش همیشه بهترین کانال نیست.',
            );
        }

        return array_slice($insights, 0, 4);
    }

    private function coverageStatus(
        int $orders,
        float $coverage
    ): string {
        if ($orders <= 0) {
            return 'داده کافی نیست';
        }

        if ($coverage >= 80.0) {
            return 'خوب';
        }

        if ($coverage >= 50.0) {
            return 'متوسط';
        }

        return 'نیاز به توجه';
    }
}
