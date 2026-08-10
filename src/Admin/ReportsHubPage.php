<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Csv;
use Hashieban\Security\Json;
use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\ReportsHubService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class ReportsHubPage
{
    private ReportsHubService $reports;

    public function __construct(ReportsHubService $reports)
    {
        $this->reports = $reports;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_export_report',
            array($this, 'exportCsv')
        );
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        list($start, $end, $range) = $this->resolveDateRange();
        $report = $this->reports->getReport($start, $end);
        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $payload = $this->buildChartPayload($report, $currency, $precision);
        $comparison = (array) $report['comparison'];

        ?>
        <div class="wrap hb-reports-page">
            <section class="hb-reports-hero">
                <div>
                    <span class="hb-reports-hero__eyebrow">حاشیه‌بان BI · Reports Hub</span>
                    <h1>مرکز گزارش‌های مدیریتی</h1>
                    <p>
                        نمای یکپارچه فروش، سود، محصول، مشتری، سفارش، زمان و Refund؛
                        برای اینکه مدیر فروشگاه به‌جای جابه‌جایی بین چند صفحه، تصویر کل کسب‌وکار را یکجا ببیند.
                    </p>
                    <div class="hb-reports-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>نسخه: <strong><?php echo esc_html(HASHIEBAN_VERSION); ?></strong></span>
                    </div>
                </div>
                <div class="hb-reports-hero__score">
                    <small>سود خالص بازه</small>
                    <strong><?php echo esc_html(Currency::formatMinor((int) $report['profit_minor'], $currency, $precision)); ?></strong>
                    <?php $this->renderDelta($comparison['profit_change_percentage'] ?? null, 'نسبت به دوره قبل'); ?>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <section class="hb-reports-kpis">
                <?php
                $this->renderKpi(
                    'فروش خالص',
                    Currency::formatMinor((int) $report['revenue_minor'], $currency, $precision),
                    $comparison['revenue_change_percentage'] ?? null,
                    'درآمد خالص سفارش‌ها پس از Refund'
                );
                $this->renderKpi(
                    'سود خالص',
                    Currency::formatMinor((int) $report['profit_minor'], $currency, $precision),
                    $comparison['profit_change_percentage'] ?? null,
                    'پس از COGS و همه هزینه‌های ثبت‌شده'
                );
                $this->renderKpi(
                    'Margin',
                    $this->percentage($report['margin_percentage']),
                    null,
                    'حاشیه سود خالص در بازه'
                );
                $this->renderKpi(
                    'سفارش‌ها',
                    number_format_i18n((int) $report['order_count']),
                    $comparison['orders_change_percentage'] ?? null,
                    'processing، completed و refunded'
                );
                $this->renderKpi(
                    'مشتریان فعال',
                    number_format_i18n((int) $report['customer_count']),
                    null,
                    'مشتریانی که در بازه خرید داشته‌اند'
                );
                $this->renderKpi(
                    'محصولات فروخته‌شده',
                    number_format_i18n((int) $report['product_count']),
                    null,
                    'محصولات دارای فروش در بازه'
                );
                ?>
            </section>

            <section class="hb-reports-module-grid">
                <?php
                $this->renderModule(
                    'سودآوری محصولات',
                    'پرفروش، پرسود، Margin، مرجوعی و سهم هر محصول از سود.',
                    'hashieban-products',
                    'products',
                    $start,
                    $end,
                    'dashicons-products'
                );
                $this->renderModule(
                    'سودآوری مشتریان',
                    'AOV، تعداد سفارش، سود مشتری و سهم از فروش و سود.',
                    'hashieban-customers',
                    'customers',
                    $start,
                    $end,
                    'dashicons-groups'
                );
                $this->renderModule(
                    'مرکز سفارش‌ها',
                    'سود/زیان، Margin، هزینه‌ها، Refund و Drill-down سفارش.',
                    'hashieban-orders',
                    'orders',
                    $start,
                    $end,
                    'dashicons-cart'
                );
                $this->renderModule(
                    'تحلیل زمانی',
                    'روند، مقایسه دوره‌ها، بهترین روز و الگوهای زمانی.',
                    'hashieban-time',
                    'timeline',
                    $start,
                    $end,
                    'dashicons-chart-line'
                );
                $this->renderModule(
                    'هوش هزینه‌ها',
                    'ترکیب هزینه، روند، بودجه، هزینه‌های پرتکرار و فشار هزینه بر فروش.',
                    'hashieban-expense-intelligence',
                    '',
                    $start,
                    $end,
                    'dashicons-chart-pie'
                );
                ?>
            </section>

            <section class="hb-reports-chart-grid">
                <div class="hb-reports-card hb-reports-card--wide">
                    <div class="hb-reports-card__header">
                        <div>
                            <h2>پل سود مدیریتی</h2>
                            <p>از فروش تا سود خالص؛ هر هزینه چه مقدار از درآمد را مصرف کرده است؟</p>
                        </div>
                        <span class="hb-reports-chip">Profit Bridge</span>
                    </div>
                    <div class="hb-reports-chart hb-reports-chart--large">
                        <canvas id="hashieban-reports-profit-bridge"></canvas>
                    </div>
                </div>

                <div class="hb-reports-card">
                    <div class="hb-reports-card__header">
                        <div>
                            <h2>تمرکز سود محصولات</h2>
                            <p>سهم محصولات برتر از کل سود محصولی</p>
                        </div>
                    </div>
                    <div class="hb-reports-chart">
                        <canvas id="hashieban-reports-product-share"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-reports-card hb-reports-card--timeline">
                <div class="hb-reports-card__header">
                    <div>
                        <h2>روند فروش و سود</h2>
                        <p>نمای زمانی یکپارچه برای تشخیص رشد، افت و تغییر جهت عملکرد.</p>
                    </div>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hashieban-time')); ?>">تحلیل کامل زمان</a>
                </div>
                <div class="hb-reports-chart hb-reports-chart--large">
                    <canvas id="hashieban-reports-timeline"></canvas>
                </div>
            </section>

            <section class="hb-reports-insights">
                <?php
                $this->renderTopProduct($report['top_product'], $currency, $precision);
                $this->renderTopCustomer($report['top_customer'], $currency, $precision);
                $this->renderBestDay($report['best_day'], $currency, $precision);
                $this->renderHealth($report);
                ?>
            </section>

            <section class="hb-reports-card hb-reports-card--statement">
                <div class="hb-reports-card__header">
                    <div>
                        <h2>صورت خلاصه مدیریتی</h2>
                        <p>نمای عددی فشرده از اجزای اصلی سود.</p>
                    </div>
                </div>
                <div class="hb-reports-statement">
                    <?php $this->statementRow('فروش خالص', (int) $report['revenue_minor'], $currency, $precision, 'positive'); ?>
                    <?php $this->statementRow('قیمت خرید کالاها (COGS)', -1 * (int) $report['cogs_minor'], $currency, $precision, 'cost'); ?>
                    <?php $this->statementRow('هزینه‌های مستقیم سفارش', -1 * (int) $report['direct_costs_minor'], $currency, $precision, 'cost'); ?>
                    <?php $this->statementRow('هزینه ثابت هر سفارش', -1 * (int) $report['global_order_costs_minor'], $currency, $precision, 'cost'); ?>
                    <?php $this->statementRow('هزینه‌های کلی فروشگاه', -1 * (int) $report['store_expenses_minor'], $currency, $precision, 'cost'); ?>
                    <?php $this->statementRow('سود خالص', (int) $report['profit_minor'], $currency, $precision, 'profit'); ?>
                </div>
            </section>

            <script id="hashieban-reports-data" type="application/json"><?php echo Json::forHtmlScript($payload); ?></script>
        </div>
        <?php
    }

    public function exportCsv(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دریافت این گزارش را ندارید.'));
        }

        check_admin_referer('hashieban_export_report');

        $type = isset($_GET['report_type'])
            ? sanitize_key(wp_unslash($_GET['report_type']))
            : 'timeline';

        $allowed = array('products', 'customers', 'orders', 'timeline');

        if (! in_array($type, $allowed, true)) {
            $type = 'timeline';
        }

        $timezone = wp_timezone();
        $startValue = isset($_GET['start'])
            ? sanitize_text_field(wp_unslash($_GET['start']))
            : '';
        $endValue = isset($_GET['end'])
            ? sanitize_text_field(wp_unslash($_GET['end']))
            : '';

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startValue, $timezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endValue, $timezone);

        if (! $start || ! $end) {
            wp_die(esc_html('بازه گزارش معتبر نیست.'));
        }

        $end = $end->setTime(23, 59, 59);
        $data = $this->reports->exportRows($type, $start, $end);
        $filename = sanitize_file_name((string) $data['filename']);

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            wp_die(esc_html('ساخت فایل خروجی ممکن نشد.'));
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, Csv::protectRow((array) $data['headers']));

        foreach ((array) $data['rows'] as $row) {
            fputcsv($stream, Csv::protectRow((array) $row));
        }

        fclose($stream);
        exit;
    }

    private function renderModule(
        string $title,
        string $description,
        string $page,
        string $exportType,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $icon
    ): void {
        $pageUrl = add_query_arg(
            array(
                'page' => $page,
                'range' => 'custom',
                'start_date' => JalaliDate::numeric($start),
                'end_date' => JalaliDate::numeric($end),
            ),
            admin_url('admin.php')
        );
        $exportUrl = $exportType !== ''
            ? $this->exportUrl($exportType, $start, $end)
            : '';
        ?>
        <article class="hb-reports-module">
            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            <div>
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo esc_html($description); ?></p>
                <div class="hb-reports-module__actions">
                    <a class="button button-primary" href="<?php echo esc_url($pageUrl); ?>">باز کردن گزارش</a>
                    <?php if ($exportUrl !== '') : ?>
                        <a class="button" href="<?php echo esc_url($exportUrl); ?>">CSV</a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }

    private function exportUrl(
        string $type,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): string {
        $url = add_query_arg(
            array(
                'action' => 'hashieban_export_report',
                'report_type' => $type,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ),
            admin_url('admin-post.php')
        );

        return wp_nonce_url($url, 'hashieban_export_report');
    }

    private function renderKpi(
        string $title,
        string $value,
        $delta,
        string $description
    ): void {
        ?>
        <article class="hb-reports-kpi">
            <span><?php echo esc_html($title); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <?php if ($delta !== null) : ?>
                <?php $this->renderDelta($delta, 'نسبت به قبل'); ?>
            <?php else : ?>
                <small><?php echo esc_html($description); ?></small>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderDelta($value, string $suffix): void
    {
        if ($value === null) {
            return;
        }

        $number = (float) $value;
        $class = $number > 0 ? 'positive' : ($number < 0 ? 'negative' : 'neutral');
        $arrow = $number > 0 ? '↑' : ($number < 0 ? '↓' : '•');
        ?>
        <small class="hb-reports-delta hb-reports-delta--<?php echo esc_attr($class); ?>">
            <?php echo esc_html($arrow . ' ' . number_format_i18n(abs($number), 1) . '٪ ' . $suffix); ?>
        </small>
        <?php
    }

    private function renderTopProduct($row, string $currency, int $precision): void
    {
        if (! is_array($row)) {
            $this->renderEmptyInsight('محصول پرسود', 'داده کافی وجود ندارد.');
            return;
        }

        $url = (string) ($row['edit_url'] ?? '');
        ?>
        <article class="hb-reports-insight hb-reports-insight--product">
            <span>محصول پرسود</span>
            <h3>
                <?php if ($url !== '') : ?>
                    <a href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $row['name']); ?></a>
                <?php else : ?>
                    <?php echo esc_html((string) $row['name']); ?>
                <?php endif; ?>
            </h3>
            <strong><?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?></strong>
            <small>سهم از سود: <?php echo esc_html($this->percentage($row['profit_share_percentage'] ?? null)); ?></small>
        </article>
        <?php
    }

    private function renderTopCustomer($row, string $currency, int $precision): void
    {
        if (! is_array($row)) {
            $this->renderEmptyInsight('مشتری سودآور', 'داده کافی وجود ندارد.');
            return;
        }
        ?>
        <article class="hb-reports-insight hb-reports-insight--customer">
            <span>مشتری سودآور</span>
            <h3><?php echo esc_html((string) $row['name']); ?></h3>
            <strong><?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?></strong>
            <small><?php echo esc_html(number_format_i18n((int) $row['order_count']) . ' سفارش'); ?></small>
        </article>
        <?php
    }

    private function renderBestDay($row, string $currency, int $precision): void
    {
        if (! is_array($row)) {
            $this->renderEmptyInsight('بهترین روز سود', 'داده کافی وجود ندارد.');
            return;
        }
        ?>
        <article class="hb-reports-insight hb-reports-insight--time">
            <span>بهترین روز سود</span>
            <h3><?php echo esc_html((string) $row['full_label']); ?></h3>
            <strong><?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?></strong>
            <small><?php echo esc_html(number_format_i18n((int) $row['order_count']) . ' سفارش'); ?></small>
        </article>
        <?php
    }

    private function renderHealth(array $report): void
    {
        $issues = (int) $report['incomplete_order_count']
            + (int) $report['products_missing_cogs']
            + (int) $report['loss_order_count'];
        ?>
        <article class="hb-reports-insight hb-reports-insight--health">
            <span>سلامت گزارش</span>
            <h3><?php echo $issues === 0 ? esc_html('پایدار') : esc_html('نیازمند بررسی'); ?></h3>
            <strong><?php echo esc_html(number_format_i18n($issues) . ' مورد'); ?></strong>
            <small>
                <?php echo esc_html(
                    number_format_i18n((int) $report['refund_order_count'])
                    . ' سفارش دارای Refund'
                ); ?>
            </small>
        </article>
        <?php
    }

    private function renderEmptyInsight(string $title, string $message): void
    {
        ?>
        <article class="hb-reports-insight">
            <span><?php echo esc_html($title); ?></span>
            <h3>—</h3>
            <small><?php echo esc_html($message); ?></small>
        </article>
        <?php
    }

    private function statementRow(
        string $label,
        int $amountMinor,
        string $currency,
        int $precision,
        string $type
    ): void {
        ?>
        <div class="hb-reports-statement__row hb-reports-statement__row--<?php echo esc_attr($type); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html(Currency::formatMinor($amountMinor, $currency, $precision)); ?></strong>
        </div>
        <?php
    }

    private function buildChartPayload(array $report, string $currency, int $precision): array
    {
        $bridge = (array) $report['profit_bridge'];
        $bridgeValues = array();

        foreach ((array) $bridge['values_minor'] as $value) {
            $bridgeValues[] = Currency::minorToDisplayNumber((int) $value, $currency, $precision);
        }

        $productLabels = array();
        $productValues = array();
        $productUrls = array();

        foreach ((array) $report['top_product_profit'] as $row) {
            $productLabels[] = (string) $row['name'];
            $productValues[] = Currency::minorToDisplayNumber((int) $row['profit_minor'], $currency, $precision);
            $productUrls[] = (string) $row['edit_url'];
        }

        $timelineLabels = array();
        $timelineRevenue = array();
        $timelineProfit = array();

        foreach ((array) $report['timeline'] as $row) {
            $timelineLabels[] = (string) ($row['label'] ?? '');
            $timelineRevenue[] = Currency::minorToDisplayNumber((int) ($row['revenue_minor'] ?? 0), $currency, $precision);
            $timelineProfit[] = Currency::minorToDisplayNumber((int) ($row['profit_minor'] ?? 0), $currency, $precision);
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'bridge' => array(
                'labels' => (array) $bridge['labels'],
                'values' => $bridgeValues,
            ),
            'products' => array(
                'labels' => $productLabels,
                'values' => $productValues,
                'urls' => $productUrls,
            ),
            'timeline' => array(
                'labels' => $timelineLabels,
                'revenue' => $timelineRevenue,
                'profit' => $timelineProfit,
            ),
        );
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
            'all' => 'همه زمان‌ها',
        );
        ?>
        <div class="hb-reports-range">
            <div class="hb-reports-range__buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-reports', 'range' => $key), admin_url('admin.php'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <form method="get" class="hb-reports-range__custom">
                <input type="hidden" name="page" value="hashieban-reports">
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

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $gregorianStart,
            wp_timezone()
        );
        $end = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $gregorianEnd,
            wp_timezone()
        );

        if (! $start || ! $end) {
            return null;
        }

        $start = $start->setTime(0, 0, 0);
        $end = $end->setTime(23, 59, 59);

        if ($start > $end) {
            $temporary = $start;
            $start = $end->setTime(0, 0, 0);
            $end = $temporary->setTime(23, 59, 59);
        }

        return array($start, $end);
    }

    private function resolveAllTimeStart(DateTimeImmutable $fallback): DateTimeImmutable
    {
        $orders = wc_get_orders(
            array(
                'status' => array('processing', 'completed', 'refunded'),
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
                'return' => 'objects',
            )
        );

        if (is_array($orders) && isset($orders[0])) {
            $created = $orders[0]->get_date_created();
            if ($created) {
                return (new DateTimeImmutable('@' . $created->getTimestamp()))
                    ->setTimezone(wp_timezone())
                    ->setTime(0, 0, 0);
            }
        }

        return $fallback->modify('-30 days')->setTime(0, 0, 0);
    }

    private function percentage($value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format_i18n((float) $value, 1) . '٪';
    }
}
