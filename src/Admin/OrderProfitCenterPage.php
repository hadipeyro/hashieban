<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\OrderProfitCenterService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class OrderProfitCenterPage
{
    private OrderProfitCenterService $analytics;

    public function __construct(
        OrderProfitCenterService $analytics
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

        $orderId = isset($_GET['order_id'])
            ? absint(wp_unslash($_GET['order_id']))
            : 0;

        if ($orderId > 0) {
            $this->renderOrderDetail($orderId);
            return;
        }

        list($start, $end, $range) = $this->resolveDateRange();
        $filters = $this->resolveFilters();

        $report = $this->analytics->getReport(
            $start,
            $end,
            $filters
        );

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $orders = (array) $report['orders'];

        $perPage = 25;
        $currentPage = max(
            1,
            isset($_GET['paged'])
                ? absint(wp_unslash($_GET['paged']))
                : 1
        );

        $totalRows = count($orders);
        $totalPages = max(
            1,
            (int) ceil($totalRows / $perPage)
        );

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $pageRows = array_slice(
            $orders,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        $payload = $this->buildListChartPayload(
            $report,
            $currency,
            $precision
        );

        ?>
        <div class="wrap hb-orders-page">
            <section class="hb-orders-hero">
                <div>
                    <div class="hb-orders-hero__eyebrow">حاشیه‌بان BI · Order Intelligence</div>
                    <h1>مرکز سودآوری سفارش‌ها</h1>
                    <p>
                        هر سفارش را با درآمد، بهای کالا، هزینه‌های مستقیم، هزینه ثابت، سود و Margin ببین؛
                        بدون پیدا کردن یا تایپ شناسه داخلی سفارش.
                    </p>

                    <div class="hb-orders-hero__meta">
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

                <div class="hb-orders-hero__profit <?php echo (int) $report['total_profit_minor'] < 0 ? 'is-negative' : ''; ?>">
                    <span>سود سفارش‌های فیلترشده</span>
                    <strong><?php echo esc_html(Currency::formatMinor((int) $report['total_profit_minor'], $currency, $precision)); ?></strong>
                    <small>
                        هزینه‌های کلی فروشگاه در سود تک‌سفارش تخصیص داده نشده‌اند؛ هزینه ثابت هر سفارش اعمال شده است.
                    </small>
                </div>
            </section>

            <?php $this->renderRangeFilters($range, $start, $end); ?>
            <?php $this->renderSearchFilters($range); ?>

            <section class="hb-orders-kpis">
                <?php
                $this->renderKpi(
                    'سفارش‌ها',
                    number_format_i18n((int) $report['order_count']),
                    'تعداد سفارش مطابق فیلترهای فعلی'
                );

                $this->renderKpi(
                    'فروش قابل انتساب',
                    Currency::formatMinor((int) $report['total_revenue_minor'], $currency, $precision),
                    'فروش محصولات + ارسال + Fee مثبت - Refund'
                );

                $this->renderKpi(
                    'سود سفارش‌ها',
                    Currency::formatMinor((int) $report['total_profit_minor'], $currency, $precision),
                    'پس از COGS، هزینه مستقیم و هزینه ثابت هر سفارش',
                    (int) $report['total_profit_minor'] < 0
                );

                $this->renderKpi(
                    'Margin وزنی',
                    $this->formatPercentage($report['weighted_margin_percentage']),
                    'سود کل سفارش‌ها نسبت به فروش قابل انتساب'
                );

                $this->renderKpi(
                    'سفارش زیان‌ده',
                    number_format_i18n((int) $report['loss_count']),
                    'سفارش‌هایی که سودشان کمتر از صفر است',
                    (int) $report['loss_count'] > 0
                );

                $this->renderKpi(
                    'داده مالی ناقص',
                    number_format_i18n((int) $report['incomplete_count']),
                    'عمدتاً COGS ناقص یا غیرقابل اتکا',
                    (int) $report['incomplete_count'] > 0
                );
                ?>
            </section>

            <?php $this->renderInsightCards($report, $currency, $precision); ?>

            <section class="hb-orders-chart-grid">
                <article class="hb-orders-card hb-orders-card--chart">
                    <div class="hb-orders-card__header">
                        <div>
                            <h2>سلامت سود سفارش‌ها</h2>
                            <p>تعداد سفارش‌های سودده، زیان‌ده و سربه‌سر</p>
                        </div>
                    </div>
                    <div class="hb-orders-chart-wrap hb-orders-chart-wrap--compact">
                        <canvas id="hashieban-orders-profitability-chart"></canvas>
                    </div>
                </article>

                <article class="hb-orders-card hb-orders-card--chart">
                    <div class="hb-orders-card__header">
                        <div>
                            <h2>توزیع Margin</h2>
                            <p>چند سفارش در هر محدوده حاشیه سود قرار گرفته‌اند؟</p>
                        </div>
                    </div>
                    <div class="hb-orders-chart-wrap hb-orders-chart-wrap--compact">
                        <canvas id="hashieban-orders-margin-chart"></canvas>
                    </div>
                </article>
            </section>

            <section class="hb-orders-card hb-orders-card--chart hb-orders-card--wide">
                <div class="hb-orders-card__header">
                    <div>
                        <h2>نقشه ارزش سفارش در برابر Margin</h2>
                        <p>هر نقطه یک سفارش است؛ سفارش‌های بزرگ با Margin ضعیف سریع دیده می‌شوند.</p>
                    </div>
                    <?php if (! empty($report['chart_sampled'])) : ?>
                        <span class="hb-orders-chip">نمونه نمایشی نمودار</span>
                    <?php endif; ?>
                </div>
                <div class="hb-orders-chart-wrap hb-orders-chart-wrap--scatter">
                    <canvas id="hashieban-orders-scatter-chart"></canvas>
                </div>
            </section>

            <section class="hb-orders-card hb-orders-table-card">
                <div class="hb-orders-card__header hb-orders-card__header--table">
                    <div>
                        <h2>دفتر سودآوری سفارش‌ها</h2>
                        <p>برای تحلیل کامل روی شماره سفارش کلیک کن.</p>
                    </div>
                    <span class="hb-orders-chip"><?php echo esc_html(number_format_i18n($totalRows)); ?> سفارش</span>
                </div>

                <div class="hb-orders-table-wrap">
                    <table class="widefat striped hb-orders-table">
                        <thead>
                            <tr>
                                <th>سفارش</th>
                                <th>تاریخ</th>
                                <th>مشتری</th>
                                <th>اقلام</th>
                                <th>فروش</th>
                                <th>COGS</th>
                                <th>هزینه سفارش</th>
                                <th>هزینه ثابت</th>
                                <th>سود</th>
                                <th>Margin</th>
                                <th>وضعیت</th>
                                <th>داده</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pageRows === array()) : ?>
                                <tr>
                                    <td colspan="13" class="hb-orders-empty">
                                        سفارشی مطابق این فیلترها پیدا نشد.
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($pageRows as $row) : ?>
                                    <?php
                                    $analysisUrl = add_query_arg(
                                        array(
                                            'page' => 'hashieban-orders',
                                            'order_id' => (int) $row['order_id'],
                                        ),
                                        admin_url('admin.php')
                                    );
                                    ?>
                                    <tr>
                                        <td>
                                            <a class="hb-orders-order-link" href="<?php echo esc_url($analysisUrl); ?>">
                                                #<?php echo esc_html((string) $row['order_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html(JalaliDate::format($row['created_at'])); ?></td>
                                        <td>
                                            <?php if ((int) $row['customer_id'] > 0) : ?>
                                                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . (int) $row['customer_id'])); ?>">
                                                    <?php echo esc_html((string) $row['customer_name']); ?>
                                                </a>
                                            <?php else : ?>
                                                <strong><?php echo esc_html((string) $row['customer_name']); ?></strong>
                                            <?php endif; ?>
                                            <?php if ((string) $row['customer_email'] !== '') : ?>
                                                <small><?php echo esc_html((string) $row['customer_email']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(number_format_i18n((int) $row['item_count'])); ?></td>
                                        <td><?php echo esc_html(Currency::formatMinor((int) $row['revenue_minor'], $currency, $precision)); ?></td>
                                        <td><?php echo esc_html(Currency::formatMinor((int) $row['cogs_minor'], $currency, $precision)); ?></td>
                                        <td><?php echo esc_html(Currency::formatMinor((int) $row['direct_costs_minor'], $currency, $precision)); ?></td>
                                        <td><?php echo esc_html(Currency::formatMinor((int) $row['global_order_costs_minor'], $currency, $precision)); ?></td>
                                        <td class="<?php echo (int) $row['profit_minor'] < 0 ? 'is-negative' : 'is-positive'; ?>">
                                            <strong><?php echo esc_html(Currency::formatMinor((int) $row['profit_minor'], $currency, $precision)); ?></strong>
                                        </td>
                                        <td><?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?></td>
                                        <td><span class="hb-orders-status"><?php echo esc_html((string) $row['status_label']); ?></span></td>
                                        <td>
                                            <?php if (! empty($row['has_missing_data'])) : ?>
                                                <span class="hb-orders-health hb-orders-health--warning">ناقص</span>
                                            <?php else : ?>
                                                <span class="hb-orders-health hb-orders-health--ok">کامل</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="hb-orders-actions">
                                            <a href="<?php echo esc_url($analysisUrl); ?>">تحلیل</a>
                                            <a href="<?php echo esc_url((string) $row['edit_url']); ?>">ووکامرس</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php $this->renderPagination($currentPage, $totalPages, $totalRows, $range); ?>
            </section>

            <script id="hashieban-order-center-data" type="application/json"><?php echo wp_json_encode($payload); ?></script>
        </div>
        <?php
    }

    private function renderOrderDetail(
        int $orderId
    ): void {
        $detail = $this->analytics->getOrderDetail($orderId);

        if ($detail === null) {
            ?>
            <div class="wrap hb-orders-page">
                <div class="notice notice-error inline"><p>سفارش موردنظر پیدا نشد یا قابل تحلیل نیست.</p></div>
                <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-orders'), admin_url('admin.php'))); ?>">بازگشت به مرکز سفارش‌ها</a>
            </div>
            <?php
            return;
        }

        $currency = (string) $detail['currency'];
        $precision = (int) $detail['precision'];
        $payload = $this->buildDetailChartPayload($detail, $currency, $precision);
        ?>
        <div class="wrap hb-orders-page hb-orders-detail-page">
            <div class="hb-orders-detail-toolbar">
                <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-orders'), admin_url('admin.php'))); ?>">← بازگشت به سفارش‌ها</a>
                <a class="button button-primary" href="<?php echo esc_url((string) $detail['order_edit_url']); ?>">باز کردن سفارش در WooCommerce</a>
            </div>

            <section class="hb-orders-hero hb-orders-hero--detail">
                <div>
                    <div class="hb-orders-hero__eyebrow">تحلیل مالی سفارش</div>
                    <h1>سفارش #<?php echo esc_html((string) $detail['order_number']); ?></h1>
                    <p>
                        <?php echo esc_html((string) $detail['customer_name']); ?>
                        · <?php echo esc_html((string) $detail['status_label']); ?>
                        <?php if ($detail['created_at'] instanceof DateTimeImmutable) : ?>
                            · <?php echo esc_html(JalaliDate::format($detail['created_at'])); ?>
                        <?php endif; ?>
                    </p>
                    <div class="hb-orders-hero__meta">
                        <?php if ((string) $detail['customer_email'] !== '') : ?>
                            <span><?php echo esc_html((string) $detail['customer_email']); ?></span>
                        <?php endif; ?>
                        <?php if ((string) $detail['customer_phone'] !== '') : ?>
                            <span><?php echo esc_html((string) $detail['customer_phone']); ?></span>
                        <?php endif; ?>
                        <?php if ((string) $detail['customer_edit_url'] !== '') : ?>
                            <a href="<?php echo esc_url((string) $detail['customer_edit_url']); ?>">پروفایل مشتری</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hb-orders-hero__profit <?php echo (int) $detail['profit_minor'] < 0 ? 'is-negative' : ''; ?>">
                    <span>سود این سفارش</span>
                    <strong><?php echo esc_html(Currency::formatMinor((int) $detail['profit_minor'], $currency, $precision)); ?></strong>
                    <small>Margin: <?php echo esc_html($this->formatPercentage($detail['margin_percentage'])); ?></small>
                </div>
            </section>

            <?php if (! empty($detail['has_missing_data'])) : ?>
                <div class="hb-orders-notice hb-orders-notice--warning">
                    <strong>اطلاعات مالی این سفارش کامل نیست.</strong>
                    <ul>
                        <?php foreach ((array) $detail['missing_data'] as $message) : ?>
                            <li><?php echo esc_html((string) $message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ((int) $detail['refund_minor'] > 0) : ?>
                <div class="hb-orders-notice hb-orders-notice--warning">
                    این سفارش Refund دارد. موتور دقیق اثر Refund روی COGS در مرحله Refund & Returns تکمیل می‌شود.
                </div>
            <?php endif; ?>

            <section class="hb-orders-kpis hb-orders-kpis--detail">
                <?php
                $this->renderKpi('فروش قابل انتساب', Currency::formatMinor((int) $detail['revenue_minor'], $currency, $precision), 'محصول + ارسال + Fee مثبت - Refund');
                $this->renderKpi('COGS', Currency::formatMinor((int) $detail['cogs_minor'], $currency, $precision), 'بهای خرید اقلام سفارش');
                $this->renderKpi('هزینه مستقیم سفارش', Currency::formatMinor((int) $detail['direct_costs_minor'], $currency, $precision), 'هزینه‌های اختصاصی ثبت‌شده برای سفارش');
                $this->renderKpi('هزینه ثابت سفارش', Currency::formatMinor((int) $detail['global_order_costs_minor'], $currency, $precision), 'قواعد عمومی هزینه برای هر سفارش');
                $this->renderKpi('سود سفارش', Currency::formatMinor((int) $detail['profit_minor'], $currency, $precision), 'پس از هزینه‌های قابل انتساب', (int) $detail['profit_minor'] < 0);
                $this->renderKpi('Margin', $this->formatPercentage($detail['margin_percentage']), 'حاشیه سود این سفارش');
                ?>
            </section>

            <section class="hb-orders-detail-grid">
                <article class="hb-orders-card hb-orders-card--chart">
                    <div class="hb-orders-card__header">
                        <div>
                            <h2>کالبدشکافی مالی سفارش</h2>
                            <p>فروش، COGS، هزینه‌ها و سود کنار هم</p>
                        </div>
                    </div>
                    <div class="hb-orders-chart-wrap hb-orders-chart-wrap--detail">
                        <canvas id="hashieban-order-detail-breakdown-chart"></canvas>
                    </div>
                </article>

                <article class="hb-orders-card">
                    <div class="hb-orders-card__header">
                        <div>
                            <h2>اجزای درآمد</h2>
                            <p>منشأ مبلغ قابل انتساب به سفارش</p>
                        </div>
                    </div>
                    <div class="hb-orders-metric-list">
                        <div><span>فروش محصولات</span><strong><?php echo esc_html(Currency::formatMinor((int) $detail['product_revenue_minor'], $currency, $precision)); ?></strong></div>
                        <div><span>ارسال دریافت‌شده</span><strong><?php echo esc_html(Currency::formatMinor((int) $detail['shipping_revenue_minor'], $currency, $precision)); ?></strong></div>
                        <div><span>Fee مثبت</span><strong><?php echo esc_html(Currency::formatMinor((int) $detail['fee_revenue_minor'], $currency, $precision)); ?></strong></div>
                        <div><span>Refund</span><strong><?php echo esc_html(Currency::formatMinor((int) $detail['refund_minor'], $currency, $precision)); ?></strong></div>
                    </div>
                </article>
            </section>

            <section class="hb-orders-card hb-orders-table-card">
                <div class="hb-orders-card__header">
                    <div>
                        <h2>محصولات سفارش</h2>
                        <p>نام محصول‌ها به صفحه خود محصول لینک شده‌اند.</p>
                    </div>
                </div>
                <div class="hb-orders-table-wrap">
                    <table class="widefat striped hb-orders-table">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>SKU</th>
                                <th>تعداد</th>
                                <th>فروش</th>
                                <th>COGS</th>
                                <th>سود کالا</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) $detail['items'] as $item) : ?>
                                <tr>
                                    <td>
                                        <?php if ((string) $item['edit_url'] !== '') : ?>
                                            <a class="hb-orders-product-link" href="<?php echo esc_url((string) $item['edit_url']); ?>"><?php echo esc_html((string) $item['name']); ?></a>
                                        <?php else : ?>
                                            <strong><?php echo esc_html((string) $item['name']); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html((string) $item['sku'] !== '' ? (string) $item['sku'] : '—'); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $item['quantity'])); ?></td>
                                    <td><?php echo esc_html(Currency::formatMinor((int) $item['revenue_minor'], $currency, $precision)); ?></td>
                                    <td><?php echo esc_html(Currency::formatMinor((int) $item['cogs_minor'], $currency, $precision)); ?></td>
                                    <td class="<?php echo (int) $item['profit_minor'] < 0 ? 'is-negative' : 'is-positive'; ?>">
                                        <strong><?php echo esc_html(Currency::formatMinor((int) $item['profit_minor'], $currency, $precision)); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="hb-orders-card hb-orders-table-card">
                <div class="hb-orders-card__header">
                    <div>
                        <h2>هزینه‌های مستقیم سفارش</h2>
                        <p>دسته و رنگ هزینه‌های ثبت‌شده روی همین سفارش</p>
                    </div>
                </div>

                <?php if ((array) $detail['direct_cost_rows'] === array()) : ?>
                    <div class="hb-orders-empty-block">برای این سفارش هزینه مستقیم جداگانه ثبت نشده است.</div>
                <?php else : ?>
                    <div class="hb-orders-direct-costs">
                        <?php foreach ((array) $detail['direct_cost_rows'] as $cost) : ?>
                            <div class="hb-orders-direct-cost">
                                <span class="hb-orders-color-dot" style="background: <?php echo esc_attr((string) $cost['category_color']); ?>"></span>
                                <div>
                                    <strong><?php echo esc_html((string) $cost['title']); ?></strong>
                                    <small><?php echo esc_html((string) $cost['category_name']); ?></small>
                                    <?php if ((string) $cost['note'] !== '') : ?>
                                        <p><?php echo esc_html((string) $cost['note']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <b><?php echo esc_html(Currency::formatMinor((int) $cost['amount_minor'], $currency, $precision)); ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <script id="hashieban-order-center-data" type="application/json"><?php echo wp_json_encode($payload); ?></script>
        </div>
        <?php
    }

    private function renderKpi(
        string $label,
        string $value,
        string $help,
        bool $danger = false
    ): void {
        ?>
        <div class="hb-orders-kpi <?php echo $danger ? 'hb-orders-kpi--danger' : ''; ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </div>
        <?php
    }

    private function renderInsightCards(
        array $report,
        string $currency,
        int $precision
    ): void {
        $cards = array(
            array('title' => 'پرسودترین سفارش', 'row' => $report['best_order'], 'field' => 'profit_minor', 'type' => 'profit'),
            array('title' => 'کم‌سودترین / زیان‌ده', 'row' => $report['worst_order'], 'field' => 'profit_minor', 'type' => 'risk'),
            array('title' => 'بزرگ‌ترین سفارش', 'row' => $report['largest_order'], 'field' => 'revenue_minor', 'type' => 'revenue'),
            array('title' => 'بالاترین Margin', 'row' => $report['highest_margin_order'], 'field' => 'margin_percentage', 'type' => 'margin'),
        );
        ?>
        <section class="hb-orders-insights">
            <?php foreach ($cards as $card) : ?>
                <?php $row = is_array($card['row']) ? $card['row'] : null; ?>
                <article class="hb-orders-insight hb-orders-insight--<?php echo esc_attr((string) $card['type']); ?>">
                    <span><?php echo esc_html((string) $card['title']); ?></span>
                    <?php if ($row === null) : ?>
                        <strong>—</strong><small>داده‌ای وجود ندارد</small>
                    <?php else : ?>
                        <?php
                        $url = add_query_arg(
                            array('page' => 'hashieban-orders', 'order_id' => (int) $row['order_id']),
                            admin_url('admin.php')
                        );
                        ?>
                        <a href="<?php echo esc_url($url); ?>">#<?php echo esc_html((string) $row['order_number']); ?></a>
                        <?php if ($card['field'] === 'margin_percentage') : ?>
                            <strong><?php echo esc_html($this->formatPercentage($row['margin_percentage'])); ?></strong>
                        <?php else : ?>
                            <strong><?php echo esc_html(Currency::formatMinor((int) $row[$card['field']], $currency, $precision)); ?></strong>
                        <?php endif; ?>
                        <small><?php echo esc_html((string) $row['customer_name']); ?></small>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
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
        <div class="hb-orders-range-bar">
            <div class="hb-orders-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-orders', 'range' => $key), admin_url('admin.php'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-orders-custom-range">
                <input type="hidden" name="page" value="hashieban-orders">
                <input type="hidden" name="range" value="custom">
                <label>از <input type="text" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>" autocomplete="off" data-jdp></label>
                <label>تا <input type="text" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>" autocomplete="off" data-jdp></label>
                <button type="submit" class="button button-primary">اعمال</button>
            </form>
        </div>
        <?php
    }

    private function renderSearchFilters(
        string $range
    ): void {
        $filters = $this->resolveFilters();
        ?>
        <form method="get" class="hb-orders-filter-card">
            <input type="hidden" name="page" value="hashieban-orders">
            <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">
            <?php if ($range === 'custom') : ?>
                <input type="hidden" name="start_date" value="<?php echo esc_attr(isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : ''); ?>">
                <input type="hidden" name="end_date" value="<?php echo esc_attr(isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : ''); ?>">
            <?php endif; ?>

            <label class="hb-orders-filter-card__search">
                <span>جستجو</span>
                <input type="search" name="q" value="<?php echo esc_attr($filters['q']); ?>" placeholder="شماره سفارش، نام، ایمیل یا موبایل مشتری">
            </label>

            <label>
                <span>وضعیت</span>
                <select name="status">
                    <option value="all" <?php selected($filters['status'], 'all'); ?>>همه وضعیت‌های مالی</option>
                    <option value="processing" <?php selected($filters['status'], 'processing'); ?>>در حال انجام</option>
                    <option value="completed" <?php selected($filters['status'], 'completed'); ?>>تکمیل‌شده</option>
                </select>
            </label>

            <label>
                <span>سودآوری</span>
                <select name="profitability">
                    <option value="all" <?php selected($filters['profitability'], 'all'); ?>>همه</option>
                    <option value="profit" <?php selected($filters['profitability'], 'profit'); ?>>فقط سودده</option>
                    <option value="loss" <?php selected($filters['profitability'], 'loss'); ?>>فقط زیان‌ده</option>
                    <option value="break_even" <?php selected($filters['profitability'], 'break_even'); ?>>سربه‌سر</option>
                    <option value="incomplete" <?php selected($filters['profitability'], 'incomplete'); ?>>داده ناقص</option>
                </select>
            </label>

            <label>
                <span>حداقل مبلغ (<?php echo esc_html(Currency::label()); ?>)</span>
                <input type="text" inputmode="decimal" name="min_amount" value="<?php echo esc_attr($filters['min_amount']); ?>">
            </label>

            <label>
                <span>حداکثر مبلغ (<?php echo esc_html(Currency::label()); ?>)</span>
                <input type="text" inputmode="decimal" name="max_amount" value="<?php echo esc_attr($filters['max_amount']); ?>">
            </label>

            <label>
                <span>مرتب‌سازی</span>
                <select name="sort">
                    <option value="date_desc" <?php selected($filters['sort'], 'date_desc'); ?>>جدیدترین</option>
                    <option value="date_asc" <?php selected($filters['sort'], 'date_asc'); ?>>قدیمی‌ترین</option>
                    <option value="revenue_desc" <?php selected($filters['sort'], 'revenue_desc'); ?>>بیشترین مبلغ</option>
                    <option value="profit_desc" <?php selected($filters['sort'], 'profit_desc'); ?>>بیشترین سود</option>
                    <option value="profit_asc" <?php selected($filters['sort'], 'profit_asc'); ?>>کمترین سود</option>
                    <option value="margin_desc" <?php selected($filters['sort'], 'margin_desc'); ?>>بیشترین Margin</option>
                    <option value="margin_asc" <?php selected($filters['sort'], 'margin_asc'); ?>>کمترین Margin</option>
                </select>
            </label>

            <div class="hb-orders-filter-card__actions">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-orders', 'range' => $range), admin_url('admin.php'))); ?>">پاک کردن</a>
            </div>
        </form>
        <?php
    }

    private function buildListChartPayload(
        array $report,
        string $currency,
        int $precision
    ): array {
        $marginLabels = array_keys((array) $report['margin_buckets']);
        $marginValues = array_values((array) $report['margin_buckets']);
        $scatter = array();

        foreach ((array) $report['chart_rows'] as $row) {
            $scatter[] = array(
                'x' => Currency::minorToDisplayNumber((int) $row['revenue_minor'], $currency, $precision),
                'y' => $row['margin_percentage'] !== null ? (float) $row['margin_percentage'] : 0.0,
                'order' => '#' . (string) $row['order_number'],
                'customer' => (string) $row['customer_name'],
                'profit' => Currency::minorToDisplayNumber((int) $row['profit_minor'], $currency, $precision),
                'url' => add_query_arg(
                    array('page' => 'hashieban-orders', 'order_id' => (int) $row['order_id']),
                    admin_url('admin.php')
                ),
            );
        }

        return array(
            'mode' => 'list',
            'currencyLabel' => Currency::label($currency),
            'profitability' => array(
                'labels' => array('سودده', 'زیان‌ده', 'سربه‌سر'),
                'values' => array(
                    (int) $report['profitable_count'],
                    (int) $report['loss_count'],
                    (int) $report['break_even_count'],
                ),
            ),
            'margin' => array(
                'labels' => $marginLabels,
                'values' => $marginValues,
            ),
            'scatter' => $scatter,
        );
    }

    private function buildDetailChartPayload(
        array $detail,
        string $currency,
        int $precision
    ): array {
        return array(
            'mode' => 'detail',
            'currencyLabel' => Currency::label($currency),
            'breakdown' => array(
                'labels' => array('فروش', 'COGS', 'هزینه سفارش', 'هزینه ثابت', 'سود'),
                'values' => array(
                    Currency::minorToDisplayNumber((int) $detail['revenue_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $detail['cogs_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $detail['direct_costs_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $detail['global_order_costs_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $detail['profit_minor'], $currency, $precision),
                ),
            ),
        );
    }

    private function renderPagination(
        int $currentPage,
        int $totalPages,
        int $totalRows,
        string $range
    ): void {
        if ($totalPages <= 1) {
            ?>
            <div class="hb-orders-pagination-summary"><?php echo esc_html(number_format_i18n($totalRows)); ?> سفارش</div>
            <?php
            return;
        }

        $baseArgs = array_merge(
            array(
                'page' => 'hashieban-orders',
                'range' => $range,
            ),
            $this->resolveFilters()
        );

        if ($range === 'custom') {
            $baseArgs['start_date'] = isset($_GET['start_date'])
                ? sanitize_text_field(wp_unslash($_GET['start_date']))
                : '';
            $baseArgs['end_date'] = isset($_GET['end_date'])
                ? sanitize_text_field(wp_unslash($_GET['end_date']))
                : '';
        }
        ?>
        <div class="hb-orders-pagination">
            <span><?php echo esc_html(number_format_i18n($totalRows)); ?> سفارش</span>
            <div>
                <?php if ($currentPage > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($baseArgs, array('paged' => $currentPage - 1)), admin_url('admin.php'))); ?>">قبلی</a>
                <?php endif; ?>
                <strong>صفحه <?php echo esc_html(number_format_i18n($currentPage)); ?> از <?php echo esc_html(number_format_i18n($totalPages)); ?></strong>
                <?php if ($currentPage < $totalPages) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($baseArgs, array('paged' => $currentPage + 1)), admin_url('admin.php'))); ?>">بعدی</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function resolveFilters(): array
    {
        return array(
            'q' => isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '',
            'status' => isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'all',
            'profitability' => isset($_GET['profitability']) ? sanitize_key(wp_unslash($_GET['profitability'])) : 'all',
            'min_amount' => isset($_GET['min_amount']) ? sanitize_text_field(wp_unslash($_GET['min_amount'])) : '',
            'max_amount' => isset($_GET['max_amount']) ? sanitize_text_field(wp_unslash($_GET['max_amount'])) : '',
            'sort' => isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'date_desc',
        );
    }

    private function formatPercentage(
        $value
    ): string {
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
