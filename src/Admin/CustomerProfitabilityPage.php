<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\CustomerProfitabilityService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class CustomerProfitabilityPage
{
    private CustomerProfitabilityService $analytics;

    public function __construct(
        CustomerProfitabilityService $analytics
    ) {
        $this->analytics = $analytics;
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(
                esc_html('شما اجازه دسترسی به این بخش را ندارید.')
            );
        }

        list($start, $end, $range) = $this->resolveDateRange();

        $report = $this->analytics->getReport(
            $start,
            $end
        );

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];

        $customers = $this->filterCustomers(
            $report['customers']
        );

        $sort = $this->resolveSort();
        $customers = $this->sortCustomers(
            $customers,
            $sort
        );

        $perPage = 25;
        $currentPage = max(
            1,
            isset($_GET['paged'])
                ? absint(wp_unslash($_GET['paged']))
                : 1
        );

        $totalRows = count($customers);
        $totalPages = max(
            1,
            (int) ceil($totalRows / $perPage)
        );

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $pageRows = array_slice(
            $customers,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        $chartPayload = $this->buildChartPayload(
            $report,
            $currency,
            $precision
        );

        ?>
        <div class="wrap hb-customer-page">
            <section class="hb-customer-hero">
                <div>
                    <div class="hb-customer-hero__eyebrow">حاشیه‌بان BI</div>
                    <h1>تحلیل سودآوری مشتریان</h1>
                    <p>
                        ببین کدام مشتری فقط فروش ایجاد می‌کند، کدام مشتری سود واقعی می‌سازد
                        و چه سهمی از درآمد و سود فروشگاه به هر مشتری وابسته است.
                    </p>

                    <div class="hb-customer-hero__meta">
                        <span>
                            بازه:
                            <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong>
                        </span>
                        <span>
                            واحد:
                            <strong><?php echo esc_html(Currency::label($currency)); ?></strong>
                        </span>
                    </div>
                </div>

                <div class="hb-customer-hero__profit">
                    <span>سود قابل انتساب به سفارش‌های مشتریان</span>
                    <strong>
                        <?php
                        echo esc_html(
                            Currency::formatMinor(
                                (int) $report['total_profit_minor'],
                                $currency,
                                $precision
                            )
                        );
                        ?>
                    </strong>
                    <small>
                        شامل COGS، هزینه مستقیم سفارش و هزینه ثابت هر سفارش است؛ هزینه‌های عمومی فروشگاه بین مشتریان تخصیص داده نشده‌اند.
                    </small>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <?php if ((int) $report['orders_with_refunds'] > 0) : ?>
                <div class="hb-customer-notice hb-customer-notice--warning">
                    <strong>توجه به Refund:</strong>
                    در این بازه <?php echo esc_html(number_format_i18n((int) $report['orders_with_refunds'])); ?> سفارش دارای بازپرداخت است.
                    موتور کامل Refund در مرحله اختصاصی بازگشت وجه تکمیل می‌شود؛ بنابراین سود مشتریان دارای Refund را با احتیاط تفسیر کن.
                </div>
            <?php endif; ?>

            <?php if ((int) $report['incomplete_orders'] > 0) : ?>
                <div class="hb-customer-notice hb-customer-notice--info">
                    <strong>داده مالی ناقص:</strong>
                    <?php echo esc_html(number_format_i18n((int) $report['incomplete_orders'])); ?> سفارش در این بازه اطلاعات مالی کامل ندارد.
                </div>
            <?php endif; ?>

            <section class="hb-customer-kpis">
                <?php
                $this->renderKpi(
                    'فروش مشتریان',
                    Currency::formatMinor(
                        (int) $report['total_revenue_minor'],
                        $currency,
                        $precision
                    ),
                    'درآمد سفارش‌ها پس از Refund ثبت‌شده'
                );

                $this->renderKpi(
                    'سود سفارش‌ها',
                    Currency::formatMinor(
                        (int) $report['total_profit_minor'],
                        $currency,
                        $precision
                    ),
                    'پس از COGS و هزینه‌های مستقیم و ثابت سفارش'
                );

                $this->renderKpi(
                    'تعداد مشتری',
                    number_format_i18n((int) $report['customer_count']),
                    'مشتری ثبت‌نام‌شده یا مهمان شناسایی‌شده'
                );

                $this->renderKpi(
                    'تعداد سفارش',
                    number_format_i18n((int) $report['total_orders']),
                    'سفارش‌های processing و completed'
                );

                $this->renderKpi(
                    'میانگین ارزش سفارش',
                    Currency::formatMinor(
                        (int) $report['average_order_value_minor'],
                        $currency,
                        $precision
                    ),
                    'Average Order Value یا AOV'
                );

                $this->renderKpi(
                    'مشتری تکراری',
                    number_format_i18n((int) $report['repeat_customer_count']),
                    'مشتریانی با حداقل دو سفارش در بازه'
                );
                ?>
            </section>

            <?php $this->renderInsightCards($report, $currency, $precision); ?>

            <section class="hb-customer-chart-grid">
                <div class="hb-customer-card hb-customer-card--chart">
                    <div class="hb-customer-card__header">
                        <div>
                            <h2>مشتریان با بیشترین خرید</h2>
                            <p>۱۰ مشتری برتر بر اساس مبلغ خرید در بازه انتخابی</p>
                        </div>
                    </div>
                    <div class="hb-customer-chart-wrap">
                        <canvas id="hashieban-customer-revenue-chart"></canvas>
                    </div>
                </div>

                <div class="hb-customer-card hb-customer-card--chart">
                    <div class="hb-customer-card__header">
                        <div>
                            <h2>مشتریان با بیشترین سود</h2>
                            <p>۱۰ مشتری برتر بر اساس سود قابل انتساب به سفارش‌ها</p>
                        </div>
                    </div>
                    <div class="hb-customer-chart-wrap">
                        <canvas id="hashieban-customer-profit-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-customer-ranking-grid">
                <?php
                $this->renderRankingList(
                    'بیشترین خرید',
                    $report['top_by_revenue'],
                    'revenue_minor',
                    $currency,
                    $precision,
                    'sales'
                );

                $this->renderRankingList(
                    'بیشترین سود',
                    $report['top_by_profit'],
                    'profit_minor',
                    $currency,
                    $precision,
                    'profit'
                );

                $this->renderRankingList(
                    'کمترین سود / ریسک مالی',
                    $report['bottom_by_profit'],
                    'profit_minor',
                    $currency,
                    $precision,
                    'risk'
                );
                ?>
            </section>

            <section class="hb-customer-card hb-customer-table-card">
                <div class="hb-customer-card__header hb-customer-card__header--table">
                    <div>
                        <h2>دفتر سودآوری مشتریان</h2>
                        <p>مقایسه خرید، سود، AOV، Margin و سهم هر مشتری</p>
                    </div>

                    <form method="get" class="hb-customer-table-controls">
                        <input type="hidden" name="page" value="hashieban-customers">
                        <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">

                        <?php if ($range === 'custom') : ?>
                            <input type="hidden" name="start_date" value="<?php echo esc_attr(isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : ''); ?>">
                            <input type="hidden" name="end_date" value="<?php echo esc_attr(isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : ''); ?>">
                        <?php endif; ?>

                        <input
                            type="search"
                            name="q"
                            value="<?php echo esc_attr($this->resolveSearch()); ?>"
                            placeholder="نام، ایمیل یا موبایل..."
                        >

                        <select name="sort">
                            <option value="profit_desc" <?php selected($sort, 'profit_desc'); ?>>بیشترین سود</option>
                            <option value="revenue_desc" <?php selected($sort, 'revenue_desc'); ?>>بیشترین خرید</option>
                            <option value="orders_desc" <?php selected($sort, 'orders_desc'); ?>>بیشترین سفارش</option>
                            <option value="aov_desc" <?php selected($sort, 'aov_desc'); ?>>بیشترین AOV</option>
                            <option value="margin_desc" <?php selected($sort, 'margin_desc'); ?>>بیشترین Margin</option>
                            <option value="profit_asc" <?php selected($sort, 'profit_asc'); ?>>کمترین سود</option>
                        </select>

                        <button type="submit" class="button button-primary">اعمال</button>
                    </form>
                </div>

                <div class="hb-customer-table-wrap">
                    <table class="widefat striped hb-customer-table">
                        <thead>
                            <tr>
                                <th>مشتری</th>
                                <th>سفارش</th>
                                <th>مجموع خرید</th>
                                <th>AOV</th>
                                <th>سود</th>
                                <th>Margin</th>
                                <th>سهم فروش</th>
                                <th>سهم سود</th>
                                <th>آخرین سفارش</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pageRows === array()) : ?>
                                <tr>
                                    <td colspan="10" class="hb-customer-empty">مشتری مطابق این فیلتر پیدا نشد.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($pageRows as $row) : ?>
                                    <tr>
                                        <td>
                                            <div class="hb-customer-person">
                                                <strong>
                                                    <?php if ((string) $row['edit_url'] !== '') : ?>
                                                        <a href="<?php echo esc_url((string) $row['edit_url']); ?>">
                                                            <?php echo esc_html((string) $row['name']); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <?php echo esc_html((string) $row['name']); ?>
                                                    <?php endif; ?>
                                                </strong>
                                                <small>
                                                    <?php echo esc_html((bool) $row['registered'] ? 'عضو سایت' : 'مهمان'); ?>
                                                    <?php if ((string) $row['email'] !== '') : ?>
                                                        · <?php echo esc_html((string) $row['email']); ?>
                                                    <?php endif; ?>
                                                    <?php if ((string) $row['phone'] !== '') : ?>
                                                        · <?php echo esc_html((string) $row['phone']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html(number_format_i18n((int) $row['order_count'])); ?></td>
                                        <td><?php echo esc_html(Currency::formatMinor((int) $row['revenue_minor'], $currency, $precision)); ?></td>
                                        <td><?php echo esc_html(Currency::formatMinor((int) $row['average_order_value_minor'], $currency, $precision)); ?></td>
                                        <td class="<?php echo (int) $row['profit_minor'] < 0 ? 'hb-customer-negative' : 'hb-customer-positive'; ?>">
                                            <?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?>
                                        </td>
                                        <td><?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?></td>
                                        <td><?php echo esc_html($this->formatPercentage($row['sales_share_percentage'])); ?></td>
                                        <td><?php echo esc_html($this->formatPercentage($row['profit_share_percentage'])); ?></td>
                                        <td><?php echo esc_html($this->formatLastOrder((int) $row['last_order_timestamp'])); ?></td>
                                        <td><?php $this->renderStatusBadge($row); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php $this->renderPagination($currentPage, $totalPages, $totalRows); ?>
            </section>

            <script id="hashieban-customer-profitability-data" type="application/json"><?php echo wp_json_encode($chartPayload); ?></script>
        </div>
        <?php
    }

    private function renderInsightCards(
        array $report,
        string $currency,
        int $precision
    ): void {
        $topRevenue = $report['top_by_revenue'][0] ?? null;
        $topProfit = $report['top_by_profit'][0] ?? null;
        $topOrders = $report['top_by_orders'][0] ?? null;

        ?>
        <section class="hb-customer-insights">
            <?php
            $this->renderInsight(
                'بیشترین خرید',
                is_array($topRevenue) ? (string) $topRevenue['name'] : '—',
                is_array($topRevenue)
                    ? Currency::formatMinor((int) $topRevenue['revenue_minor'], $currency, $precision)
                    : 'داده‌ای وجود ندارد',
                'sales'
            );

            $this->renderInsight(
                'سودآورترین مشتری',
                is_array($topProfit) ? (string) $topProfit['name'] : '—',
                is_array($topProfit)
                    ? Currency::formatMinor((int) $topProfit['profit_minor'], $currency, $precision)
                    : 'داده‌ای وجود ندارد',
                'profit'
            );

            $this->renderInsight(
                'بیشترین تعداد سفارش',
                is_array($topOrders) ? (string) $topOrders['name'] : '—',
                is_array($topOrders)
                    ? number_format_i18n((int) $topOrders['order_count']) . ' سفارش'
                    : 'داده‌ای وجود ندارد',
                'orders'
            );

            $this->renderInsight(
                'مشتری زیان‌ده',
                number_format_i18n((int) $report['loss_customer_count']) . ' مشتری',
                number_format_i18n((int) $report['low_margin_customer_count']) . ' مشتری کم‌حاشیه',
                'risk'
            );
            ?>
        </section>
        <?php
    }

    private function renderInsight(
        string $label,
        string $name,
        string $value,
        string $type
    ): void {
        ?>
        <div class="hb-customer-insight hb-customer-insight--<?php echo esc_attr($type); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($name); ?></strong>
            <small><?php echo esc_html($value); ?></small>
        </div>
        <?php
    }

    private function renderKpi(
        string $label,
        string $value,
        string $help
    ): void {
        ?>
        <div class="hb-customer-kpi">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </div>
        <?php
    }

    private function renderRankingList(
        string $title,
        array $rows,
        string $field,
        string $currency,
        int $precision,
        string $type
    ): void {
        ?>
        <div class="hb-customer-card">
            <div class="hb-customer-card__header">
                <div>
                    <h2><?php echo esc_html($title); ?></h2>
                    <p>برای تشخیص سریع ارزش و ریسک مالی مشتری</p>
                </div>
            </div>

            <div class="hb-customer-ranking-list">
                <?php if ($rows === array()) : ?>
                    <div class="hb-customer-empty">داده‌ای وجود ندارد.</div>
                <?php else : ?>
                    <?php $rank = 1; ?>
                    <?php foreach ($rows as $row) : ?>
                        <div class="hb-customer-ranking-row hb-customer-ranking-row--<?php echo esc_attr($type); ?>">
                            <span class="hb-customer-ranking-number"><?php echo esc_html(number_format_i18n($rank)); ?></span>
                            <div>
                                <strong><?php echo esc_html((string) $row['name']); ?></strong>
                                <small>
                                    <?php echo esc_html(number_format_i18n((int) $row['order_count'])); ?> سفارش
                                    · Margin <?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?>
                                </small>
                            </div>
                            <b><?php echo esc_html(Currency::formatMinor((int) $row[$field], $currency, $precision)); ?></b>
                        </div>
                        <?php $rank++; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
        <div class="hb-customer-range-card">
            <div class="hb-customer-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-customers', 'range' => $key), admin_url('admin.php'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-customer-custom-range">
                <input type="hidden" name="page" value="hashieban-customers">
                <input type="hidden" name="range" value="custom">

                <label>
                    از
                    <input type="text" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>" autocomplete="off" data-jdp>
                </label>

                <label>
                    تا
                    <input type="text" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>" autocomplete="off" data-jdp>
                </label>

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
        $revenueLabels = array();
        $revenueValues = array();
        $profitLabels = array();
        $profitValues = array();

        foreach ($report['top_by_revenue'] as $row) {
            $revenueLabels[] = (string) $row['name'];
            $revenueValues[] = Currency::minorToDisplayNumber(
                (int) $row['revenue_minor'],
                $currency,
                $precision
            );
        }

        foreach ($report['top_by_profit'] as $row) {
            $profitLabels[] = (string) $row['name'];
            $profitValues[] = Currency::minorToDisplayNumber(
                (int) $row['profit_minor'],
                $currency,
                $precision
            );
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'revenue' => array(
                'labels' => $revenueLabels,
                'values' => $revenueValues,
            ),
            'profit' => array(
                'labels' => $profitLabels,
                'values' => $profitValues,
            ),
        );
    }

    private function filterCustomers(array $customers): array
    {
        $search = $this->resolveSearch();

        if ($search === '') {
            return $customers;
        }

        $needle = function_exists('mb_strtolower')
            ? mb_strtolower($search, 'UTF-8')
            : strtolower($search);

        return array_values(
            array_filter(
                $customers,
                static function (array $row) use ($needle): bool {
                    $haystack = (string) ($row['name'] ?? '')
                        . ' ' . (string) ($row['email'] ?? '')
                        . ' ' . (string) ($row['phone'] ?? '');

                    $haystack = function_exists('mb_strtolower')
                        ? mb_strtolower($haystack, 'UTF-8')
                        : strtolower($haystack);

                    return strpos($haystack, $needle) !== false;
                }
            )
        );
    }

    private function sortCustomers(
        array $customers,
        string $sort
    ): array {
        $field = 'profit_minor';
        $descending = true;

        switch ($sort) {
            case 'revenue_desc':
                $field = 'revenue_minor';
                break;
            case 'orders_desc':
                $field = 'order_count';
                break;
            case 'aov_desc':
                $field = 'average_order_value_minor';
                break;
            case 'margin_desc':
                $field = 'margin_percentage';
                break;
            case 'profit_asc':
                $field = 'profit_minor';
                $descending = false;
                break;
            case 'profit_desc':
            default:
                $field = 'profit_minor';
                break;
        }

        usort(
            $customers,
            static function (array $a, array $b) use ($field, $descending): int {
                $left = $a[$field] ?? null;
                $right = $b[$field] ?? null;

                if ($left === null && $right === null) {
                    return 0;
                }
                if ($left === null) {
                    return 1;
                }
                if ($right === null) {
                    return -1;
                }
                if ($left == $right) {
                    return 0;
                }

                if ($descending) {
                    return $left < $right ? 1 : -1;
                }

                return $left > $right ? 1 : -1;
            }
        );

        return $customers;
    }

    private function resolveSearch(): string
    {
        return isset($_GET['q'])
            ? sanitize_text_field(wp_unslash($_GET['q']))
            : '';
    }

    private function resolveSort(): string
    {
        $sort = isset($_GET['sort'])
            ? sanitize_key(wp_unslash($_GET['sort']))
            : 'profit_desc';

        $allowed = array(
            'profit_desc',
            'revenue_desc',
            'orders_desc',
            'aov_desc',
            'margin_desc',
            'profit_asc',
        );

        return in_array($sort, $allowed, true)
            ? $sort
            : 'profit_desc';
    }

    private function renderPagination(
        int $currentPage,
        int $totalPages,
        int $totalRows
    ): void {
        if ($totalPages <= 1) {
            ?>
            <div class="hb-customer-pagination-summary"><?php echo esc_html(number_format_i18n($totalRows)); ?> مشتری</div>
            <?php
            return;
        }

        $baseArgs = array(
            'page' => 'hashieban-customers',
            'range' => isset($_GET['range'])
                ? sanitize_key(wp_unslash($_GET['range']))
                : '30d',
            'sort' => $this->resolveSort(),
        );

        $search = $this->resolveSearch();

        if ($search !== '') {
            $baseArgs['q'] = $search;
        }

        if ($baseArgs['range'] === 'custom') {
            $baseArgs['start_date'] = isset($_GET['start_date'])
                ? sanitize_text_field(wp_unslash($_GET['start_date']))
                : '';
            $baseArgs['end_date'] = isset($_GET['end_date'])
                ? sanitize_text_field(wp_unslash($_GET['end_date']))
                : '';
        }

        ?>
        <div class="hb-customer-pagination">
            <span><?php echo esc_html(number_format_i18n($totalRows)); ?> مشتری</span>
            <div>
                <?php if ($currentPage > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($baseArgs, array('paged' => $currentPage - 1)), admin_url('admin.php'))); ?>">قبلی</a>
                <?php endif; ?>

                <strong>
                    صفحه <?php echo esc_html(number_format_i18n($currentPage)); ?>
                    از <?php echo esc_html(number_format_i18n($totalPages)); ?>
                </strong>

                <?php if ($currentPage < $totalPages) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($baseArgs, array('paged' => $currentPage + 1)), admin_url('admin.php'))); ?>">بعدی</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function renderStatusBadge(array $row): void
    {
        $status = (string) ($row['financial_status'] ?? 'healthy');
        $label = 'سودآور';

        if ($status === 'loss') {
            $label = 'زیان‌ده';
        } elseif ($status === 'low_margin') {
            $label = 'کم‌حاشیه';
        }

        ?>
        <span class="hb-customer-status hb-customer-status--<?php echo esc_attr($status); ?>">
            <?php echo esc_html($label); ?>
        </span>
        <?php if ((bool) ($row['repeat_customer'] ?? false)) : ?>
            <span class="hb-customer-repeat">تکراری</span>
        <?php endif; ?>
        <?php if ((int) ($row['incomplete_orders'] ?? 0) > 0) : ?>
            <span class="hb-customer-data-warning">داده ناقص</span>
        <?php endif; ?>
        <?php
    }

    private function formatLastOrder(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '—';
        }

        $date = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(wp_timezone());

        return JalaliDate::format($date);
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
                'status' => array('processing', 'completed'),
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

        return $fallback->modify('-3 years')->setTime(0, 0, 0);
    }
}
