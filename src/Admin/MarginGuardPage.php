<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Json;
use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\MarginGuardService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class MarginGuardPage
{
    private const MARGIN_OPTION = 'hashieban_margin_guard_threshold';
    private const PROFIT_DROP_OPTION = 'hashieban_profit_drop_threshold';
    private const RETURN_RATE_OPTION = 'hashieban_return_rate_threshold';

    private MarginGuardService $guard;

    public function __construct(MarginGuardService $guard)
    {
        $this->guard = $guard;
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        $saved = $this->handleSettingsSave();
        list($start, $end, $range) = $this->resolveDateRange();

        $marginThreshold = $this->optionFloat(self::MARGIN_OPTION, 20.0, 0.0, 100.0);
        $profitDropThreshold = $this->optionFloat(self::PROFIT_DROP_OPTION, 15.0, 1.0, 100.0);
        $returnRateThreshold = $this->optionFloat(self::RETURN_RATE_OPTION, 15.0, 1.0, 100.0);

        $report = $this->guard->getReport(
            $start,
            $end,
            $marginThreshold,
            $profitDropThreshold,
            $returnRateThreshold
        );

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $payload = $this->buildChartPayload($report);
        ?>
        <div class="wrap hb-guard-page">
            <section class="hb-guard-hero">
                <div>
                    <div class="hb-guard-hero__eyebrow">حاشیه‌بان · هشدارهای سود</div>
                    <h1>هشدارهای سود و فروش</h1>
                    <p>
                        حاشیه‌بان به‌صورت خودکار سفارش‌های زیان‌ده، محصولات کم‌حاشیه، افت سود،
                        هزینه خرید ناقص و نرخ مرجوعی غیرعادی را از دل داده فروشگاه بیرون می‌کشد.
                    </p>
                    <div class="hb-guard-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>حداقل درصد سود: <strong><?php echo esc_html(number_format_i18n($marginThreshold, 1)); ?>٪</strong></span>
                    </div>
                </div>

                <div class="hb-guard-score <?php echo $this->scoreClass((int) $report['health_score']); ?>">
                    <span>امتیاز سلامت مالی</span>
                    <strong><?php echo esc_html(number_format_i18n((int) $report['health_score'])); ?></strong>
                    <small>از ۱۰۰ · بر اساس ریسک‌های کشف‌شده در بازه</small>
                </div>
            </section>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>آستانه‌های هشدار ذخیره شدند.</p></div>
            <?php endif; ?>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <section class="hb-guard-control-card">
                <div>
                    <h2>آستانه‌های هشدار</h2>
                    <p>این اعداد فقط رفتار هشدار را تغییر می‌دهند و هیچ داده مالی را دستکاری نمی‌کنند.</p>
                </div>
                <?php if (Capabilities::can(Capabilities::MANAGE_SETTINGS)) : ?>
                    <form method="post" class="hb-guard-threshold-form">
                        <?php wp_nonce_field('hashieban_margin_guard_save', 'hashieban_margin_guard_nonce'); ?>
                        <input type="hidden" name="hashieban_margin_guard_action" value="save">
                        <label>حداقل درصد سود قابل قبول
                            <input type="number" step="0.1" min="0" max="100" name="margin_threshold" value="<?php echo esc_attr((string) $marginThreshold); ?>"> ٪
                        </label>
                        <label>هشدار افت سود
                            <input type="number" step="0.1" min="1" max="100" name="profit_drop_threshold" value="<?php echo esc_attr((string) $profitDropThreshold); ?>"> ٪
                        </label>
                        <label>هشدار نرخ مرجوعی
                            <input type="number" step="0.1" min="1" max="100" name="return_rate_threshold" value="<?php echo esc_attr((string) $returnRateThreshold); ?>"> ٪
                        </label>
                        <button type="submit" class="button button-primary">ذخیره آستانه‌ها</button>
                    </form>
                <?php else : ?>
                    <div class="hb-guard-threshold-form">دسترسی شما فقط خواندنی است و امکان تغییر آستانه‌های هشدار را ندارید.</div>
                <?php endif; ?>
            </section>

            <section class="hb-guard-kpis">
                <?php
                $counts = (array) $report['severity_counts'];
                $this->renderKpi('هشدار بحرانی', number_format_i18n((int) $counts['critical']), 'نیازمند بررسی سریع', (int) $counts['critical'] > 0 ? 'danger' : 'good');
                $this->renderKpi('هشدار مهم', number_format_i18n((int) $counts['warning']), 'ریسک‌هایی که بهتر است پیگیری شوند', (int) $counts['warning'] > 0 ? 'warning' : 'good');
                $this->renderKpi('فروش با درصد سود پایین', Currency::formatMinor((int) $report['risk_revenue_minor'], $currency, $precision), 'فروش محصولات کم‌حاشیه یا زیان‌ده', 'neutral');
                $this->renderKpi('تغییر سود', $this->formatDelta($report['profit_change_percentage']), 'در مقایسه با دوره هم‌اندازه قبل', $this->deltaClass($report['profit_change_percentage']));
                ?>
            </section>

            <section class="hb-guard-grid hb-guard-grid--charts">
                <article class="hb-guard-card hb-guard-card--chart">
                    <div class="hb-guard-card__header">
                        <div><h2>ترکیب هشدارها</h2><p>شدت ریسک‌های فعال در بازه</p></div>
                    </div>
                    <div class="hb-guard-chart-wrap"><canvas id="hashieban-guard-severity-chart"></canvas></div>
                </article>

                <article class="hb-guard-card hb-guard-card--chart">
                    <div class="hb-guard-card__header">
                        <div><h2>محصولات با درصد سود پایین</h2><p>کمترین درصدهای سود نسبت به حد فعلی</p></div>
                    </div>
                    <div class="hb-guard-chart-wrap"><canvas id="hashieban-guard-margin-chart"></canvas></div>
                </article>
            </section>

            <section class="hb-guard-card">
                <div class="hb-guard-card__header">
                    <div><h2>مرکز هشدارهای مدیریتی</h2><p>هشدارها بر اساس داده واقعی همین بازه ساخته شده‌اند.</p></div>
                    <span class="hb-guard-chip"><?php echo esc_html(number_format_i18n(count((array) $report['alerts']))); ?> هشدار</span>
                </div>
                <div class="hb-guard-alert-list">
                    <?php foreach ((array) $report['alerts'] as $alert) : ?>
                        <article class="hb-guard-alert hb-guard-alert--<?php echo esc_attr((string) $alert['severity']); ?>">
                            <div class="hb-guard-alert__icon"><?php echo esc_html($this->severityIcon((string) $alert['severity'])); ?></div>
                            <div class="hb-guard-alert__body">
                                <h3><?php echo esc_html((string) $alert['title']); ?></h3>
                                <p><?php echo esc_html((string) $alert['message']); ?></p>
                            </div>
                            <div class="hb-guard-alert__metric"><?php echo esc_html((string) $alert['metric']); ?></div>
                            <?php if ((string) $alert['url'] !== '') : ?>
                                <a class="button" href="<?php echo esc_url((string) $alert['url']); ?>">بررسی</a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hb-guard-grid">
                <?php $this->renderProductRiskTable('محصولات کم‌حاشیه', (array) $report['low_margin_products'], $currency, $precision, 'margin'); ?>
                <?php $this->renderOrderRiskTable((array) $report['loss_orders'], $currency, $precision); ?>
            </section>

            <section class="hb-guard-grid">
                <?php $this->renderProductRiskTable('هزینه خرید ناقص', (array) $report['missing_cogs_products'], $currency, $precision, 'cogs'); ?>
                <?php $this->renderProductRiskTable('مرجوعی بالا', (array) $report['high_return_products'], $currency, $precision, 'returns'); ?>
            </section>

            <script id="hashieban-margin-guard-data" type="application/json"><?php echo Json::forHtmlScript($payload); ?></script>
        </div>
        <?php
    }

    private function handleSettingsSave(): bool
    {
        if (
            ! isset($_POST['hashieban_margin_guard_action'])
            || sanitize_key(wp_unslash($_POST['hashieban_margin_guard_action'])) !== 'save'
        ) {
            return false;
        }

        if (! Capabilities::can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html('شما اجازه تغییر تنظیمات هشدارهای حاشیه‌بان را ندارید.'));
        }

        if (
            ! isset($_POST['hashieban_margin_guard_nonce'])
            || ! wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['hashieban_margin_guard_nonce'])),
                'hashieban_margin_guard_save'
            )
        ) {
            return false;
        }

        $margin = isset($_POST['margin_threshold']) ? (float) wp_unslash($_POST['margin_threshold']) : 20.0;
        $profitDrop = isset($_POST['profit_drop_threshold']) ? (float) wp_unslash($_POST['profit_drop_threshold']) : 15.0;
        $returnRate = isset($_POST['return_rate_threshold']) ? (float) wp_unslash($_POST['return_rate_threshold']) : 15.0;

        update_option(self::MARGIN_OPTION, max(0.0, min(100.0, $margin)));
        update_option(self::PROFIT_DROP_OPTION, max(1.0, min(100.0, $profitDrop)));
        update_option(self::RETURN_RATE_OPTION, max(1.0, min(100.0, $returnRate)));

        return true;
    }

    private function optionFloat(string $option, float $default, float $min, float $max): float
    {
        $value = (float) get_option($option, $default);
        return max($min, min($max, $value));
    }

    private function renderKpi(string $label, string $value, string $help, string $tone): void
    {
        ?>
        <article class="hb-guard-kpi hb-guard-kpi--<?php echo esc_attr($tone); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function renderProductRiskTable(string $title, array $rows, string $currency, int $precision, string $mode): void
    {
        ?>
        <article class="hb-guard-card hb-guard-table-card">
            <div class="hb-guard-card__header"><div><h2><?php echo esc_html($title); ?></h2><p>موارد اولویت‌دار برای بررسی</p></div></div>
            <?php if ($rows === array()) : ?>
                <div class="hb-guard-empty">موردی در این دسته پیدا نشد.</div>
            <?php else : ?>
                <div class="hb-guard-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>محصول</th><th>فروش</th><th>سود</th><th>شاخص ریسک</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td>
                                    <?php if ((string) $row['edit_url'] !== '') : ?>
                                        <a href="<?php echo esc_url((string) $row['edit_url']); ?>"><strong><?php echo esc_html((string) $row['name']); ?></strong></a>
                                    <?php else : ?>
                                        <strong><?php echo esc_html((string) $row['name']); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(Currency::formatMinor((int) $row['revenue_minor'], $currency, $precision)); ?></td>
                                <td class="<?php echo (int) $row['profit_minor'] < 0 ? 'is-negative' : 'is-positive'; ?>"><?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?></td>
                                <td><?php echo esc_html($this->productRiskLabel($row, $mode)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderOrderRiskTable(array $rows, string $currency, int $precision): void
    {
        ?>
        <article class="hb-guard-card hb-guard-table-card">
            <div class="hb-guard-card__header"><div><h2>سفارش‌های زیان‌ده</h2><p>بدترین سفارش‌ها از نظر سود</p></div></div>
            <?php if ($rows === array()) : ?>
                <div class="hb-guard-empty">سفارش زیان‌دهی در این بازه نیست.</div>
            <?php else : ?>
                <div class="hb-guard-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>سفارش</th><th>مشتری</th><th>فروش</th><th>سود</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-orders&order_id=' . (int) $row['order_id'])); ?>">#<?php echo esc_html((string) $row['order_number']); ?></a></td>
                                <td><?php echo esc_html((string) $row['customer_name']); ?></td>
                                <td><?php echo esc_html(Currency::formatMinor((int) $row['revenue_minor'], $currency, $precision)); ?></td>
                                <td class="is-negative"><?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function productRiskLabel(array $row, string $mode): string
    {
        if ($mode === 'cogs') {
            return number_format_i18n((int) $row['missing_cogs_lines']) . ' ردیف هزینه خرید ناقص';
        }
        if ($mode === 'returns') {
            return $this->formatPercentage($row['return_rate_percentage']) . ' مرجوعی';
        }
        return $this->formatPercentage($row['margin_percentage']) . ' درصد سود';
    }

    private function buildChartPayload(array $report): array
    {
        $lowMargin = array_slice((array) $report['low_margin_products'], 0, 8);
        return array(
            'severity' => array(
                'labels' => array('بحرانی', 'مهم', 'بدون هشدار'),
                'values' => array(
                    (int) $report['severity_counts']['critical'],
                    (int) $report['severity_counts']['warning'],
                    (int) $report['severity_counts']['good'],
                ),
            ),
            'margin' => array(
                'threshold' => (float) $report['margin_threshold'],
                'labels' => array_map(static function (array $row): string { return (string) $row['name']; }, $lowMargin),
                'values' => array_map(static function (array $row): float { return (float) $row['margin_percentage']; }, $lowMargin),
            ),
        );
    }

    private function renderRangeFilters(string $range, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $ranges = array('7d' => '۷ روز', '30d' => '۳۰ روز', '90d' => '۹۰ روز', '6m' => '۶ ماه', 'year' => '۱ سال', 'all' => 'همه زمان‌ها');
        ?>
        <div class="hb-guard-range-card">
            <div class="hb-guard-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a class="<?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-alerts', 'range' => $key), admin_url('admin.php'))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <form method="get" class="hb-guard-custom-range">
                <input type="hidden" name="page" value="hashieban-alerts">
                <input type="hidden" name="range" value="custom">
                <label>از <input type="text" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>" autocomplete="off" data-jdp></label>
                <label>تا <input type="text" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>" autocomplete="off" data-jdp></label>
                <button type="submit" class="button button-primary">اعمال</button>
            </form>
        </div>
        <?php
    }

    private function resolveDateRange(): array
    {
        $now = new DateTimeImmutable('now', wp_timezone());
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
        return array($start->setTime(0,0,0), $end->setTime(23,59,59));
    }

    private function resolveAllTimeStart(DateTimeImmutable $fallback): DateTimeImmutable
    {
        $orders = wc_get_orders(array('status' => array('processing','completed','refunded'), 'limit' => 1, 'orderby' => 'date', 'order' => 'ASC'));
        if (is_array($orders) && isset($orders[0]) && $orders[0] instanceof \WC_Order) {
            $date = $orders[0]->get_date_created();
            if ($date) {
                return (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone(wp_timezone())->setTime(0,0,0);
            }
        }
        return $fallback->modify('-3 years')->setTime(0,0,0);
    }

    private function scoreClass(int $score): string
    {
        if ($score < 50) { return 'is-danger'; }
        if ($score < 75) { return 'is-warning'; }
        return 'is-good';
    }

    private function severityIcon(string $severity): string
    {
        if ($severity === 'critical') { return '!'; }
        if ($severity === 'warning') { return '⚠'; }
        return '✓';
    }

    private function formatPercentage($value): string
    {
        return $value === null ? '—' : number_format_i18n((float) $value, 1) . '٪';
    }

    private function formatDelta($value): string
    {
        if ($value === null) { return '—'; }
        $prefix = (float) $value > 0 ? '+' : '';
        return $prefix . number_format_i18n((float) $value, 1) . '٪';
    }

    private function deltaClass($value): string
    {
        if ($value === null) { return 'neutral'; }
        return (float) $value < 0 ? 'danger' : 'good';
    }
}
