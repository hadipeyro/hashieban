<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\ProductProfitabilityService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class ProductProfitabilityPage
{
    private ProductProfitabilityService $analytics;

    public function __construct(
        ProductProfitabilityService $analytics
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

        list($start, $end, $range) =
            $this->resolveDateRange();

        $report = $this->analytics->getReport(
            $start,
            $end
        );

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];

        $products = $this->filterProducts(
            $report['products']
        );

        $sort = $this->resolveSort();
        $products = $this->sortProducts(
            $products,
            $sort
        );

        $perPage = 25;
        $currentPage = max(
            1,
            isset($_GET['paged'])
                ? absint(wp_unslash($_GET['paged']))
                : 1
        );

        $totalRows = count($products);
        $totalPages = max(
            1,
            (int) ceil($totalRows / $perPage)
        );

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $pageRows = array_slice(
            $products,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        $chartPayload = $this->buildChartPayload(
            $report,
            $currency,
            $precision
        );

        ?>
        <div class="wrap hb-product-page">
            <section class="hb-product-hero">
                <div>
                    <div class="hb-product-hero__eyebrow">حاشیه‌بان BI</div>
                    <h1>تحلیل سودآوری محصولات</h1>
                    <p>
                        ببین کدام محصول واقعاً فروش و سود می‌سازد،
                        کدام محصول فقط گردش مالی ایجاد می‌کند و کجا باید برای خرید بعدی دقیق‌تر تصمیم بگیری.
                    </p>

                    <div class="hb-product-hero__meta">
                        <span>
                            بازه:
                            <strong>
                                <?php
                                echo esc_html(
                                    JalaliDate::format($start)
                                    . ' تا '
                                    . JalaliDate::format($end)
                                );
                                ?>
                            </strong>
                        </span>
                        <span>
                            واحد:
                            <strong><?php echo esc_html(Currency::label($currency)); ?></strong>
                        </span>
                    </div>
                </div>

                <div class="hb-product-hero__profit">
                    <span>سود حاصل از فروش محصولات</span>
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
                        فروش خالص اقلام منهای COGS؛ هزینه‌های عمومی فروشگاه بین محصولات تخصیص داده نشده‌اند.
                    </small>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <?php if ((int) $report['orders_with_refunds'] > 0) : ?>
                <div class="hb-product-notice hb-product-notice--warning">
                    <strong>توجه به Refund:</strong>
                    در این بازه <?php echo esc_html(number_format_i18n((int) $report['orders_with_refunds'])); ?> سفارش دارای بازپرداخت است.
                    موتور دقیق تخصیص Refund به محصول در مرحله Refund & Returns تکمیل می‌شود؛ بنابراین این گزارش فعلاً برای آن سفارش‌ها باید با احتیاط تفسیر شود.
                </div>
            <?php endif; ?>

            <section class="hb-product-kpis">
                <?php
                $this->renderKpi(
                    'فروش محصولات',
                    Currency::formatMinor(
                        (int) $report['total_revenue_minor'],
                        $currency,
                        $precision
                    ),
                    'جمع فروش خالص اقلام سفارش‌ها'
                );

                $this->renderKpi(
                    'سود محصولات',
                    Currency::formatMinor(
                        (int) $report['total_profit_minor'],
                        $currency,
                        $precision
                    ),
                    'فروش محصول منهای COGS تاریخی همان اقلام'
                );

                $this->renderKpi(
                    'حاشیه سود وزنی',
                    $report['weighted_margin_percentage'] !== null
                        ? number_format_i18n(
                            (float) $report['weighted_margin_percentage'],
                            1
                        ) . '٪'
                        : '—',
                    'حاشیه سود کل محصولات نسبت به فروش محصولات'
                );

                $this->renderKpi(
                    'تعداد واحد فروخته‌شده',
                    number_format_i18n((int) $report['total_units']),
                    'مجموع تعداد اقلام فروخته‌شده'
                );

                $this->renderKpi(
                    'محصول فروخته‌شده',
                    number_format_i18n((int) $report['product_count']),
                    'محصول یا Variation متمایز در این بازه'
                );

                $this->renderKpi(
                    'محصول با COGS ناقص',
                    number_format_i18n((int) $report['products_with_missing_cogs']),
                    'محصولاتی که حداقل یک ردیف فروششان COGS قابل اتکا ندارد'
                );
                ?>
            </section>

            <?php $this->renderInsightCards($report, $currency, $precision); ?>

            <section class="hb-product-chart-grid">
                <div class="hb-product-card hb-product-card--chart">
                    <div class="hb-product-card__header">
                        <div>
                            <h2>بیشترین فروش محصول</h2>
                            <p>۱۰ محصول برتر بر اساس مبلغ فروش در بازه انتخابی</p>
                        </div>
                    </div>
                    <div class="hb-product-chart-wrap">
                        <canvas id="hashieban-product-revenue-chart"></canvas>
                    </div>
                </div>

                <div class="hb-product-card hb-product-card--chart">
                    <div class="hb-product-card__header">
                        <div>
                            <h2>بیشترین سود محصول</h2>
                            <p>۱۰ محصول برتر بر اساس سود حاصل از فروش کالا</p>
                        </div>
                    </div>
                    <div class="hb-product-chart-wrap">
                        <canvas id="hashieban-product-profit-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-product-ranking-grid">
                <?php
                $this->renderRankingList(
                    'پرسودترین محصولات',
                    $report['top_by_profit'],
                    'profit_minor',
                    $currency,
                    $precision,
                    'profit'
                );

                $this->renderRankingList(
                    'کم‌سودترین / زیان‌ده‌ها',
                    $report['bottom_by_profit'],
                    'profit_minor',
                    $currency,
                    $precision,
                    'risk'
                );
                ?>
            </section>

            <section class="hb-product-card hb-product-table-card">
                <div class="hb-product-table-toolbar">
                    <div>
                        <h2>جزئیات محصولات</h2>
                        <p>
                            سهم از فروش و سود، تعداد فروش، Margin و وضعیت COGS هر محصول را مقایسه کن.
                        </p>
                    </div>

                    <form method="get" class="hb-product-search-form">
                        <input type="hidden" name="page" value="hashieban-products">
                        <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">

                        <?php if ($range === 'custom') : ?>
                            <input type="hidden" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>">
                            <input type="hidden" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>">
                        <?php endif; ?>

                        <input
                            type="search"
                            name="q"
                            value="<?php echo esc_attr($this->resolveSearch()); ?>"
                            placeholder="جست‌وجوی نام یا SKU"
                        >

                        <select name="sort">
                            <?php
                            $sortOptions = array(
                                'profit_desc' => 'بیشترین سود',
                                'revenue_desc' => 'بیشترین فروش',
                                'quantity_desc' => 'بیشترین تعداد فروش',
                                'margin_desc' => 'بیشترین Margin',
                                'profit_asc' => 'کمترین سود / زیان‌ده',
                                'margin_asc' => 'کمترین Margin',
                            );
                            ?>
                            <?php foreach ($sortOptions as $value => $label) : ?>
                                <option
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php selected($sort, $value); ?>
                                >
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="button button-primary">اعمال</button>
                    </form>
                </div>

                <div class="hb-product-table-wrap">
                    <table class="widefat striped hb-product-table">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>SKU</th>
                                <th>تعداد فروش</th>
                                <th>سفارش</th>
                                <th>فروش</th>
                                <th>COGS</th>
                                <th>سود</th>
                                <th>Margin</th>
                                <th>سهم از فروش</th>
                                <th>سهم از سود</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pageRows === array()) : ?>
                                <tr>
                                    <td colspan="11" class="hb-product-empty">
                                        محصولی برای این فیلتر پیدا نشد.
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($pageRows as $row) : ?>
                                    <?php
                                    $profit = (int) $row['profit_minor'];
                                    $rowClass = $profit < 0
                                        ? 'is-loss'
                                        : '';
                                    ?>
                                    <tr class="<?php echo esc_attr($rowClass); ?>">
                                        <td class="hb-product-name-cell">
                                            <?php if ((string) $row['edit_url'] !== '') : ?>
                                                <a href="<?php echo esc_url((string) $row['edit_url']); ?>">
                                                    <?php echo esc_html((string) $row['name']); ?>
                                                </a>
                                            <?php else : ?>
                                                <strong><?php echo esc_html((string) $row['name']); ?></strong>
                                            <?php endif; ?>

                                            <small>
                                                رتبه سود #<?php echo esc_html(number_format_i18n((int) $row['profit_rank'])); ?>
                                                · رتبه فروش #<?php echo esc_html(number_format_i18n((int) $row['revenue_rank'])); ?>
                                            </small>
                                        </td>
                                        <td><?php echo esc_html((string) $row['sku'] !== '' ? (string) $row['sku'] : '—'); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $row['quantity'])); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $row['order_count'])); ?></td>
                                        <td>
                                            <?php
                                            echo esc_html(
                                                Currency::formatMinor(
                                                    (int) $row['revenue_minor'],
                                                    $currency,
                                                    $precision
                                                )
                                            );
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo esc_html(
                                                Currency::formatMinor(
                                                    (int) $row['cogs_minor'],
                                                    $currency,
                                                    $precision
                                                )
                                            );
                                            ?>
                                        </td>
                                        <td>
                                            <strong class="<?php echo $profit < 0 ? 'hb-value-loss' : 'hb-value-profit'; ?>">
                                                <?php
                                                echo esc_html(
                                                    Currency::formatMinor(
                                                        $profit,
                                                        $currency,
                                                        $precision
                                                    )
                                                );
                                                ?>
                                            </strong>
                                        </td>
                                        <td><?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?></td>
                                        <td><?php echo esc_html($this->formatPercentage($row['sales_share_percentage'])); ?></td>
                                        <td><?php echo esc_html($this->formatPercentage($row['profit_share_percentage'])); ?></td>
                                        <td>
                                            <?php if (! (bool) $row['cogs_complete']) : ?>
                                                <span class="hb-status-pill hb-status-pill--warning">COGS ناقص</span>
                                            <?php elseif ($profit < 0) : ?>
                                                <span class="hb-status-pill hb-status-pill--danger">زیان‌ده</span>
                                            <?php elseif ((int) $row['profit_rank'] <= 3) : ?>
                                                <span class="hb-status-pill hb-status-pill--success">پرسود</span>
                                            <?php elseif ((int) $row['quantity_rank'] <= 3) : ?>
                                                <span class="hb-status-pill hb-status-pill--info">پرفروش</span>
                                            <?php else : ?>
                                                <span class="hb-status-pill">عادی</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $this->renderPagination(
                    $currentPage,
                    $totalPages,
                    $totalRows
                );
                ?>
            </section>

            <script
                id="hashieban-product-profitability-data"
                type="application/json"
            ><?php echo wp_json_encode($chartPayload); ?></script>
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
        $topQuantity = $report['top_by_quantity'][0] ?? null;

        ?>
        <section class="hb-product-insights">
            <?php
            $this->renderInsight(
                'پرفروش‌ترین از نظر مبلغ',
                is_array($topRevenue)
                    ? (string) $topRevenue['name']
                    : '—',
                is_array($topRevenue)
                    ? Currency::formatMinor(
                        (int) $topRevenue['revenue_minor'],
                        $currency,
                        $precision
                    )
                    : 'داده‌ای وجود ندارد',
                'sales'
            );

            $this->renderInsight(
                'پرسودترین محصول',
                is_array($topProfit)
                    ? (string) $topProfit['name']
                    : '—',
                is_array($topProfit)
                    ? Currency::formatMinor(
                        (int) $topProfit['profit_minor'],
                        $currency,
                        $precision
                    )
                    : 'داده‌ای وجود ندارد',
                'profit'
            );

            $this->renderInsight(
                'بیشترین تعداد فروش',
                is_array($topQuantity)
                    ? (string) $topQuantity['name']
                    : '—',
                is_array($topQuantity)
                    ? number_format_i18n((int) $topQuantity['quantity']) . ' عدد'
                    : 'داده‌ای وجود ندارد',
                'quantity'
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
        <div class="hb-product-insight hb-product-insight--<?php echo esc_attr($type); ?>">
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
        <div class="hb-product-kpi">
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
        <div class="hb-product-card">
            <div class="hb-product-card__header">
                <div>
                    <h2><?php echo esc_html($title); ?></h2>
                    <p>برای مقایسه سریع تصمیم خرید و تأمین</p>
                </div>
            </div>

            <div class="hb-product-ranking-list">
                <?php if ($rows === array()) : ?>
                    <div class="hb-product-empty">داده‌ای وجود ندارد.</div>
                <?php else : ?>
                    <?php $rank = 1; ?>
                    <?php foreach ($rows as $row) : ?>
                        <div class="hb-product-ranking-row hb-product-ranking-row--<?php echo esc_attr($type); ?>">
                            <span class="hb-product-ranking-number"><?php echo esc_html(number_format_i18n($rank)); ?></span>
                            <div>
                                <strong><?php echo esc_html((string) $row['name']); ?></strong>
                                <small>
                                    <?php echo esc_html(number_format_i18n((int) $row['quantity'])); ?> فروش
                                    · Margin <?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?>
                                </small>
                            </div>
                            <b>
                                <?php
                                echo esc_html(
                                    Currency::formatMinor(
                                        (int) $row[$field],
                                        $currency,
                                        $precision
                                    )
                                );
                                ?>
                            </b>
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
            'all' => 'همه',
        );

        ?>
        <div class="hb-product-range-bar">
            <div class="hb-product-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(
                            array(
                                'page' => 'hashieban-products',
                                'range' => $key,
                            ),
                            admin_url('admin.php')
                        )); ?>"
                    >
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-product-custom-range">
                <input type="hidden" name="page" value="hashieban-products">
                <input type="hidden" name="range" value="custom">

                <label>
                    از
                    <input
                        type="text"
                        name="start_date"
                        value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>"
                        autocomplete="off"
                        data-jdp
                    >
                </label>

                <label>
                    تا
                    <input
                        type="text"
                        name="end_date"
                        value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>"
                        autocomplete="off"
                        data-jdp
                    >
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

    private function filterProducts(
        array $products
    ): array {
        $search = $this->resolveSearch();

        if ($search === '') {
            return $products;
        }

        $needle = function_exists('mb_strtolower')
            ? mb_strtolower($search, 'UTF-8')
            : strtolower($search);

        return array_values(
            array_filter(
                $products,
                static function (array $row) use ($needle): bool {
                    $haystack = (string) ($row['name'] ?? '')
                        . ' '
                        . (string) ($row['sku'] ?? '');

                    $haystack = function_exists('mb_strtolower')
                        ? mb_strtolower($haystack, 'UTF-8')
                        : strtolower($haystack);

                    return strpos($haystack, $needle) !== false;
                }
            )
        );
    }

    private function sortProducts(
        array $products,
        string $sort
    ): array {
        $field = 'profit_minor';
        $descending = true;

        switch ($sort) {
            case 'revenue_desc':
                $field = 'revenue_minor';
                break;

            case 'quantity_desc':
                $field = 'quantity';
                break;

            case 'margin_desc':
                $field = 'margin_percentage';
                break;

            case 'profit_asc':
                $field = 'profit_minor';
                $descending = false;
                break;

            case 'margin_asc':
                $field = 'margin_percentage';
                $descending = false;
                break;

            case 'profit_desc':
            default:
                $field = 'profit_minor';
                break;
        }

        usort(
            $products,
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

        return $products;
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
            'quantity_desc',
            'margin_desc',
            'profit_asc',
            'margin_asc',
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
            <div class="hb-product-pagination-summary">
                <?php echo esc_html(number_format_i18n($totalRows)); ?> محصول
            </div>
            <?php
            return;
        }

        $baseArgs = array(
            'page' => 'hashieban-products',
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
        <div class="hb-product-pagination">
            <span><?php echo esc_html(number_format_i18n($totalRows)); ?> محصول</span>
            <div>
                <?php if ($currentPage > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg(
                        array_merge(
                            $baseArgs,
                            array('paged' => $currentPage - 1)
                        ),
                        admin_url('admin.php')
                    )); ?>">قبلی</a>
                <?php endif; ?>

                <strong>
                    صفحه <?php echo esc_html(number_format_i18n($currentPage)); ?>
                    از <?php echo esc_html(number_format_i18n($totalPages)); ?>
                </strong>

                <?php if ($currentPage < $totalPages) : ?>
                  <a href="<?php echo esc_url(add_query_arg(
                           array_merge(
                               $baseArgs,
                               array('paged' => $currentPage + 1)
                           ),
                           admin_url('admin.php')
						   )); ?>">بعدی</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
		}

		private function formatPercentage(
			$value
		): string {
			if ($value === null) {
				return '—';
			}

			return number_format_i18n(
				(float) $value,
				1
			) . '٪';
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
						return array(
							$custom[0],
							$custom[1],
							'custom',
						);
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

			return array(
				$start,
				$end,
				$range,
			);
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
					'status' => array(
						'processing',
						'completed',
					),
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
					return (new DateTimeImmutable(
						'@' . $date->getTimestamp()
					))
								   ->setTimezone(wp_timezone())
								   ->setTime(0, 0, 0);
				}
			}

			return $fallback
            ->modify('-3 years')
            ->setTime(0, 0, 0);
		}

		}
