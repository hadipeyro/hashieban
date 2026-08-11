<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Performance\OrderMetricsRepository;

final class CouponDiscountIntelligenceService
{
    private OrderMetricsRepository $metrics;

    private CouponDiscountAnalyzer $analyzer;

    public function __construct(
        OrderMetricsRepository $metrics,
        CouponDiscountAnalyzer $analyzer
    ) {
        $this->metrics = $metrics;
        $this->analyzer = $analyzer;
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
            'summary' => $this->analyzer->analyzeSummary(array()),
            'coupons' => array(),
            'risky_orders' => array(),
            'best_sales_coupon' => null,
            'best_profit_coupon' => null,
            'highest_discount_coupon' => null,
            'insights' => array(),
        );

        if (! $ready) {
            $base['insights'][] = array(
                'type' => 'info',
                'title' => 'شاخص سریع در حال آماده‌سازی است',
                'description' => 'برای جلوگیری از گزارش ناقص، تحلیل تخفیف بعد از پایان بازسازی خودکار نمایش داده می‌شود.',
            );

            return $base;
        }

        $summary = $this->analyzer->analyzeSummary(
            $this->metrics->discountSummaryBetween(
                $start,
                $end,
                $currency
            )
        );

        $coupons = $this->analyzer->enrichCouponRows(
            $this->metrics->couponSummaryBetween(
                $start,
                $end,
                $currency,
                60
            )
        );

        $riskyOrders = $this->normalizeRiskyOrders(
            $this->metrics->riskyCouponOrdersBetween(
                $start,
                $end,
                $currency,
                12
            )
        );

        $bestSales = $this->analyzer->bestBy(
            $coupons,
            'revenue_minor'
        );
        $bestProfit = $this->analyzer->bestBy(
            $coupons,
            'profit_minor'
        );
        $highestDiscount = $this->analyzer->bestBy(
            $coupons,
            'coupon_discount_minor'
        );

        return array_merge(
            $base,
            array(
                'summary' => $summary,
                'coupons' => $coupons,
                'risky_orders' => $riskyOrders,
                'best_sales_coupon' => $bestSales,
                'best_profit_coupon' => $bestProfit,
                'highest_discount_coupon' => $highestDiscount,
                'insights' => $this->buildInsights(
                    $summary,
                    $coupons,
                    $bestSales,
                    $bestProfit
                ),
            )
        );
    }

    public function couponsDisabled(): bool
    {
        return function_exists('wc_coupons_enabled')
            && ! wc_coupons_enabled();
    }

    private function normalizeRiskyOrders(array $rows): array
    {
        $normalized = array();

        foreach ($rows as $row) {
            $revenue = (int) ($row['revenue_minor'] ?? 0);
            $profit = (int) ($row['profit_minor'] ?? 0);
            $discount = max(0, (int) ($row['discount_minor'] ?? 0));

            $normalized[] = array(
                'order_id' => (int) ($row['order_id'] ?? 0),
                'order_date_local' => (string) ($row['order_date_local'] ?? ''),
                'revenue_minor' => $revenue,
                'profit_minor' => $profit,
                'discount_minor' => $discount,
                'coupon_count' => max(0, (int) ($row['coupon_count'] ?? 0)),
                'incomplete' => ! empty($row['incomplete']),
                'margin_percentage' => $revenue !== 0
                    ? ($profit / $revenue) * 100
                    : null,
                'discount_rate_percentage' => ($revenue + $discount) > 0
                    ? ($discount / ($revenue + $discount)) * 100
                    : null,
            );
        }

        return $normalized;
    }

    private function buildInsights(
        array $summary,
        array $coupons,
        ?array $bestSales,
        ?array $bestProfit
    ): array {
        $orders = (int) ($summary['order_count'] ?? 0);
        $couponOrders = (int) ($summary['coupon_order_count'] ?? 0);

        if ($orders <= 0) {
            return array(
                array(
                    'type' => 'info',
                    'title' => 'در این بازه سفارش قابل تحلیل وجود ندارد',
                    'description' => 'بازه را بزرگ‌تر کن تا اثر تخفیف و کوپن قابل مقایسه شود.',
                ),
            );
        }

        if ($couponOrders <= 0) {
            return array(
                array(
                    'type' => 'info',
                    'title' => 'در این بازه سفارشی با کوپن پیدا نشد',
                    'description' => 'وقتی مشتریان از کد تخفیف استفاده کنند، حاشیه‌بان فروش و سود سفارش‌های آن‌ها را جداگانه تحلیل می‌کند.',
                ),
            );
        }

        $insights = array();
        $lossOrders = (int) ($summary['coupon_loss_order_count'] ?? 0);
        $couponProfit = (int) ($summary['coupon_profit_minor'] ?? 0);
        $couponDiscount = (int) ($summary['coupon_discount_minor'] ?? 0);
        $marginGap = $summary['margin_gap_points'] ?? null;

        if ($lossOrders > 0) {
            $insights[] = array(
                'type' => 'danger',
                'title' => 'سفارش زیان‌ده با کوپن دیده شد',
                'description' => number_format_i18n($lossOrders)
                    . ' سفارش دارای کوپن در این بازه سود منفی داشته‌اند؛ قبل از تکرار همان تخفیف بررسی‌شان کن.',
            );
        }

        if (is_numeric($marginGap) && (float) $marginGap <= -5.0) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'درصد سود سفارش‌های کوپنی پایین‌تر است',
                'description' => 'در این بازه درصد سود سفارش‌های دارای کوپن حداقل ۵ واحد درصد پایین‌تر از سفارش‌های بدون کوپن بوده است.',
            );
        }

        if ($couponProfit > 0 && $couponDiscount > $couponProfit) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'فشار تخفیف روی سود بالاست',
                'description' => 'مبلغ تخفیف داده‌شده از سود باقی‌مانده سفارش‌های کوپنی بیشتر بوده؛ این الزاماً بد نیست، اما باید با جذب مشتری و تکرار خرید توجیه شود.',
            );
        }

        if (
            $bestSales !== null
            && $bestProfit !== null
            && (string) ($bestSales['coupon_code'] ?? '')
                !== (string) ($bestProfit['coupon_code'] ?? '')
        ) {
            $insights[] = array(
                'type' => 'success',
                'title' => 'پرفروش‌ترین و پرسودترین کوپن یکی نیستند',
                'description' => 'کد «'
                    . (string) ($bestSales['coupon_code'] ?? '')
                    . '» بیشترین فروش را ساخته، اما «'
                    . (string) ($bestProfit['coupon_code'] ?? '')
                    . '» سود بیشتری باقی گذاشته است.',
            );
        }

        if ((int) ($summary['multi_coupon_order_count'] ?? 0) > 0) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'بعضی سفارش‌ها بیش از یک کوپن دارند',
                'description' => 'در جدول هر کوپن، فروش و سودِ سفارش‌های دارای آن کد نمایش داده می‌شود؛ بنابراین ردیف کوپن‌ها را با هم جمع نکن.',
            );
        }

        if ((int) ($summary['unattributed_discount_order_count'] ?? 0) > 0) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'تخفیف بدون کد کوپن هم دیده شد',
                'description' => 'چند سفارش تخفیف ثبت‌شده دارند اما کد کوپن روی آن‌ها نیست؛ این می‌تواند حاصل ویرایش دستی یا روش تخفیف دیگری باشد.',
            );
        }

        if ($insights === array()) {
            $insights[] = array(
                'type' => 'success',
                'title' => 'کوپن‌ها فعلاً علامت خطر واضحی ندارند',
                'description' => 'فروش، سود و مبلغ تخفیف هر کد را کنار هم ببین؛ فروش بیشتر همیشه به معنی تخفیف بهتر نیست.',
            );
        }

        return array_slice($insights, 0, 5);
    }
}
