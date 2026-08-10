<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\GeoIntelligenceService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class GeoIntelligencePage
{
    private GeoIntelligenceService $analytics;

    public function __construct(GeoIntelligenceService $analytics)
    {
        $this->analytics = $analytics;
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        list($start, $end, $range) = $this->resolveDateRange();
        $report = $this->analytics->getReport($start, $end);
        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $payload = $this->buildPayload($report, $currency, $precision);
        $topSales = is_array($report['top_sales_province']) ? $report['top_sales_province'] : null;
        $topProfit = is_array($report['top_profit_province']) ? $report['top_profit_province'] : null;
        $topCity = is_array($report['top_city']) ? $report['top_city'] : null;
        ?>
        <div class="wrap hb-geo-page">
            <section class="hb-geo-hero">
                <div>
                    <div class="hb-geo-hero__eyebrow">حاشیه‌بان BI · Geo Intelligence</div>
                    <h1>نقشه هوشمند فروش ایران</h1>
                    <p>
                        ببین فروش، سود، سفارش و مشتری‌های فروشگاه از کدام استان‌ها و شهرها می‌آیند؛
                        روی هر استان کلیک کن تا شهرهای همان منطقه و محرک‌های اصلی فروش را ببینی.
                    </p>
                    <div class="hb-geo-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>واحد: <strong><?php echo esc_html(Currency::label($currency)); ?></strong></span>
                    </div>
                </div>
                <div class="hb-geo-hero__readiness">
                    <span>پوشش جغرافیایی سفارش‌ها</span>
                    <strong><?php echo esc_html(number_format_i18n((float) $report['province_readiness_percentage'], 1)); ?>٪</strong>
                    <small>
                        <?php echo esc_html(number_format_i18n((int) $report['province_mapped_orders'])); ?> از
                        <?php echo esc_html(number_format_i18n((int) $report['iran_order_count'])); ?> سفارش ایرانی دارای استان قابل تحلیل‌اند.
                    </small>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <?php if ((int) $report['iran_order_count'] > (int) $report['city_mapped_orders']) : ?>
                <div class="hb-geo-notice">
                    <strong>داده شهر هنوز کامل نیست.</strong>
                    پوشش شهر در این بازه <?php echo esc_html(number_format_i18n((float) $report['city_readiness_percentage'], 1)); ?>٪ است؛
                    سفارش‌های جدید ایران از مرحله قبل استان و شهر اجباری دارند و این عدد به‌مرور کامل‌تر می‌شود.
                </div>
            <?php endif; ?>

            <section class="hb-geo-kpis">
                <?php
                $this->renderKpi(
                    'بیشترین سهم فروش',
                    $topSales ? (string) $topSales['name'] : '—',
                    $topSales
                        ? $this->formatPercentage($topSales['sales_share_percentage']) . ' از فروش جغرافیایی'
                        : 'هنوز داده کافی وجود ندارد',
                    'blue'
                );
                $this->renderKpi(
                    'بیشترین سود',
                    $topProfit ? (string) $topProfit['name'] : '—',
                    $topProfit
                        ? Currency::formatMinor((int) $topProfit['profit_minor'], $currency, $precision)
                        : 'هنوز داده کافی وجود ندارد',
                    'green'
                );
                $this->renderKpi(
                    'پرفروش‌ترین شهر',
                    $topCity ? (string) $topCity['name'] : '—',
                    $topCity
                        ? (string) $topCity['province'] . ' · ' . $this->formatPercentage($topCity['sales_share_percentage']) . ' سهم فروش'
                        : 'برای این KPI شهر سفارش لازم است',
                    'purple'
                );
                $this->renderKpi(
                    'شهر قابل تحلیل',
                    number_format_i18n((int) $report['city_count']),
                    number_format_i18n((int) $report['unique_customer_count']) . ' مشتری یکتا در داده جغرافیایی',
                    'amber'
                );
                ?>
            </section>

            <section class="hb-geo-map-layout">
                <article class="hb-geo-card hb-geo-map-card">
                    <div class="hb-geo-card__header hb-geo-card__header--map">
                        <div>
                            <h2>سهم خرید روی نقشه ایران</h2>
                            <p>رنگ پررنگ‌تر یعنی سهم بیشتر. معیار را تغییر بده یا روی استان کلیک کن.</p>
                        </div>
                        <button type="button" class="hb-geo-reset" id="hb-geo-reset">کل ایران</button>
                    </div>

                    <div class="hb-geo-metric-switch" role="group" aria-label="معیار نقشه">
                        <button type="button" class="is-active" data-metric="revenue">فروش</button>
                        <button type="button" data-metric="profit">سود</button>
                        <button type="button" data-metric="orders">سفارش</button>
                        <button type="button" data-metric="customers">مشتری</button>
                        <button type="button" data-metric="margin">Margin</button>
                    </div>

                    <div class="hb-geo-map-shell" id="hashieban-iran-map">
                        <?php echo $this->mapSvg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="hb-geo-tooltip" id="hb-geo-tooltip" hidden></div>
                    </div>

                    <div class="hb-geo-legend">
                        <span id="hb-geo-legend-max">بیشترین</span>
                        <div class="hb-geo-legend__gradient"></div>
                        <span id="hb-geo-legend-min">کمترین</span>
                    </div>
                </article>

                <aside class="hb-geo-card hb-geo-ranking-card">
                    <div class="hb-geo-card__header">
                        <div>
                            <h2>رتبه‌بندی استان‌ها</h2>
                            <p id="hb-geo-ranking-caption">بر اساس سهم فروش</p>
                        </div>
                    </div>
                    <div id="hb-geo-province-ranking" class="hb-geo-ranking"></div>
                </aside>
            </section>

            <section class="hb-geo-card hb-geo-selected" id="hb-geo-selected-card">
                <div class="hb-geo-card__header">
                    <div>
                        <span class="hb-geo-selected__eyebrow">Drill-down منطقه</span>
                        <h2 id="hb-geo-selected-title">یک استان را انتخاب کن</h2>
                        <p id="hb-geo-selected-subtitle">جزئیات فروش، سود، مشتری و شهرهای استان اینجا نمایش داده می‌شود.</p>
                    </div>
                </div>
                <div class="hb-geo-selected__metrics" id="hb-geo-selected-metrics"></div>
                <div class="hb-geo-insights" id="hb-geo-selected-insights"></div>
            </section>

            <section class="hb-geo-grid hb-geo-grid--charts">
                <article class="hb-geo-card">
                    <div class="hb-geo-card__header">
                        <div>
                            <h2>شهرهای برتر</h2>
                            <p id="hb-geo-city-caption">برترین شهرهای ایران در بازه انتخابی</p>
                        </div>
                    </div>
                    <div class="hb-geo-chart-wrap hb-geo-chart-wrap--cities">
                        <canvas id="hashieban-geo-city-chart"></canvas>
                    </div>
                    <div id="hb-geo-city-ranking" class="hb-geo-city-ranking"></div>
                </article>

                <article class="hb-geo-card">
                    <div class="hb-geo-card__header">
                        <div>
                            <h2>فروش و سود استان‌های برتر</h2>
                            <p>مقایسه مستقیم گردش مالی با سود قابل انتساب به سفارش‌های هر استان</p>
                        </div>
                    </div>
                    <div class="hb-geo-chart-wrap">
                        <canvas id="hashieban-geo-compare-chart"></canvas>
                    </div>
                </article>
            </section>

            <section class="hb-geo-card hb-geo-methodology">
                <div>
                    <strong>منبع موقعیت:</strong>
                    <span>آدرس Shipping کامل در اولویت است؛ در غیر این صورت Billing استفاده می‌شود.</span>
                </div>
                <div>
                    <strong>سود منطقه‌ای:</strong>
                    <span>هزینه‌های مستقیم و هزینه ثابت هر سفارش لحاظ می‌شوند؛ هزینه عمومی فروشگاه بین استان‌ها به‌صورت مصنوعی پخش نشده است.</span>
                </div>
                <div>
                    <strong>حفظ حریم داده:</strong>
                    <span>نقشه فقط آمار تجمیعی مدیریتی نمایش می‌دهد و آدرس کامل مشتری روی نقشه قرار نمی‌گیرد.</span>
                </div>
            </section>

            <script id="hashieban-geo-data" type="application/json"><?php echo wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        </div>
        <?php
    }

    private function renderKpi(string $label, string $value, string $help, string $tone): void
    {
        ?>
        <article class="hb-geo-kpi hb-geo-kpi--<?php echo esc_attr($tone); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function buildPayload(array $report, string $currency, int $precision): array
    {
        $provinces = array();
        $cities = array();

        foreach ((array) $report['provinces'] as $row) {
            $provinces[] = $this->serializeRow($row, $currency, $precision, true);
        }

        foreach ((array) $report['cities'] as $row) {
            $cities[] = $this->serializeRow($row, $currency, $precision, false);
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'provinces' => $provinces,
            'cities' => $cities,
        );
    }

    private function serializeRow(array $row, string $currency, int $precision, bool $province): array
    {
        $serialized = array(
            'name' => (string) ($row['name'] ?? ''),
            'province' => (string) ($row['province'] ?? ($row['name'] ?? '')),
            'mapName' => (string) ($row['map_name'] ?? ''),
            'orders' => (int) ($row['order_count'] ?? 0),
            'customers' => (int) ($row['customer_count'] ?? 0),
            'revenue' => Currency::minorToDisplayNumber((int) ($row['revenue_minor'] ?? 0), $currency, $precision),
            'profit' => Currency::minorToDisplayNumber((int) ($row['profit_minor'] ?? 0), $currency, $precision),
            'averageOrder' => Currency::minorToDisplayNumber((int) ($row['average_order_minor'] ?? 0), $currency, $precision),
            'margin' => $row['margin_percentage'] !== null ? round((float) $row['margin_percentage'], 2) : null,
            'salesShare' => $row['sales_share_percentage'] !== null ? round((float) $row['sales_share_percentage'], 2) : null,
            'profitShare' => $row['profit_share_percentage'] !== null ? round((float) $row['profit_share_percentage'], 2) : null,
            'orderShare' => $row['order_share_percentage'] !== null ? round((float) $row['order_share_percentage'], 2) : null,
            'customerShare' => isset($row['customer_share_percentage']) && $row['customer_share_percentage'] !== null
                ? round((float) $row['customer_share_percentage'], 2)
                : null,
        );

        if ($province) {
            $serialized['topCity'] = $this->serializeMiniRow($row['top_city'] ?? null, $currency, $precision);
            $serialized['topProduct'] = $this->serializeMiniRow($row['top_product'] ?? null, $currency, $precision);
            $serialized['topCustomer'] = $this->serializeMiniRow($row['top_customer'] ?? null, $currency, $precision);
        }

        return $serialized;
    }

    private function serializeMiniRow($row, string $currency, int $precision): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $url = '';
        $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;

        if ($productId > 0) {
            $editUrl = get_edit_post_link($productId, 'raw');
            $url = is_string($editUrl) ? $editUrl : '';
        }

        return array(
            'name' => (string) ($row['name'] ?? ''),
            'revenue' => Currency::minorToDisplayNumber((int) ($row['revenue_minor'] ?? 0), $currency, $precision),
            'orders' => (int) ($row['order_count'] ?? 0),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'url' => $url,
        );
    }

    private function mapSvg(): string
    {
        $path = HASHIEBAN_PATH . 'assets/admin/maps/iran-provinces.svg';

        if (! is_readable($path)) {
            return '<div class="hb-geo-map-missing">فایل نقشه ایران در بسته افزونه پیدا نشد.</div>';
        }

        $svg = file_get_contents($path);

        return is_string($svg) ? $svg : '';
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
            'all' => 'همه',
        );
        ?>
        <div class="hb-geo-range-bar">
            <div class="hb-geo-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-geo', 'range' => $key), admin_url('admin.php'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <form method="get" class="hb-geo-custom-range">
                <input type="hidden" name="page" value="hashieban-geo">
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
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $end = $now->setTime(23, 59, 59);
        $range = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '30d';

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
        $startValue = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $endValue = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';

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

        return array($start->setTime(0, 0, 0), $end->setTime(23, 59, 59));
    }

    private function resolveAllTimeStart(DateTimeImmutable $fallback): DateTimeImmutable
    {
        $orders = wc_get_orders(
            array(
                'status' => array('processing', 'completed', 'refunded'),
                'currency' => get_woocommerce_currency(),
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

    private function formatPercentage($value): string
    {
        return $value === null ? '—' : number_format_i18n((float) $value, 1) . '٪';
    }
}
