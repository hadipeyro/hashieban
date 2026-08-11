<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\CouponDiscountIntelligenceService;
use Hashieban\Security\Capabilities;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class CouponDiscountIntelligencePage
{
    private CouponDiscountIntelligenceService $service;

    public function __construct(
        CouponDiscountIntelligenceService $service
    ) {
        $this->service = $service;
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        list($start, $end, $range) = $this->resolveDateRange();
        $report = $this->service->getReport($start, $end);
        $summary = (array) $report['summary'];
        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];

        wp_localize_script(
            'hashieban-coupon-intelligence',
            'hashiebanCouponIntelligence',
            $this->chartPayload($report, $currency, $precision)
        );
        ?>
        <div class="wrap hb-coupon-page">
            <section class="hb-coupon-hero">
                <div>
                    <span class="hb-coupon-hero__eyebrow">حاشیه‌بان · تخفیف و کوپن</span>
                    <h1>تحلیل تخفیف‌ها و کوپن‌ها</h1>
                    <p>
                        سفارش‌های دارای کوپن را از نظر فروش، مبلغ تخفیف، سود باقی‌مانده و سفارش‌های زیان‌ده مقایسه کن.
                        این گزارش اثر حسابداری تخفیف را نشان می‌دهد؛ ادعا نمی‌کند مشتری بدون تخفیف همان خرید را انجام می‌داد.
                    </p>
                    <div class="hb-coupon-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>سهم سفارش‌های کوپنی: <strong><?php echo esc_html($this->formatPercent($summary['coupon_order_share_percentage'] ?? null)); ?></strong></span>
                    </div>
                </div>
                <div class="hb-coupon-hero__score">
                    <small>سود باقی‌مانده سفارش‌های کوپنی</small>
                    <strong><?php echo esc_html(Currency::formatMinor((int) ($summary['coupon_profit_minor'] ?? 0), $currency, $precision)); ?></strong>
                    <span>پس از تخفیف، هزینه خرید و هزینه‌های مستقیم/ثابت سفارش</span>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <?php if ($this->service->couponsDisabled()) : ?>
                <div class="notice notice-warning hb-coupon-notice">
                    <p><strong>استفاده از کوپن در ووکامرس غیرفعال است.</strong> گزارش سفارش‌های قبلی باقی می‌ماند، اما برای کوپن‌های جدید باید امکان کوپن را در تنظیمات عمومی ووکامرس فعال کنی.</p>
                </div>
            <?php endif; ?>

            <?php if (empty($report['index_ready'])) : ?>
                <section class="hb-coupon-state hb-coupon-state--building">
                    <span class="dashicons dashicons-update"></span>
                    <div>
                        <h2>در حال آماده‌سازی گزارش تخفیف‌ها</h2>
                        <p>شاخص سریع سفارش‌ها برای ذخیره اطلاعات کوپن ارتقا پیدا کرده و حاشیه‌بان سفارش‌های قبلی را دوباره فهرست می‌کند.</p>
                    </div>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hashieban-bulk-tools')); ?>">وضعیت ابزارهای گروهی</a>
                </section>
            <?php else : ?>
                <section class="hb-coupon-kpis">
                    <?php
                    $this->renderKpi('سفارش با کوپن', number_format_i18n((int) ($summary['coupon_order_count'] ?? 0)), 'از ' . number_format_i18n((int) ($summary['order_count'] ?? 0)) . ' سفارش قابل تحلیل');
                    $this->renderMoneyKpi('فروش سفارش‌های کوپنی', (int) ($summary['coupon_revenue_minor'] ?? 0), $currency, $precision, 'فروش واقعی پس از تخفیف و مرجوعی');
                    $this->renderMoneyKpi('تخفیف داده‌شده', (int) ($summary['coupon_discount_minor'] ?? 0), $currency, $precision, 'مبلغ تخفیف ثبت‌شده روی سفارش‌های دارای کوپن');
                    $this->renderMoneyKpi('سود باقی‌مانده', (int) ($summary['coupon_profit_minor'] ?? 0), $currency, $precision, 'سود سفارش بعد از هزینه‌های قابل انتساب');
                    $this->renderKpi('درصد سود سفارش‌های کوپنی', $this->formatPercent($summary['coupon_margin_percentage'] ?? null), 'بدون سرشکن‌کردن هزینه عمومی فروشگاه');
                    $this->renderKpi('سفارش کوپنی زیان‌ده', number_format_i18n((int) ($summary['coupon_loss_order_count'] ?? 0)), 'نیازمند بررسی قبل از تکرار کمپین');
                    ?>
                </section>

                <?php if ((int) ($summary['order_count'] ?? 0) <= 0) : ?>
                    <section class="hb-coupon-state">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <div><h2>برای این بازه سفارشی پیدا نشد</h2><p>یک بازه زمانی بزرگ‌تر انتخاب کن.</p></div>
                    </section>
                <?php else : ?>
                    <section class="hb-coupon-compare-grid">
                        <?php $this->renderComparisonCard('دارای کوپن', (int) ($summary['coupon_order_count'] ?? 0), (int) ($summary['coupon_average_order_minor'] ?? 0), (int) ($summary['coupon_profit_per_order_minor'] ?? 0), $summary['coupon_margin_percentage'] ?? null, $currency, $precision); ?>
                        <?php $this->renderComparisonCard('بدون کوپن', (int) ($summary['no_coupon_order_count'] ?? 0), (int) ($summary['no_coupon_average_order_minor'] ?? 0), (int) ($summary['no_coupon_profit_per_order_minor'] ?? 0), $summary['no_coupon_margin_percentage'] ?? null, $currency, $precision); ?>
                        <article class="hb-coupon-compare hb-coupon-compare--note">
                            <span>اثر حسابداری تخفیف</span>
                            <strong><?php echo esc_html(Currency::formatMinor((int) ($summary['coupon_pre_discount_profit_minor'] ?? 0), $currency, $precision)); ?></strong>
                            <small>سود فرضی همان سفارش‌ها اگر فقط مبلغ تخفیف به درآمد برگردد؛ این عدد پیش‌بینی رفتار مشتری نیست.</small>
                        </article>
                    </section>

                    <section class="hb-coupon-chart-grid">
                        <article class="hb-coupon-card hb-coupon-card--chart">
                            <header><div><h2>فروش و سود هر کوپن</h2><p>کد پرفروش لزوماً کدی نیست که سود بیشتری باقی گذاشته باشد.</p></div></header>
                            <div class="hb-coupon-chart-wrap"><canvas id="hashieban-coupon-value-chart"></canvas></div>
                        </article>
                        <article class="hb-coupon-card hb-coupon-card--chart">
                            <header><div><h2>تخفیف در برابر سود باقی‌مانده</h2><p>برای هر کد ببین چه مقدار تخفیف داده شده و چه مقدار سود باقی مانده است.</p></div></header>
                            <div class="hb-coupon-chart-wrap"><canvas id="hashieban-coupon-pressure-chart"></canvas></div>
                        </article>
                    </section>

                    <section class="hb-coupon-card hb-coupon-card--table">
                        <header>
                            <div>
                                <h2>مقایسه کدهای تخفیف</h2>
                                <p>اگر یک سفارش چند کوپن داشته باشد، فروش و سود آن سفارش در ردیف هر کد دیده می‌شود؛ ردیف‌ها را با هم جمع نکن.</p>
                            </div>
                        </header>
                        <div class="hb-coupon-table-scroll">
                            <table class="widefat striped">
                                <thead><tr><th>کد</th><th>سفارش</th><th>فروش سفارش‌ها</th><th>تخفیف</th><th>سود باقی‌مانده</th><th>درصد سود</th><th>شدت تخفیف</th><th>زیان‌ده</th><th>وضعیت</th></tr></thead>
                                <tbody>
                                <?php if ((array) $report['coupons'] === array()) : ?>
                                    <tr><td colspan="9">در این بازه کد تخفیفی استفاده نشده است.</td></tr>
                                <?php else : ?>
                                    <?php foreach ((array) $report['coupons'] as $coupon) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html((string) ($coupon['coupon_code'] ?? '—')); ?></strong></td>
                                            <td><?php echo esc_html(number_format_i18n((int) ($coupon['order_count'] ?? 0))); ?></td>
                                            <td><?php echo esc_html(Currency::formatMinor((int) ($coupon['revenue_minor'] ?? 0), $currency, $precision)); ?></td>
                                            <td><?php echo esc_html(Currency::formatMinor((int) ($coupon['coupon_discount_minor'] ?? 0), $currency, $precision)); ?></td>
                                            <td class="<?php echo (int) ($coupon['profit_minor'] ?? 0) < 0 ? 'is-negative' : 'is-positive'; ?>"><?php echo esc_html(Currency::formatMinor((int) ($coupon['profit_minor'] ?? 0), $currency, $precision)); ?></td>
                                            <td><?php echo esc_html($this->formatPercent($coupon['margin_percentage'] ?? null)); ?></td>
                                            <td><?php echo esc_html($this->formatPercent($coupon['discount_rate_percentage'] ?? null)); ?></td>
                                            <td><?php echo esc_html(number_format_i18n((int) ($coupon['loss_order_count'] ?? 0))); ?></td>
                                            <td><?php echo wp_kses_post($this->statusBadge((string) ($coupon['status'] ?? 'empty'))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <?php $this->renderInsights((array) $report['insights']); ?>
                    <?php $this->renderRiskyOrders((array) $report['risky_orders'], $currency, $precision); ?>

                    <section class="hb-coupon-footnote">
                        <strong>مرز تحلیل:</strong>
                        حاشیه‌بان در این نسخه تخفیف ثبت‌شده روی سفارش و کدهای کوپن را تحلیل می‌کند. قیمت عادی تاریخی یک محصول در برابر قیمت Sale اگر در سفارش ذخیره نشده باشد، از روی قیمت امروز محصول بازسازی نمی‌شود.
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderKpi(string $label, string $value, string $description): void
    {
        ?>
        <article class="hb-coupon-kpi"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong><small><?php echo esc_html($description); ?></small></article>
        <?php
    }

    private function renderMoneyKpi(string $label, int $amount, string $currency, int $precision, string $description): void
    {
        $this->renderKpi($label, Currency::formatMinor($amount, $currency, $precision), $description);
    }

    private function renderComparisonCard(string $title, int $orders, int $averageOrder, int $profitPerOrder, $margin, string $currency, int $precision): void
    {
        ?>
        <article class="hb-coupon-compare">
            <span><?php echo esc_html($title); ?></span>
            <strong><?php echo esc_html(number_format_i18n($orders)); ?> سفارش</strong>
            <dl>
                <div><dt>میانگین مبلغ سفارش</dt><dd><?php echo esc_html(Currency::formatMinor($averageOrder, $currency, $precision)); ?></dd></div>
                <div><dt>سود به‌ازای سفارش</dt><dd><?php echo esc_html(Currency::formatMinor($profitPerOrder, $currency, $precision)); ?></dd></div>
                <div><dt>درصد سود</dt><dd><?php echo esc_html($this->formatPercent($margin)); ?></dd></div>
            </dl>
        </article>
        <?php
    }

    private function renderInsights(array $insights): void
    {
        if ($insights === array()) {
            return;
        }
        ?>
        <section class="hb-coupon-insights">
            <?php foreach ($insights as $insight) : $type = (string) ($insight['type'] ?? 'info'); ?>
                <article class="hb-coupon-insight hb-coupon-insight--<?php echo esc_attr($type); ?>">
                    <span class="dashicons <?php echo esc_attr($this->insightIcon($type)); ?>"></span>
                    <div><strong><?php echo esc_html((string) ($insight['title'] ?? '')); ?></strong><p><?php echo esc_html((string) ($insight['description'] ?? '')); ?></p></div>
                </article>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private function renderRiskyOrders(array $rows, string $currency, int $precision): void
    {
        if ($rows === array()) {
            return;
        }
        ?>
        <section class="hb-coupon-card hb-coupon-card--table">
            <header><div><h2>سفارش‌های کوپنی که اول باید بررسی شوند</h2><p>سفارش‌های زیان‌ده و سپس سفارش‌های با سود پایین‌تر در اولویت آمده‌اند.</p></div></header>
            <div class="hb-coupon-table-scroll">
                <table class="widefat striped">
                    <thead><tr><th>سفارش</th><th>تاریخ</th><th>فروش</th><th>تخفیف</th><th>سود</th><th>درصد سود</th><th>کوپن</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td>#<?php echo esc_html(number_format_i18n((int) ($row['order_id'] ?? 0))); ?></td>
                            <td><?php echo esc_html($this->formatDate((string) ($row['order_date_local'] ?? ''))); ?></td>
                            <td><?php echo esc_html(Currency::formatMinor((int) ($row['revenue_minor'] ?? 0), $currency, $precision)); ?></td>
                            <td><?php echo esc_html(Currency::formatMinor((int) ($row['discount_minor'] ?? 0), $currency, $precision)); ?></td>
                            <td class="<?php echo (int) ($row['profit_minor'] ?? 0) < 0 ? 'is-negative' : 'is-positive'; ?>"><?php echo esc_html(Currency::formatMinor((int) ($row['profit_minor'] ?? 0), $currency, $precision)); ?></td>
                            <td><?php echo esc_html($this->formatPercent($row['margin_percentage'] ?? null)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) ($row['coupon_count'] ?? 0))); ?> کد</td>
                            <td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-orders', 'order_id' => (int) ($row['order_id'] ?? 0)), admin_url('admin.php'))); ?>">جزئیات سفارش</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
    }

    private function renderRangeFilters(string $range, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $ranges = array('7d' => '۷ روز', '30d' => '۳۰ روز', '90d' => '۹۰ روز', '6m' => '۶ ماه', 'year' => '۱ سال', 'all' => 'همه');
        ?>
        <section class="hb-coupon-range">
            <nav>
                <?php foreach ($ranges as $key => $label) : ?>
                    <a class="<?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-coupons', 'range' => $key), admin_url('admin.php'))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <form method="get">
                <input type="hidden" name="page" value="hashieban-coupons"><input type="hidden" name="range" value="custom">
                <label>از <input type="text" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>" autocomplete="off" data-jdp></label>
                <label>تا <input type="text" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>" autocomplete="off" data-jdp></label>
                <button type="submit" class="button button-primary">اعمال</button>
            </form>
        </section>
        <?php
    }

    private function chartPayload(array $report, string $currency, int $precision): array
    {
        $labels = array();
        $sales = array();
        $profits = array();
        $discounts = array();

        foreach (array_slice((array) $report['coupons'], 0, 12) as $coupon) {
            $labels[] = (string) ($coupon['coupon_code'] ?? '—');
            $sales[] = Currency::minorToDisplayNumber((int) ($coupon['revenue_minor'] ?? 0), $currency, $precision);
            $profits[] = Currency::minorToDisplayNumber((int) ($coupon['profit_minor'] ?? 0), $currency, $precision);
            $discounts[] = Currency::minorToDisplayNumber((int) ($coupon['coupon_discount_minor'] ?? 0), $currency, $precision);
        }

        return array('currencyLabel' => Currency::label($currency), 'labels' => $labels, 'sales' => $sales, 'profits' => $profits, 'discounts' => $discounts);
    }

    private function resolveDateRange(): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $end = $now->setTime(23, 59, 59);
        $range = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '30d';

        switch ($range) {
            case '7d': $start = $now->modify('-6 days')->setTime(0, 0, 0); break;
            case '90d': $start = $now->modify('-89 days')->setTime(0, 0, 0); break;
            case '6m': $start = $now->modify('-6 months')->setTime(0, 0, 0); break;
            case 'year': $start = $now->modify('-1 year')->setTime(0, 0, 0); break;
            case 'all': $start = $this->resolveAllTimeStart($now); break;
            case 'custom':
                $custom = $this->resolveCustomRange();
                if ($custom !== null) { return array($custom[0], $custom[1], 'custom'); }
                $range = '30d';
                $start = $now->modify('-29 days')->setTime(0, 0, 0);
                break;
            case '30d':
            default:
                $range = '30d';
                $start = $now->modify('-29 days')->setTime(0, 0, 0);
                break;
        }

        return array($start, $end, $range);
    }

    private function resolveCustomRange(): ?array
    {
        $startValue = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $endValue = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';
        if ($startValue === '' || $endValue === '') { return null; }
        $gregorianStart = JalaliDate::parseInputToGregorianYmd($startValue);
        $gregorianEnd = JalaliDate::parseInputToGregorianYmd($endValue);
        if ($gregorianStart === null || $gregorianEnd === null) { return null; }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorianStart, wp_timezone());
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorianEnd, wp_timezone());
        if (! $start || ! $end || $start > $end) { return null; }
        return array($start->setTime(0, 0, 0), $end->setTime(23, 59, 59));
    }

    private function resolveAllTimeStart(DateTimeImmutable $fallback): DateTimeImmutable
    {
        $orders = wc_get_orders(array('status' => array('processing', 'completed', 'refunded'), 'limit' => 1, 'orderby' => 'date', 'order' => 'ASC'));
        if (is_array($orders) && isset($orders[0]) && $orders[0] instanceof \WC_Order) {
            $date = $orders[0]->get_date_created();
            if ($date) { return (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone(wp_timezone())->setTime(0, 0, 0); }
        }
        return $fallback->modify('-1 year')->setTime(0, 0, 0);
    }

    private function statusBadge(string $status): string
    {
        $labels = array('healthy' => 'متعادل', 'pressure' => 'فشار تخفیف بالا', 'fragile' => 'لب مرز', 'loss' => 'زیان‌ده', 'empty' => 'بدون داده');
        $class = in_array($status, array('healthy', 'pressure', 'fragile', 'loss'), true) ? $status : 'empty';
        return '<span class="hb-coupon-badge hb-coupon-badge--' . esc_attr($class) . '">' . esc_html($labels[$class] ?? 'بدون داده') . '</span>';
    }

    private function insightIcon(string $type): string
    {
        $icons = array('success' => 'dashicons-yes-alt', 'warning' => 'dashicons-warning', 'danger' => 'dashicons-dismiss', 'info' => 'dashicons-info-outline');
        return $icons[$type] ?? 'dashicons-info-outline';
    }

    private function formatPercent($value): string
    {
        return is_numeric($value) ? number_format_i18n((float) $value, 1) . '٪' : '—';
    }

    private function formatDate(string $value): string
    {
        if ($value === '') { return '—'; }
        try { return JalaliDate::format(new DateTimeImmutable($value, wp_timezone())); } catch (\Throwable $exception) { return $value; }
    }
}
