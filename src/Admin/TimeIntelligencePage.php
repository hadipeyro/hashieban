<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\TimeIntelligenceService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class TimeIntelligencePage
{
    private TimeIntelligenceService $analytics;

    public function __construct(TimeIntelligenceService $analytics)
    {
        $this->analytics = $analytics;
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        list($start, $end, $range) = $this->resolveDateRange();
        $report = $this->analytics->getReport($start, $end);

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $comparison = (array) $report['comparison'];
        $payload = $this->buildChartPayload($report, $currency, $precision);

        ?>
        <div class="wrap hb-time-page">
            <section class="hb-time-hero">
                <div>
                    <span class="hb-time-hero__eyebrow">حاشیه‌بان BI · Time Intelligence</span>
                    <h1>هوش زمانی فروش و سود</h1>
                    <p>
                        روند واقعی فروش و سود را در طول زمان ببین، دوره فعلی را با دوره قبل مقایسه کن
                        و بهترین روزها، روزهای هفته و الگوهای فصلی کسب‌وکارت را پیدا کن.
                    </p>
                    <div class="hb-time-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>مقایسه با: <strong><?php echo esc_html(JalaliDate::format($comparison['previous_start']) . ' تا ' . JalaliDate::format($comparison['previous_end'])); ?></strong></span>
                    </div>
                </div>
                <div class="hb-time-hero__score">
                    <small>سود خالص بازه</small>
                    <strong><?php echo esc_html(Currency::formatMinor((int) $report['net_profit_minor'], $currency, $precision)); ?></strong>
                    <?php $this->renderDeltaBadge($comparison['profit_change_percentage'], 'نسبت به دوره قبل'); ?>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <?php if ((int) $report['orders_with_refunds'] > 0) : ?>
                <div class="hb-time-notice hb-time-notice--warning">
                    <strong>Refund:</strong>
                    <?php echo esc_html(number_format_i18n((int) $report['orders_with_refunds'])); ?> سفارش در این بازه بازپرداخت دارد؛ موتور کامل Refund در مرحله اختصاصی تکمیل می‌شود.
                </div>
            <?php endif; ?>

            <?php if ((int) $report['incomplete_orders'] > 0) : ?>
                <div class="hb-time-notice hb-time-notice--info">
                    <strong>داده ناقص:</strong>
                    <?php echo esc_html(number_format_i18n((int) $report['incomplete_orders'])); ?> سفارش اطلاعات مالی کامل ندارد.
                </div>
            <?php endif; ?>

            <section class="hb-time-kpis">
                <?php
                $this->renderKpi(
                    'فروش',
                    Currency::formatMinor((int) $report['total_revenue_minor'], $currency, $precision),
                    $comparison['revenue_change_percentage'],
                    'درآمد خالص سفارش‌ها در بازه'
                );
                $this->renderKpi(
                    'سود خالص',
                    Currency::formatMinor((int) $report['net_profit_minor'], $currency, $precision),
                    $comparison['profit_change_percentage'],
                    'پس از هزینه‌های فروشگاه و سفارش'
                );
                $this->renderKpi(
                    'تعداد سفارش',
                    number_format_i18n((int) $report['order_count']),
                    $comparison['orders_change_percentage'],
                    'سفارش‌های processing و completed'
                );
                $this->renderKpi(
                    'حاشیه سود',
                    $this->formatPercentage($report['margin_percentage']),
                    $comparison['margin_change_points'],
                    'تغییر این کارت بر حسب واحد درصد است',
                    true
                );
                ?>
            </section>

            <section class="hb-time-insights">
                <?php
                $this->renderInsightDay(
                    'بیشترین فروش روزانه',
                    $report['best_revenue_day'],
                    'revenue_minor',
                    $currency,
                    $precision,
                    'sales'
                );
                $this->renderInsightDay(
                    'بیشترین سود روزانه',
                    $report['best_profit_day'],
                    'profit_minor',
                    $currency,
                    $precision,
                    'profit'
                );
                $this->renderInsightWeekday(
                    'بهترین روز هفته',
                    $report['best_weekday'],
                    $currency,
                    $precision
                );
                $this->renderInsightDay(
                    'ضعیف‌ترین روز سود',
                    $report['worst_profit_day'],
                    'profit_minor',
                    $currency,
                    $precision,
                    'risk'
                );
                ?>
            </section>

            <section class="hb-time-chart-grid hb-time-chart-grid--wide">
                <div class="hb-time-card hb-time-card--chart hb-time-card--wide">
                    <div class="hb-time-card__header">
                        <div>
                            <h2>روند فروش و سود</h2>
                            <p><?php echo esc_html($this->timelineDescription((string) $report['timeline_mode'])); ?></p>
                        </div>
                        <span class="hb-time-chip">Timeline</span>
                    </div>
                    <div class="hb-time-chart-wrap hb-time-chart-wrap--large">
                        <canvas id="hashieban-time-trend-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-time-chart-grid">
                <div class="hb-time-card hb-time-card--chart">
                    <div class="hb-time-card__header">
                        <div>
                            <h2>دوره فعلی در برابر دوره قبل</h2>
                            <p>مقایسه مستقیم فروش و سود در دو بازه هم‌اندازه</p>
                        </div>
                    </div>
                    <div class="hb-time-chart-wrap">
                        <canvas id="hashieban-time-comparison-chart"></canvas>
                    </div>
                </div>

                <div class="hb-time-card hb-time-card--chart">
                    <div class="hb-time-card__header">
                        <div>
                            <h2>عملکرد روزهای هفته</h2>
                            <p>میانگین روزانه فروش و سود برای شنبه تا جمعه</p>
                        </div>
                    </div>
                    <div class="hb-time-chart-wrap">
                        <canvas id="hashieban-time-weekday-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-time-chart-grid hb-time-chart-grid--wide">
                <div class="hb-time-card hb-time-card--chart hb-time-card--wide">
                    <div class="hb-time-card__header">
                        <div>
                            <h2>الگوی فصلی ماه‌های شمسی</h2>
                            <p>میانگین فروش و سود هر ماه شمسی در سال‌های موجود در بازه</p>
                        </div>
                        <span class="hb-time-chip">Seasonality</span>
                    </div>
                    <div class="hb-time-chart-wrap">
                        <canvas id="hashieban-time-seasonality-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-time-card hb-time-table-card">
                <div class="hb-time-card__header">
                    <div>
                        <h2>دفتر زمانی عملکرد</h2>
                        <p>جزئیات فروش، سود، سفارش و Margin در Bucketهای زمانی نمودار</p>
                    </div>
                    <span class="hb-time-chip"><?php echo esc_html(number_format_i18n(count((array) $report['timeline']))); ?> بازه</span>
                </div>

                <div class="hb-time-table-wrap">
                    <table class="widefat striped hb-time-table">
                        <thead>
                            <tr>
                                <th>زمان</th>
                                <th>فروش</th>
                                <th>سود</th>
                                <th>سفارش</th>
                                <th>Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) $report['timeline'] as $row) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html((string) $row['label']); ?></strong></td>
                                    <td><?php echo esc_html(Currency::formatMinor((int) $row['revenue_minor'], $currency, $precision)); ?></td>
                                    <td class="<?php echo (int) $row['profit_minor'] < 0 ? 'is-negative' : 'is-positive'; ?>">
                                        <?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n((int) $row['order_count'])); ?></td>
                                    <td><?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <script id="hashieban-time-intelligence-data" type="application/json"><?php echo wp_json_encode($payload); ?></script>
        </div>
        <?php
    }

    private function renderKpi(
        string $label,
        string $value,
        $delta,
        string $help,
        bool $points = false
    ): void {
        ?>
        <div class="hb-time-kpi">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <?php $this->renderDeltaBadge($delta, $points ? 'واحد درصد' : 'نسبت به دوره قبل', $points); ?>
            <small><?php echo esc_html($help); ?></small>
        </div>
        <?php
    }

    private function renderDeltaBadge($value, string $suffix, bool $points = false): void
    {
        if ($value === null) {
            ?>
            <span class="hb-time-delta hb-time-delta--neutral">دوره قبل مبنای مقایسه ندارد</span>
            <?php
            return;
        }

        $number = (float) $value;
        $class = $number > 0 ? 'positive' : ($number < 0 ? 'negative' : 'neutral');
        $arrow = $number > 0 ? '↑' : ($number < 0 ? '↓' : '•');
        $formatted = number_format_i18n(abs($number), 1) . ($points ? '' : '٪');
        ?>
        <span class="hb-time-delta hb-time-delta--<?php echo esc_attr($class); ?>">
            <?php echo esc_html($arrow . ' ' . $formatted . ' ' . $suffix); ?>
        </span>
        <?php
    }

    private function renderInsightDay(
        string $title,
        $row,
        string $field,
        string $currency,
        int $precision,
        string $type
    ): void {
        if (! is_array($row)) {
            $label = 'داده‌ای وجود ندارد';
            $value = '—';
            $meta = '—';
        } else {
            $label = (string) $row['full_label'];
            $value = Currency::formatMinor((int) $row[$field], $currency, $precision);
            $meta = number_format_i18n((int) $row['order_count']) . ' سفارش';
        }
        ?>
        <div class="hb-time-insight hb-time-insight--<?php echo esc_attr($type); ?>">
            <span><?php echo esc_html($title); ?></span>
            <strong><?php echo esc_html($label); ?></strong>
            <b><?php echo esc_html($value); ?></b>
            <small><?php echo esc_html($meta); ?></small>
        </div>
        <?php
    }

    private function renderInsightWeekday(
        string $title,
        $row,
        string $currency,
        int $precision
    ): void {
        if (! is_array($row)) {
            $label = 'داده‌ای وجود ندارد';
            $value = '—';
            $meta = '—';
        } else {
            $label = (string) $row['label'];
            $value = Currency::formatMinor((int) $row['average_profit_minor'], $currency, $precision);
            $meta = 'میانگین سود روزانه · ' . number_format_i18n((int) $row['order_count']) . ' سفارش';
        }
        ?>
        <div class="hb-time-insight hb-time-insight--weekday">
            <span><?php echo esc_html($title); ?></span>
            <strong><?php echo esc_html($label); ?></strong>
            <b><?php echo esc_html($value); ?></b>
            <small><?php echo esc_html($meta); ?></small>
        </div>
        <?php
    }

    private function renderRangeFilters(
        string $range,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): void {
        $ranges = array(
            '7d' => '۷ روز',
            '30d' => '۳۰ روز',
            '90d' => '۹۰ روز',
            '6m' => '۶ ماه',
            'year' => '۱ سال',
            '2y' => '۲ سال',
            '3y' => '۳ سال',
            'all' => 'همه زمان‌ها',
        );
        ?>
        <div class="hb-time-range-card">
            <div class="hb-time-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-time', 'range' => $key), admin_url('admin.php'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-time-custom-range">
                <input type="hidden" name="page" value="hashieban-time">
                <input type="hidden" name="range" value="custom">
                <label>از <input type="text" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>" autocomplete="off" data-jdp></label>
                <label>تا <input type="text" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>" autocomplete="off" data-jdp></label>
                <button type="submit" class="button button-primary">اعمال</button>
            </form>
        </div>
        <?php
    }

    private function buildChartPayload(
        array $report,
        string $currency,
        int $precision
    ): array {
        $timelineLabels = array();
        $timelineRevenue = array();
        $timelineProfit = array();
        $timelineOrders = array();

        foreach ((array) $report['timeline'] as $row) {
            $timelineLabels[] = (string) $row['label'];
            $timelineRevenue[] = Currency::minorToDisplayNumber((int) $row['revenue_minor'], $currency, $precision);
            $timelineProfit[] = Currency::minorToDisplayNumber((int) $row['profit_minor'], $currency, $precision);
            $timelineOrders[] = (int) $row['order_count'];
        }

        $weekdayLabels = array();
        $weekdayRevenue = array();
        $weekdayProfit = array();

        foreach ((array) $report['weekday'] as $row) {
            $weekdayLabels[] = (string) $row['label'];
            $weekdayRevenue[] = Currency::minorToDisplayNumber((int) $row['average_revenue_minor'], $currency, $precision);
            $weekdayProfit[] = Currency::minorToDisplayNumber((int) $row['average_profit_minor'], $currency, $precision);
        }

        $seasonalityLabels = array();
        $seasonalityRevenue = array();
        $seasonalityProfit = array();

        foreach ((array) $report['seasonality'] as $row) {
            $seasonalityLabels[] = (string) $row['label'];
            $seasonalityRevenue[] = Currency::minorToDisplayNumber((int) $row['average_revenue_minor'], $currency, $precision);
            $seasonalityProfit[] = Currency::minorToDisplayNumber((int) $row['average_profit_minor'], $currency, $precision);
        }

        $comparison = (array) $report['comparison'];

        return array(
            'currencyLabel' => Currency::label($currency),
            'timeline' => array(
                'labels' => $timelineLabels,
                'revenue' => $timelineRevenue,
                'profit' => $timelineProfit,
                'orders' => $timelineOrders,
            ),
            'comparison' => array(
                'labels' => array('فروش', 'سود'),
                'current' => array(
                    Currency::minorToDisplayNumber((int) $comparison['current']['revenue_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $comparison['current']['profit_minor'], $currency, $precision),
                ),
                'previous' => array(
                    Currency::minorToDisplayNumber((int) $comparison['previous']['revenue_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $comparison['previous']['profit_minor'], $currency, $precision),
                ),
            ),
            'weekday' => array(
                'labels' => $weekdayLabels,
                'revenue' => $weekdayRevenue,
                'profit' => $weekdayProfit,
            ),
            'seasonality' => array(
                'labels' => $seasonalityLabels,
                'revenue' => $seasonalityRevenue,
                'profit' => $seasonalityProfit,
            ),
        );
    }

    private function timelineDescription(string $mode): string
    {
        if ($mode === 'week') {
            return 'برای خوانایی بیشتر این بازه به صورت هفتگی تجمیع شده است.';
        }

        if ($mode === 'month') {
            return 'برای بازه بلندمدت داده‌ها به صورت ماهانه شمسی تجمیع شده‌اند.';
        }

        return 'نمای روزانه فروش و سود خالص در بازه انتخابی.';
    }

    private function formatPercentage($value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format_i18n((float) $value, 1) . '٪';
    }

    private function resolveDateRange(): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $end = $now->setTime(23, 59, 59);

        $range = isset($_GET['range'])
            ? sanitize_key(wp_unslash($_GET['range']))
            : '30d';

        switch ($range) {
            case '7d':
                $start = $now->modify('-6 days')->setTime(0, 0, 0);
                break;
            case '90d':
                $start = $now->modify('-89 days')->setTime(0, 0, 0);
                break;
            case '6m':
                $start = $now->modify('-6 months')->setTime(0, 0, 0);
                break;
            case 'year':
                $start = $now->modify('-1 year')->setTime(0, 0, 0);
                break;
            case '2y':
                $start = $now->modify('-2 years')->setTime(0, 0, 0);
                break;
            case '3y':
                $start = $now->modify('-3 years')->setTime(0, 0, 0);
                break;
            case 'all':
                $start = $this->resolveAllTimeStart($now);
                break;
            case 'custom':
                $custom = $this->resolveCustomRange();
                if ($custom !== null) {
                    return array($custom[0], $custom[1], 'custom');
                }
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
        $startValue = isset($_GET['start_date'])
            ? sanitize_text_field(wp_unslash($_GET['start_date']))
            : '';
        $endValue = isset($_GET['end_date'])
            ? sanitize_text_field(wp_unslash($_GET['end_date']))
            : '';

        if ($startValue === '' || $endValue === '') {
            return null;
        }

        $gregorianStart = JalaliDate::parseInputToGregorianYmd($startValue);
        $gregorianEnd = JalaliDate::parseInputToGregorianYmd($endValue);

        if ($gregorianStart === null || $gregorianEnd === null) {
            return null;
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorianStart, wp_timezone());
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorianEnd, wp_timezone());

        if (! $start || ! $end || $start > $end) {
            return null;
        }

        return array(
            $start->setTime(0, 0, 0),
            $end->setTime(23, 59, 59),
        );
    }

    private function resolveAllTimeStart(DateTimeImmutable $fallback): DateTimeImmutable
    {
        $orders = wc_get_orders(
            array(
                'status' => array('processing', 'completed'),
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
            )
        );

        if (is_array($orders) && isset($orders[0]) && $orders[0] instanceof \WC_Order) {
            $date = $orders[0]->get_date_created();
            if ($date) {
                return (new DateTimeImmutable('@' . $date->getTimestamp()))
                    ->setTimezone(wp_timezone())
                    ->setTime(0, 0, 0);
            }
        }

        return $fallback->modify('-3 years')->setTime(0, 0, 0);
    }
}
