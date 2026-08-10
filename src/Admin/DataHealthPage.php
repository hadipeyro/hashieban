<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Json;
use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\DataHealthService;
use Hashieban\Support\JalaliDate;

final class DataHealthPage
{
    private DataHealthService $health;

    public function __construct(DataHealthService $health)
    {
        $this->health = $health;
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        list($start, $end, $range) = $this->resolveDateRange();

        $report = $this->health->getReport($start, $end);
        $payload = $this->buildChartPayload($report);
        ?>
        <div class="wrap hb-data-health-page">
            <section class="hb-data-health-hero">
                <div>
                    <div class="hb-data-health-hero__eyebrow">حاشیه‌بان BI · Data Health</div>
                    <h1>سلامت داده و آمادگی تحلیل</h1>
                    <p>
                        قبل از اینکه یک گزارش مدیریتی مبنای تصمیم باشد، باید بدانیم داده‌های پشت آن چقدر قابل اتکاست.
                        این بخش COGS ناقص، سفارش غیرقابل محاسبه، ارز متفاوت، Refund مشکوک و کیفیت داده جغرافیایی را بررسی می‌کند.
                    </p>
                    <div class="hb-data-health-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>سفارش اسکن‌شده: <strong><?php echo esc_html(number_format_i18n((int) $report['scanned_orders'])); ?></strong></span>
                    </div>
                </div>

                <div class="hb-data-health-score <?php echo esc_attr($this->scoreClass((int) $report['health_score'])); ?>">
                    <span>امتیاز سلامت داده</span>
                    <strong><?php echo esc_html(number_format_i18n((int) $report['health_score'])); ?></strong>
                    <small>از ۱۰۰ · مستقل از سودآوری کسب‌وکار</small>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <section class="hb-data-health-kpis">
                <?php
                $this->renderKpi(
                    'آمادگی مالی',
                    $this->formatPercentage($report['financial_readiness_percentage']),
                    number_format_i18n((int) $report['financially_complete_orders']) . ' سفارش کامل',
                    (float) $report['financial_readiness_percentage'] >= 95 ? 'good' : 'warning'
                );
                $this->renderKpi(
                    'پوشش COGS',
                    $this->formatPercentage($report['cogs_coverage_percentage']),
                    number_format_i18n((int) $report['products_with_missing_cogs']) . ' محصول ناقص',
                    (float) $report['cogs_coverage_percentage'] >= 95 ? 'good' : 'warning'
                );
                $this->renderKpi(
                    'آمادگی نقشه ایران',
                    $this->formatPercentage($report['geo_readiness_percentage']),
                    number_format_i18n((int) $report['geo_incomplete_count']) . ' از ' . number_format_i18n((int) $report['geo_eligible_order_count']) . ' سفارش ایرانی ناقص',
                    (float) $report['geo_readiness_percentage'] >= 90 ? 'good' : 'neutral'
                );
                $this->renderKpi(
                    'سفارش با ارز متفاوت',
                    number_format_i18n((int) $report['mixed_currency_count']),
                    'در جمع مالی فعلی وارد نمی‌شوند',
                    (int) $report['mixed_currency_count'] > 0 ? 'danger' : 'good'
                );
                ?>
            </section>

            <section class="hb-data-health-grid hb-data-health-grid--charts">
                <article class="hb-data-health-card hb-data-health-card--chart">
                    <div class="hb-data-health-card__header">
                        <div>
                            <h2>آمادگی لایه‌های داده</h2>
                            <p>درصد کیفیت داده برای تحلیل مالی، محصول، جغرافیا و ارتباط با مشتری</p>
                        </div>
                    </div>
                    <div class="hb-data-health-chart-wrap">
                        <canvas id="hashieban-data-health-readiness-chart"></canvas>
                    </div>
                </article>

                <article class="hb-data-health-card hb-data-health-card--chart">
                    <div class="hb-data-health-card__header">
                        <div>
                            <h2>سرنوشت سفارش‌های بازه</h2>
                            <p>چه تعداد وارد تحلیل شده‌اند و چه تعداد به دلیل وضعیت، ارز یا خطا کنار گذاشته شده‌اند</p>
                        </div>
                    </div>
                    <div class="hb-data-health-chart-wrap">
                        <canvas id="hashieban-data-health-orders-chart"></canvas>
                    </div>
                </article>
            </section>

            <section class="hb-data-health-card">
                <div class="hb-data-health-card__header">
                    <div>
                        <h2>مرکز مشکلات کیفیت داده</h2>
                        <p>هر مورد همراه با علت و اقدام پیشنهادی برای اصلاح نمایش داده می‌شود.</p>
                    </div>
                    <span class="hb-data-health-chip"><?php echo esc_html(number_format_i18n(count((array) $report['issues']))); ?> مورد</span>
                </div>

                <div class="hb-data-health-issue-list">
                    <?php foreach ((array) $report['issues'] as $issue) : ?>
                        <article class="hb-data-health-issue hb-data-health-issue--<?php echo esc_attr((string) $issue['severity']); ?>">
                            <div class="hb-data-health-issue__mark"><?php echo esc_html($this->severityIcon((string) $issue['severity'])); ?></div>
                            <div class="hb-data-health-issue__body">
                                <h3><?php echo esc_html((string) $issue['title']); ?></h3>
                                <p><?php echo esc_html((string) $issue['message']); ?></p>
                                <small><strong>اقدام پیشنهادی:</strong> <?php echo esc_html((string) $issue['action']); ?></small>
                            </div>
                            <div class="hb-data-health-issue__metric"><?php echo esc_html((string) $issue['metric']); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hb-data-health-grid">
                <?php $this->renderIncompleteOrders((array) $report['incomplete_orders']); ?>
                <?php $this->renderMissingCogsProducts((array) $report['missing_cogs_products']); ?>
            </section>

            <section class="hb-data-health-grid">
                <?php
                $this->renderSimpleOrderTable(
                    'ارز متفاوت',
                    'این سفارش‌ها عمداً با ارز پایه فروشگاه جمع نمی‌شوند.',
                    (array) $report['mixed_currency_orders'],
                    'currency'
                );
                $this->renderSimpleOrderTable(
                    'Refund نیازمند بازبینی',
                    'Refundهایی که هشدار تخصیص یا بازیابی COGS دارند.',
                    (array) $report['refund_warning_orders'],
                    'refund'
                );
                ?>
            </section>

            <section class="hb-data-health-grid">
                <?php
                $this->renderSimpleOrderTable(
                    'آدرس جغرافیایی ناقص',
                    'این موارد مربوط به سفارش‌های ایرانی قدیمی‌اند؛ برای سفارش‌های جدید، استان و شهر Checkout اجباری شده‌اند.',
                    (array) $report['geo_incomplete_orders'],
                    'geo'
                );
                $this->renderSimpleOrderTable(
                    'سفارش‌های خارج از تحلیل',
                    'این سفارش‌ها به دلیل وضعیت فعلی‌شان در سود نهایی وارد نشده‌اند.',
                    (array) $report['excluded_status_orders'],
                    'excluded'
                );
                ?>
            </section>

            <?php if ((array) $report['calculation_errors'] !== array() || (array) $report['orphan_product_orders'] !== array()) : ?>
                <section class="hb-data-health-grid">
                    <?php
                    $this->renderSimpleOrderTable(
                        'خطاهای محاسباتی',
                        'این موارد اولویت بالایی برای بررسی دارند.',
                        (array) $report['calculation_errors'],
                        'error'
                    );
                    $this->renderSimpleOrderTable(
                        'محصول حذف‌شده / غیرقابل بازیابی',
                        'آیتم تاریخی سفارش وجود دارد اما محصول فعلی قابل بازیابی نیست.',
                        (array) $report['orphan_product_orders'],
                        'orphan'
                    );
                    ?>
                </section>
            <?php endif; ?>

            <section class="hb-data-health-card hb-data-health-guidance">
                <div class="hb-data-health-card__header">
                    <div>
                        <h2>قاعده حاشیه‌بان برای داده سالم</h2>
                        <p>این بخش به‌جای پنهان کردن مشکل، صریحاً محدودیت داده را نشان می‌دهد.</p>
                    </div>
                </div>
                <div class="hb-data-health-guidance__grid">
                    <div><strong>COGS صفر با COGS گمشده یکی نیست</strong><span>اگر صفر عمداً ثبت شده باشد، به‌عنوان Missing علامت نمی‌خورد.</span></div>
                    <div><strong>ارزهای متفاوت جمع نمی‌شوند</strong><span>تا زمانی که موتور چندارزی واقعی ساخته نشود، عدد جعلی تولید نمی‌کنیم.</span></div>
                    <div><strong>تاریخچه سفارش حفظ می‌شود</strong><span>حذف محصول نباید باعث حذف سفارش تاریخی یا اطلاعات مالی گذشته شود.</span></div>
                    <div><strong>جغرافیا یک لایه تحلیلی است، نه خطای مالی</strong><span>استان و شهر روی امتیاز سلامت مالی جریمه نمی‌شوند؛ فقط آمادگی نقشه ایران را مشخص می‌کنند.</span></div>
                </div>
            </section>

            <script id="hashieban-data-health-data" type="application/json"><?php echo Json::forHtmlScript($payload); ?></script>
        </div>
        <?php
    }

    private function renderKpi(
        string $label,
        string $value,
        string $help,
        string $tone
    ): void {
        ?>
        <article class="hb-data-health-kpi hb-data-health-kpi--<?php echo esc_attr($tone); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function renderIncompleteOrders(array $rows): void
    {
        ?>
        <article class="hb-data-health-card hb-data-health-table-card">
            <div class="hb-data-health-card__header">
                <div><h2>سفارش‌های مالی ناقص</h2><p>مواردی که محاسبه سود آن‌ها کامل نیست</p></div>
            </div>
            <?php if ($rows === array()) : ?>
                <div class="hb-data-health-empty">سفارش مالی ناقصی در این بازه دیده نشد.</div>
            <?php else : ?>
                <div class="hb-data-health-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>سفارش</th><th>مشتری</th><th>مشکل</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url((string) $row['edit_url']); ?>">#<?php echo esc_html((string) $row['order_number']); ?></a></td>
                                <td><?php echo esc_html((string) $row['customer_name']); ?></td>
                                <td><?php echo esc_html(implode(' · ', (array) $row['missing_data'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderMissingCogsProducts(array $rows): void
    {
        ?>
        <article class="hb-data-health-card hb-data-health-table-card">
            <div class="hb-data-health-card__header">
                <div><h2>محصولات با COGS ناقص</h2><p>قیمت خرید تاریخی برای حداقل یک ردیف فروش قابل اتکا نیست</p></div>
            </div>
            <?php if ($rows === array()) : ?>
                <div class="hb-data-health-empty">پوشش COGS محصولات کامل است.</div>
            <?php else : ?>
                <div class="hb-data-health-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>محصول</th><th>SKU</th><th>ردیف ناقص</th><th>سفارش</th></tr></thead>
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
                                <td><?php echo esc_html((string) $row['sku'] !== '' ? (string) $row['sku'] : '—'); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $row['missing_cogs_lines'])); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $row['order_count'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderSimpleOrderTable(
        string $title,
        string $subtitle,
        array $rows,
        string $mode
    ): void {
        ?>
        <article class="hb-data-health-card hb-data-health-table-card">
            <div class="hb-data-health-card__header">
                <div><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($subtitle); ?></p></div>
                <span class="hb-data-health-chip"><?php echo esc_html(number_format_i18n(count($rows))); ?></span>
            </div>

            <?php if ($rows === array()) : ?>
                <div class="hb-data-health-empty">موردی برای نمایش وجود ندارد.</div>
            <?php else : ?>
                <div class="hb-data-health-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>سفارش</th><th>مشتری</th><th>جزئیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url((string) $row['edit_url']); ?>">#<?php echo esc_html((string) $row['order_number']); ?></a></td>
                                <td><?php echo esc_html((string) $row['customer_name']); ?></td>
                                <td><?php echo esc_html($this->simpleOrderDetail($row, $mode)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function simpleOrderDetail(array $row, string $mode): string
    {
        if ($mode === 'currency') {
            return 'ارز سفارش: ' . (string) ($row['currency'] ?? '—');
        }

        if ($mode === 'geo') {
            return 'ناقص: ' . implode('، ', (array) ($row['missing_fields'] ?? array()));
        }

        if ($mode === 'refund') {
            $warnings = (array) ($row['warnings'] ?? array());

            if ($warnings !== array()) {
                return implode(' · ', $warnings);
            }

            return ! empty($row['has_unallocated_refund'])
                ? 'بخشی از Refund به آیتم مشخص تخصیص پیدا نکرده است.'
                : 'نیازمند بازبینی Refund';
        }

        if ($mode === 'orphan') {
            return number_format_i18n((int) ($row['orphan_lines'] ?? 0)) . ' ردیف محصول غیرقابل بازیابی';
        }

        return (string) ($row['reason'] ?? '—');
    }

    private function buildChartPayload(array $report): array
    {
        return array(
            'readiness' => array(
                'labels' => array('مالی', 'COGS', 'جغرافیا', 'اطلاعات تماس'),
                'values' => array(
                    round((float) $report['financial_readiness_percentage'], 1),
                    round((float) $report['cogs_coverage_percentage'], 1),
                    round((float) $report['geo_readiness_percentage'], 1),
                    round((float) $report['contact_readiness_percentage'], 1),
                ),
            ),
            'orders' => array(
                'labels' => array('وارد تحلیل', 'خارج به دلیل وضعیت', 'ارز متفاوت', 'خطای محاسبه'),
                'values' => array(
                    max(
                        0,
                        (int) $report['included_orders'] - (int) $report['calculation_error_count']
                    ),
                    (int) $report['excluded_status_count'],
                    (int) $report['mixed_currency_count'],
                    (int) $report['calculation_error_count'],
                ),
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
            'all' => 'همه زمان‌ها',
        );
        ?>
        <div class="hb-data-health-range-card">
            <div class="hb-data-health-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-data-health', 'range' => $key), admin_url('admin.php'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-data-health-custom-range">
                <input type="hidden" name="page" value="hashieban-data-health">
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

        if (! $start || ! $end || $start > $end) {
            return null;
        }

        return array(
            $start->setTime(0, 0, 0),
            $end->setTime(23, 59, 59),
        );
    }

    private function resolveAllTimeStart(
        DateTimeImmutable $fallback
    ): DateTimeImmutable {
        $orders = wc_get_orders(
            array(
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
            )
        );

        if (
            is_array($orders)
            && isset($orders[0])
            && $orders[0] instanceof \WC_Order
        ) {
            $date = $orders[0]->get_date_created();

            if ($date) {
                return (new DateTimeImmutable('@' . $date->getTimestamp()))
                    ->setTimezone(wp_timezone())
                    ->setTime(0, 0, 0);
            }
        }

        return $fallback
            ->modify('-3 years')
            ->setTime(0, 0, 0);
    }

    private function formatPercentage($value): string
    {
        return number_format_i18n((float) $value, 1) . '٪';
    }

    private function scoreClass(int $score): string
    {
        if ($score < 60) {
            return 'is-danger';
        }

        if ($score < 85) {
            return 'is-warning';
        }

        return 'is-good';
    }

    private function severityIcon(string $severity): string
    {
        if ($severity === 'critical') {
            return '!';
        }

        if ($severity === 'warning') {
            return '⚠';
        }

        if ($severity === 'info') {
            return 'i';
        }

        return '✓';
    }
}
