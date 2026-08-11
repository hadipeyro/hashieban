<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Json;
use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\InventoryPurchaseInsightService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class InventoryPurchaseInsightPage
{
    private InventoryPurchaseInsightService $analytics;

    public function __construct(
        InventoryPurchaseInsightService $analytics
    ) {
        $this->analytics = $analytics;
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(
                esc_html('شما اجازه دسترسی به این بخش را ندارید.')
            );
        }

        list($start, $end, $range) =
            $this->resolveDateRange();

        $leadDays = isset($_GET['lead_days'])
            ? max(1, min(180, absint(wp_unslash($_GET['lead_days']))))
            : 14;

        $targetCoverDays = isset($_GET['target_cover_days'])
            ? max(7, min(365, absint(wp_unslash($_GET['target_cover_days']))))
            : 30;

        $report = $this->analytics->getReport(
            $start,
            $end,
            $leadDays,
            $targetCoverDays
        );

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $summary = (array) $report['summary'];

        $rows = $this->filterRows(
            (array) $report['products']
        );

        $sort = $this->resolveSort();
        $rows = $this->sortRows(
            $rows,
            $sort
        );

        $perPage = 25;
        $currentPage = max(
            1,
            isset($_GET['paged'])
                ? absint(wp_unslash($_GET['paged']))
                : 1
        );

        $totalRows = count($rows);
        $totalPages = max(
            1,
            (int) ceil($totalRows / $perPage)
        );

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $pageRows = array_slice(
            $rows,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        $chartPayload = $this->buildChartPayload(
            $report,
            $currency,
            $precision
        );

        ?>
        <div class="wrap hb-inventory-page">
            <section class="hb-inventory-hero">
                <div>
                    <span class="hb-inventory-hero__eyebrow">حاشیه‌بان · موجودی و خرید</span>
                    <h1>موجودی و پیشنهاد خرید</h1>
                    <p>
                        بفهم کدام کالا در آستانه اتمام است، کدام موجودی سرمایه را خوابانده
                        و برای خرید بعدی چه مقدار تقریبی منطقی‌تر است.
                    </p>

                    <div class="hb-inventory-hero__meta">
                        <span>
                            بازه فروش:
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

                <div class="hb-inventory-hero__score">
                    <span>پیشنهاد خرید برآوردی</span>
                    <strong>
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                (int) $summary['suggested_purchase_units']
                            )
                        );
                        ?>
                        واحد
                    </strong>
                    <small>
                        بر اساس سرعت فروش همین بازه، زمان تأمین
                        <?php echo esc_html(number_format_i18n($leadDays)); ?>
                        روز و پوشش هدف
                        <?php echo esc_html(number_format_i18n($targetCoverDays)); ?>
                        روز.
                    </small>
                </div>
            </section>

            <?php
            $this->renderControls(
                $range,
                $start,
                $end,
                $leadDays,
                $targetCoverDays
            );
            ?>

            <div class="hb-inventory-notice">
                <strong>نکته مدیریتی:</strong>
                پیشنهاد خرید یک تخمین تصمیم‌یار است، نه سفارش خرید خودکار.
                اگر موجودی یک محصول در ووکامرس ردیابی نشود یا هزینه خرید نداشته باشد،
                حاشیه‌بان آن را شفاف علامت می‌زند و عدد جعلی تولید نمی‌کند.
            </div>

            <section class="hb-inventory-kpis">
                <?php
                $this->renderKpi(
                    'ارزش موجودی با بهای خرید',
                    Currency::formatMinor(
                        (int) $summary['inventory_value_minor'],
                        $currency,
                        $precision
                    ),
                    'فقط کالاهای دارای موجودی و هزینه خرید قابل محاسبه'
                );

                $this->renderKpi(
                    'نیاز به سفارش مجدد',
                    number_format_i18n(
                        (int) $summary['reorder_now']
                    ),
                    'موجودی فعلی به نقطه سفارش برآوردی رسیده است'
                );

                $this->renderKpi(
                    'ناموجود',
                    number_format_i18n(
                        (int) $summary['out_of_stock']
                    ),
                    'کالاهای Stock-managed با موجودی صفر یا وضعیت ناموجود'
                );

                $this->renderKpi(
                    'سرمایه کم‌گردش',
                    Currency::formatMinor(
                        (int) $summary['dead_stock_value_minor'],
                        $currency,
                        $precision
                    ),
                    'موجودی بدون فروش یا با پوشش بسیار طولانی'
                );

                $this->renderKpi(
                    'بودجه تقریبی خرید پیشنهادی',
                    Currency::formatMinor(
                        (int) $summary['suggested_purchase_value_minor'],
                        $currency,
                        $precision
                    ),
                    'فقط برای کالاهایی که هزینه خرید فعلی دارند'
                );

                $this->renderKpi(
                    'ردیابی موجودی خاموش',
                    number_format_i18n(
                        (int) $summary['untracked_products']
                    ),
                    'برای این کالاها پیشنهاد خرید دقیق قابل اتکا نیست'
                );

                $this->renderKpi(
                    'هزینه خرید ناقص',
                    number_format_i18n(
                        (int) $summary['missing_cogs']
                    ),
                    'ارزش ریالی موجودی یا خرید پیشنهادی برای این کالاها ناقص است'
                );

                $this->renderKpi(
                    'موجودی سالم',
                    number_format_i18n(
                        (int) $summary['healthy']
                    ),
                    'نه در نقطه سفارش است و نه کم‌گردش تشخیص داده شده'
                );
                ?>
            </section>

            <section class="hb-inventory-chart-grid">
                <div class="hb-inventory-card hb-inventory-card--chart">
                    <div class="hb-inventory-card__header">
                        <div>
                            <h2>اولویت خرید مجدد</h2>
                            <p>محصولاتی که بر اساس سرعت فروش و موجودی فعلی، بیشترین کسری برآوردی دارند.</p>
                        </div>
                    </div>
                    <div class="hb-inventory-chart-wrap">
                        <canvas id="hashieban-inventory-reorder-chart"></canvas>
                    </div>
                </div>

                <div class="hb-inventory-card hb-inventory-card--chart">
                    <div class="hb-inventory-card__header">
                        <div>
                            <h2>سرمایه موجودی بر اساس وضعیت</h2>
                            <p>ببین چه مقدار از سرمایه فعلی در موجودی سالم، کم‌گردش یا پرریسک قرار گرفته است.</p>
                        </div>
                    </div>
                    <div class="hb-inventory-chart-wrap hb-inventory-chart-wrap--doughnut">
                        <canvas id="hashieban-inventory-status-chart"></canvas>
                    </div>
                </div>

                <div class="hb-inventory-card hb-inventory-card--chart">
                    <div class="hb-inventory-card__header">
                        <div>
                            <h2>سریع‌ترین گردش کالا</h2>
                            <p>میانگین واحد فروخته‌شده در روز در بازه انتخاب‌شده.</p>
                        </div>
                    </div>
                    <div class="hb-inventory-chart-wrap">
                        <canvas id="hashieban-inventory-velocity-chart"></canvas>
                    </div>
                </div>

                <div class="hb-inventory-card hb-inventory-card--chart">
                    <div class="hb-inventory-card__header">
                        <div>
                            <h2>بیشترین سرمایه در موجودی</h2>
                            <p>محصولاتی که بیشترین مبلغ خرید فعلی در قفسه یا انبار نگه داشته‌اند.</p>
                        </div>
                    </div>
                    <div class="hb-inventory-chart-wrap">
                        <canvas id="hashieban-inventory-capital-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-inventory-ranking-grid">
                <?php
                $this->renderRanking(
                    'خرید بعدی پیشنهادی',
                    (array) $report['top_reorder'],
                    'reorder',
                    $currency,
                    $precision
                );

                $this->renderRanking(
                    'بیشترین سرمایه خوابیده',
                    array_values(
                        array_filter(
                            (array) $report['top_capital'],
                            static function (array $row): bool {
                                return (string) $row['status'] === 'slow';
                            }
                        )
                    ),
                    'capital',
                    $currency,
                    $precision
                );
                ?>
            </section>

            <section class="hb-inventory-card hb-inventory-table-card">
                <div class="hb-inventory-table-toolbar">
                    <div>
                        <h2>جزئیات موجودی محصولات</h2>
                        <p>موجودی، سرعت فروش، پوشش روزانه، نقطه سفارش و پیشنهاد خرید را کنار هم ببین.</p>
                    </div>

                    <form method="get" class="hb-inventory-search">
                        <input type="hidden" name="page" value="hashieban-inventory">
                        <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">
                        <input type="hidden" name="lead_days" value="<?php echo esc_attr((string) $leadDays); ?>">
                        <input type="hidden" name="target_cover_days" value="<?php echo esc_attr((string) $targetCoverDays); ?>">

                        <?php if ($range === 'custom') : ?>
                            <input type="hidden" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>">
                            <input type="hidden" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>">
                        <?php endif; ?>

                        <input
                            type="search"
                            name="s"
                            value="<?php echo esc_attr($this->searchQuery()); ?>"
                            placeholder="نام یا SKU محصول..."
                        >

                        <select name="inventory_status">
                            <?php
                            $statusFilter = $this->statusFilter();
                            $options = array(
                                '' => 'همه وضعیت‌ها',
                                'out' => 'ناموجود',
                                'reorder' => 'نیاز به خرید',
                                'healthy' => 'سالم',
                                'slow' => 'کم‌گردش / راکد',
                                'untracked' => 'بدون ردیابی موجودی',
                            );

                            foreach ($options as $key => $label) :
                                ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($statusFilter, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php
                            endforeach;
                            ?>
                        </select>

                        <select name="sort">
                            <?php
                            $sortOptions = array(
                                'priority' => 'اولویت خرید',
                                'velocity' => 'سرعت فروش',
                                'stock' => 'موجودی',
                                'cover' => 'پوشش موجودی',
                                'capital' => 'ارزش موجودی',
                                'profit' => 'سود محصول',
                            );

                            foreach ($sortOptions as $key => $label) :
                                ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($sort, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php
                            endforeach;
                            ?>
                        </select>

                        <button class="button button-primary" type="submit">اعمال</button>
                    </form>
                </div>

                <div class="hb-inventory-table-wrap">
                    <table class="widefat striped hb-inventory-table">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>وضعیت</th>
                                <th>موجودی</th>
                                <th>فروش بازه</th>
                                <th>سرعت روزانه</th>
                                <th>پوشش موجودی</th>
                                <th>نقطه سفارش</th>
                                <th>خرید پیشنهادی</th>
                                <th>ارزش موجودی</th>
                                <th>سود بازه</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pageRows)) : ?>
                                <tr>
                                    <td colspan="10">
                                        داده‌ای با این فیلتر پیدا نشد.
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($pageRows as $row) : ?>
                                    <tr>
                                        <td>
                                            <?php if ((string) $row['edit_url'] !== '') : ?>
                                                <a class="hb-inventory-product-link" href="<?php echo esc_url((string) $row['edit_url']); ?>">
                                                    <?php echo esc_html((string) $row['name']); ?>
                                                </a>
                                            <?php else : ?>
                                                <strong><?php echo esc_html((string) $row['name']); ?></strong>
                                            <?php endif; ?>

                                            <small>
                                                <?php
                                                echo esc_html(
                                                    (string) $row['sku'] !== ''
                                                        ? 'SKU: ' . (string) $row['sku']
                                                        : 'بدون SKU'
                                                );
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php $this->renderStatusBadge((string) $row['status']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo $row['stock_quantity'] === null
                                                ? '—'
                                                : esc_html(number_format_i18n((float) $row['stock_quantity'], 0));
                                            ?>
                                        </td>
                                        <td><?php echo esc_html(number_format_i18n((float) $row['sold_units'], 0)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((float) $row['daily_velocity'], 2)); ?></td>
                                        <td>
                                            <?php
                                            echo $row['days_cover'] === null
                                                ? '—'
                                                : esc_html(number_format_i18n((float) $row['days_cover'], 0) . ' روز');
                                            ?>
                                        </td>
                                        <td><?php echo esc_html(number_format_i18n((int) $row['reorder_point_units'])); ?></td>
                                        <td>
                                            <strong>
                                                <?php echo esc_html(number_format_i18n((int) $row['suggested_purchase_units'])); ?>
                                            </strong>
                                            <?php if ((int) $row['suggested_purchase_units'] > 0) : ?>
                                                <small>
                                                    <?php
                                                    echo ! empty($row['cogs_missing'])
                                                        ? esc_html('بودجه نامشخص؛ هزینه خرید ناقص')
                                                        : esc_html(
                                                            Currency::formatMinor(
                                                                (int) $row['suggested_purchase_value_minor'],
                                                                $currency,
                                                                $precision
                                                            )
                                                        );
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo ! empty($row['cogs_missing'])
                                                ? esc_html('—')
                                                : esc_html(
                                                    Currency::formatMinor(
                                                        (int) $row['inventory_value_minor'],
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
                                                    (int) $row['profit_minor'],
                                                    $currency,
                                                    $precision
                                                )
                                            );
                                            ?>
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
                    $totalPages
                );
                ?>
            </section>

            <script type="application/json" id="hashieban-inventory-chart-data"><?php
                echo Json::forHtmlScript($chartPayload);
            ?></script>
        </div>
        <?php
    }

    private function renderControls(
        string $range,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $leadDays,
        int $targetCoverDays
    ): void {
        $ranges = array(
            '7d' => '۷ روز',
            '30d' => '۳۰ روز',
            '90d' => '۹۰ روز',
            '6m' => '۶ ماه',
            'year' => '۱ سال',
            'all' => 'همه',
        );
        ?>
        <section class="hb-inventory-controls">
            <div class="hb-inventory-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(
                            array(
                                'page' => 'hashieban-inventory',
                                'range' => $key,
                                'lead_days' => $leadDays,
                                'target_cover_days' => $targetCoverDays,
                            ),
                            admin_url('admin.php')
                        )); ?>"
                    >
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-inventory-scenario-form">
                <input type="hidden" name="page" value="hashieban-inventory">
                <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">

                <?php if ($range === 'custom') : ?>
                    <input type="hidden" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>">
                    <input type="hidden" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>">
                <?php endif; ?>

                <label>
                    زمان تأمین
                    <input type="number" min="1" max="180" name="lead_days" value="<?php echo esc_attr((string) $leadDays); ?>">
                    روز
                </label>

                <label>
                    پوشش هدف بعد از خرید
                    <input type="number" min="7" max="365" name="target_cover_days" value="<?php echo esc_attr((string) $targetCoverDays); ?>">
                    روز
                </label>

                <button class="button button-primary" type="submit">محاسبه سناریو</button>
            </form>

            <form method="get" class="hb-inventory-custom-range">
                <input type="hidden" name="page" value="hashieban-inventory">
                <input type="hidden" name="range" value="custom">
                <input type="hidden" name="lead_days" value="<?php echo esc_attr((string) $leadDays); ?>">
                <input type="hidden" name="target_cover_days" value="<?php echo esc_attr((string) $targetCoverDays); ?>">

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

                <button class="button" type="submit">بازه سفارشی</button>
            </form>
        </section>
        <?php
    }

    private function renderKpi(
        string $label,
        string $value,
        string $description
    ): void {
        ?>
        <article class="hb-inventory-kpi">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($description); ?></small>
        </article>
        <?php
    }

    private function renderRanking(
        string $title,
        array $rows,
        string $mode,
        string $currency,
        int $precision
    ): void {
        ?>
        <div class="hb-inventory-card hb-inventory-ranking">
            <div class="hb-inventory-card__header">
                <div>
                    <h2><?php echo esc_html($title); ?></h2>
                </div>
            </div>

            <?php if (empty($rows)) : ?>
                <p class="hb-inventory-empty">فعلاً موردی در این بخش نیست.</p>
            <?php else : ?>
                <ol>
                    <?php foreach (array_slice($rows, 0, 7) as $row) : ?>
                        <li>
                            <div>
                                <?php if ((string) $row['edit_url'] !== '') : ?>
                                    <a href="<?php echo esc_url((string) $row['edit_url']); ?>">
                                        <?php echo esc_html((string) $row['name']); ?>
                                    </a>
                                <?php else : ?>
                                    <strong><?php echo esc_html((string) $row['name']); ?></strong>
                                <?php endif; ?>
                                <small>
                                    <?php
                                    echo esc_html(
                                        number_format_i18n((float) $row['sold_units'], 0)
                                        . ' واحد فروش در بازه'
                                    );
                                    ?>
                                </small>
                            </div>

                            <strong>
                                <?php
                                if ($mode === 'reorder') {
                                    echo esc_html(
                                        number_format_i18n(
                                            (int) $row['suggested_purchase_units']
                                        )
                                        . ' واحد'
                                    );
                                } else {
                                    echo esc_html(
                                        Currency::formatMinor(
                                            (int) $row['inventory_value_minor'],
                                            $currency,
                                            $precision
                                        )
                                    );
                                }
                                ?>
                            </strong>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderStatusBadge(
        string $status
    ): void {
        $map = array(
            'out' => array('ناموجود', 'danger'),
            'reorder' => array('نیاز به خرید', 'warning'),
            'healthy' => array('سالم', 'success'),
            'slow' => array('کم‌گردش', 'muted'),
            'untracked' => array('بدون ردیابی', 'neutral'),
        );

        $row = $map[$status] ?? array('نامشخص', 'neutral');

        ?>
        <span class="hb-inventory-badge hb-inventory-badge--<?php echo esc_attr((string) $row[1]); ?>">
            <?php echo esc_html((string) $row[0]); ?>
        </span>
        <?php
    }

    private function buildChartPayload(
        array $report,
        string $currency,
        int $precision
    ): array {
        $statusValues = array(
            'healthy' => 0.0,
            'reorder' => 0.0,
            'out' => 0.0,
            'slow' => 0.0,
        );

        foreach ((array) $report['products'] as $row) {
            $status = (string) $row['status'];

            if (! isset($statusValues[$status])) {
                continue;
            }

            $statusValues[$status] += Currency::minorToDisplayNumber(
                (int) $row['inventory_value_minor'],
                $currency,
                $precision
            );
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'reorder' => array(
                'labels' => array_map(
                    static function (array $row): string {
                        return (string) $row['name'];
                    },
                    (array) $report['top_reorder']
                ),
                'values' => array_map(
                    static function (array $row): int {
                        return (int) $row['suggested_purchase_units'];
                    },
                    (array) $report['top_reorder']
                ),
                'urls' => array_map(
                    static function (array $row): string {
                        return (string) $row['edit_url'];
                    },
                    (array) $report['top_reorder']
                ),
            ),
            'velocity' => array(
                'labels' => array_map(
                    static function (array $row): string {
                        return (string) $row['name'];
                    },
                    (array) $report['fast_movers']
                ),
                'values' => array_map(
                    static function (array $row): float {
                        return round((float) $row['daily_velocity'], 2);
                    },
                    (array) $report['fast_movers']
                ),
                'urls' => array_map(
                    static function (array $row): string {
                        return (string) $row['edit_url'];
                    },
                    (array) $report['fast_movers']
                ),
            ),
            'capital' => array(
                'labels' => array_map(
                    static function (array $row): string {
                        return (string) $row['name'];
                    },
                    (array) $report['top_capital']
                ),
                'values' => array_map(
                    static function (array $row) use ($currency, $precision): float {
                        return Currency::minorToDisplayNumber(
                            (int) $row['inventory_value_minor'],
                            $currency,
                            $precision
                        );
                    },
                    (array) $report['top_capital']
                ),
                'urls' => array_map(
                    static function (array $row): string {
                        return (string) $row['edit_url'];
                    },
                    (array) $report['top_capital']
                ),
            ),
            'status' => array(
                'labels' => array(
                    'سالم',
                    'نیاز به خرید',
                    'ناموجود',
                    'کم‌گردش',
                ),
                'values' => array(
                    $statusValues['healthy'],
                    $statusValues['reorder'],
                    $statusValues['out'],
                    $statusValues['slow'],
                ),
            ),
        );
    }

    private function filterRows(
        array $rows
    ): array {
        $rawSearch = $this->searchQuery();

        $search = function_exists('mb_strtolower')
            ? mb_strtolower($rawSearch, 'UTF-8')
            : strtolower($rawSearch);

        $status = $this->statusFilter();

        return array_values(
            array_filter(
                $rows,
                static function (array $row) use ($search, $status): bool {
                    if (
                        $status !== ''
                        && (string) $row['status'] !== $status
                    ) {
                        return false;
                    }

                    if ($search === '') {
                        return true;
                    }

                    $rawHaystack =
                        (string) $row['name']
                        . ' '
                        . (string) $row['sku'];

                    $haystack = function_exists('mb_strtolower')
                        ? mb_strtolower($rawHaystack, 'UTF-8')
                        : strtolower($rawHaystack);

                    if (function_exists('mb_strpos')) {
                        return mb_strpos(
                            $haystack,
                            $search,
                            0,
                            'UTF-8'
                        ) !== false;
                    }

                    return strpos(
                        $haystack,
                        $search
                    ) !== false;
                }
            )
        );
    }

    private function sortRows(
        array $rows,
        string $sort
    ): array {
        $priority = array(
            'out' => 0,
            'reorder' => 1,
            'healthy' => 2,
            'slow' => 3,
            'untracked' => 4,
        );

        usort(
            $rows,
            static function (array $a, array $b) use ($sort, $priority): int {
                switch ($sort) {
                    case 'velocity':
                        return (float) $b['daily_velocity']
                            <=> (float) $a['daily_velocity'];

                    case 'stock':
                        return (float) ($b['stock_quantity'] ?? -1)
                            <=> (float) ($a['stock_quantity'] ?? -1);

                    case 'cover':
                        $aValue = $a['days_cover'] === null
                            ? PHP_FLOAT_MAX
                            : (float) $a['days_cover'];
                        $bValue = $b['days_cover'] === null
                            ? PHP_FLOAT_MAX
                            : (float) $b['days_cover'];

                        return $aValue <=> $bValue;

                    case 'capital':
                        return (int) $b['inventory_value_minor']
                            <=> (int) $a['inventory_value_minor'];

                    case 'profit':
                        return (int) $b['profit_minor']
                            <=> (int) $a['profit_minor'];

                    case 'priority':
                    default:
                        $aPriority = $priority[(string) $a['status']] ?? 9;
                        $bPriority = $priority[(string) $b['status']] ?? 9;

                        if ($aPriority !== $bPriority) {
                            return $aPriority <=> $bPriority;
                        }

                        return (int) $b['suggested_purchase_units']
                            <=> (int) $a['suggested_purchase_units'];
                }
            }
        );

        return $rows;
    }

    private function resolveSort(): string
    {
        $sort = isset($_GET['sort'])
            ? sanitize_key(wp_unslash($_GET['sort']))
            : 'priority';

        $allowed = array(
            'priority',
            'velocity',
            'stock',
            'cover',
            'capital',
            'profit',
        );

        return in_array($sort, $allowed, true)
            ? $sort
            : 'priority';
    }

    private function searchQuery(): string
    {
        return isset($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s']))
            : '';
    }

    private function statusFilter(): string
    {
        $status = isset($_GET['inventory_status'])
            ? sanitize_key(wp_unslash($_GET['inventory_status']))
            : '';

        $allowed = array(
            '',
            'out',
            'reorder',
            'healthy',
            'slow',
            'untracked',
        );

        return in_array($status, $allowed, true)
            ? $status
            : '';
    }

    private function renderPagination(
        int $currentPage,
        int $totalPages
    ): void {
        if ($totalPages <= 1) {
            return;
        }

        $baseArgs = $_GET;
        unset($baseArgs['paged']);

        $baseArgs = array_map(
            static function ($value) {
                return is_scalar($value)
                    ? sanitize_text_field(wp_unslash((string) $value))
                    : '';
            },
            $baseArgs
        );

        echo '<div class="hb-inventory-pagination">';

        echo wp_kses_post(
            paginate_links(
                array(
                    'base' => add_query_arg(
                        array_merge(
                            $baseArgs,
                            array(
                                'paged' => '%#%',
                            )
                        ),
                        admin_url('admin.php')
                    ),
                    'format' => '',
                    'current' => $currentPage,
                    'total' => $totalPages,
                    'prev_text' => '→ قبلی',
                    'next_text' => 'بعدی ←',
                    'type' => 'list',
                )
            )
        );

        echo '</div>';
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

        $gregorianStart = JalaliDate::parseInputToGregorianYmd(
            $startValue
        );

        $gregorianEnd = JalaliDate::parseInputToGregorianYmd(
            $endValue
        );

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
                    'refunded',
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
