<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\BusinessKpiService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class BusinessKpisPage
{
    private BusinessKpiService $service;

    public function __construct(
        BusinessKpiService $service
    ) {
        $this->service = $service;
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

        $report = $this->service->getReport(
            $start,
            $end
        );

        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];

        wp_localize_script(
            'hashieban-business-kpis',
            'hashiebanBusinessKpis',
            $this->chartPayload(
                $report,
                $currency,
                $precision
            )
        );
        ?>
        <div class="wrap hb-kpi-page">
            <section class="hb-kpi-hero">
                <div>
                    <span class="hb-kpi-hero__eyebrow">حاشیه‌بان · نبض کسب‌وکار</span>
                    <h1>نبض کسب‌وکار</h1>
                    <p>
                        مهم‌ترین شاخص‌های مدیریتی فروشگاه را در یک صفحه ببین؛
                        رشد، سودآوری، کیفیت مشتری، هزینه و ریسک داده کنار هم قرار گرفته‌اند.
                    </p>

                    <div class="hb-kpi-hero__meta">
                        <span>
                            بازه:
                            <strong><?php echo esc_html(
                                JalaliDate::format($start)
                                . ' تا '
                                . JalaliDate::format($end)
                            ); ?></strong>
                        </span>
                        <span>
                            موتور گزارش:
                            <strong>
                                <?php echo esc_html(
                                    (string) $report['performance_mode'] === 'indexed'
                                        ? 'شاخص سریع'
                                        : 'استاندارد'
                                ); ?>
                            </strong>
                        </span>
                    </div>
                </div>

                <div class="hb-kpi-score hb-kpi-score--<?php echo esc_attr(
                    $this->scoreClass(
                        (int) $report['performance_score']
                    )
                ); ?>">
                    <small>امتیاز عملکرد حاشیه‌بان</small>
                    <strong>
                        <?php echo esc_html(
                            number_format_i18n(
                                (int) $report['performance_score']
                            )
                        ); ?>
                        <span>/100</span>
                    </strong>
                    <b><?php echo esc_html(
                        (string) $report['performance_status']
                    ); ?></b>
                    <em>شاخص راهنمای مدیریتی است، نه استاندارد حسابداری.</em>
                </div>
            </section>

            <?php
            $this->renderRangeFilters(
                $range,
                $start,
                $end
            );
            ?>

            <section class="hb-kpi-primary-grid">
                <?php
                $this->renderMoneyKpi(
                    'فروش',
                    (int) $report['revenue_minor'],
                    $currency,
                    $precision,
                    $report['revenue_growth_percentage'],
                    'رشد نسبت به دوره قبل'
                );

                $this->renderMoneyKpi(
                    'سود خالص',
                    (int) $report['net_profit_minor'],
                    $currency,
                    $precision,
                    $report['profit_growth_percentage'],
                    'پس از همه هزینه‌های ثبت‌شده'
                );

                $this->renderPercentKpi(
                    'درصد سود',
                    $report['margin_percentage'],
                    null,
                    'سهم سود خالص از فروش'
                );

                $this->renderMoneyKpi(
                    'میانگین سفارش',
                    (int) $report['average_order_value_minor'],
                    $currency,
                    $precision,
                    $report['aov_growth_percentage'],
                    'Average Order Value'
                );
                ?>
            </section>

            <section class="hb-kpi-secondary-grid">
                <?php
                $this->renderSimpleKpi(
                    'سفارش',
                    number_format_i18n(
                        (int) $report['order_count']
                    ),
                    $report['order_growth_percentage'],
                    'تعداد سفارش‌های تحلیلی'
                );

                $this->renderSimpleKpi(
                    'مشتری',
                    number_format_i18n(
                        (int) $report['customer_count']
                    ),
                    null,
                    'ثبت‌نام‌شده و مهمان'
                );

                $this->renderPercentKpi(
                    'نرخ مشتری تکراری',
                    $report['repeat_customer_rate'],
                    null,
                    'مشتری با حداقل ۲ سفارش'
                );

                $this->renderPercentKpi(
                    'نرخ سفارش دارای مرجوعی',
                    $report['refund_order_rate'],
                    null,
                    'سفارش‌هایی که مرجوعی یا بازگشت وجه داشته‌اند'
                );

                $this->renderMoneyKpi(
                    'سود به‌ازای سفارش',
                    (int) $report['profit_per_order_minor'],
                    $currency,
                    $precision,
                    null,
                    'سود خالص فروشگاه ÷ تعداد سفارش'
                );

                $this->renderMoneyKpi(
                    'سود به‌ازای مشتری',
                    (int) $report['profit_per_customer_minor'],
                    $currency,
                    $precision,
                    null,
                    'سود خالص فروشگاه ÷ تعداد مشتری'
                );

                $this->renderPercentKpi(
                    'نسبت کل هزینه به فروش',
                    $report['cost_ratio_percentage'],
                    null,
                    'هزینه خرید کالا + هزینه سفارش + هزینه فروشگاه'
                );

                $this->renderPercentKpi(
                    'سهم مشتری اول از فروش',
                    $report['top_customer_sales_share_percentage'],
                    null,
                    'برای تشخیص ریسک تمرکز مشتری'
                );
                ?>
            </section>

            <section class="hb-kpi-chart-grid">
                <article class="hb-kpi-card hb-kpi-card--chart">
                    <header>
                        <div>
                            <h2>نبض عملکرد</h2>
                            <p>۶ مؤلفه مدیریتی در مقیاس ۰ تا ۱۰۰</p>
                        </div>
                        <span class="hb-kpi-chip">نمای سریع</span>
                    </header>
                    <div class="hb-kpi-chart-wrap">
                        <canvas id="hashieban-business-pulse-chart"></canvas>
                    </div>
                </article>

                <article class="hb-kpi-card hb-kpi-card--chart">
                    <header>
                        <div>
                            <h2>رشد نسبت به دوره قبل</h2>
                            <p>تغییر فروش، سود، سفارش و میانگین مبلغ سفارش</p>
                        </div>
                    </header>
                    <div class="hb-kpi-chart-wrap">
                        <canvas id="hashieban-business-growth-chart"></canvas>
                    </div>
                </article>
            </section>

            <section class="hb-kpi-chart-grid">
                <article class="hb-kpi-card hb-kpi-card--chart">
                    <header>
                        <div>
                            <h2>ساختار هزینه</h2>
                            <p>پول فروشگاه در این بازه بیشتر کجا مصرف شده است؟</p>
                        </div>
                    </header>
                    <div class="hb-kpi-chart-wrap">
                        <canvas id="hashieban-business-cost-chart"></canvas>
                    </div>
                </article>

                <article class="hb-kpi-card hb-kpi-card--ratios">
                    <header>
                        <div>
                            <h2>نسبت‌های مدیریتی</h2>
                            <p>چند نسبت سریع برای بررسی کیفیت عملکرد</p>
                        </div>
                    </header>

                    <?php
                    $this->renderRatioRow(
                        'هزینه خرید / فروش',
                        $report['cogs_ratio_percentage'],
                        100
                    );

                    $this->renderRatioRow(
                        'هزینه عملیاتی / فروش',
                        $report['operating_cost_ratio_percentage'],
                        100
                    );

                    $this->renderRatioRow(
                        'داده مالی ناقص',
                        $report['incomplete_order_rate'],
                        100
                    );

                    $this->renderRatioRow(
                        'مشتری تکراری',
                        $report['repeat_customer_rate'],
                        100
                    );
                    ?>
                </article>
            </section>

            <section class="hb-kpi-card hb-kpi-insights">
                <header>
                    <div>
                        <h2>حاشیه‌بان چه چیزی می‌بیند؟</h2>
                        <p>هشدار و فرصت‌های مهم بر اساس شاخص‌های همین بازه</p>
                    </div>
                    <a href="<?php echo esc_url(
                        admin_url('admin.php?page=hashieban-alerts')
                    ); ?>">همه هشدارها</a>
                </header>

                <div class="hb-kpi-insight-grid">
                    <?php
                    foreach (
                        (array) $report['insights']
                        as $insight
                    ) :
                        ?>
                        <a
                            class="hb-kpi-insight hb-kpi-insight--<?php echo esc_attr(
                                (string) ($insight['type'] ?? 'info')
                            ); ?>"
                            href="<?php echo esc_url(
                                (string) ($insight['url'] ?? '#')
                            ); ?>"
                        >
                            <strong><?php echo esc_html(
                                (string) ($insight['title'] ?? '')
                            ); ?></strong>
                            <span><?php echo esc_html(
                                (string) ($insight['description'] ?? '')
                            ); ?></span>
                            <b>
                                <?php echo esc_html(
                                    (string) ($insight['action'] ?? 'بررسی')
                                ); ?>
                                ←
                            </b>
                        </a>
                        <?php
                    endforeach;
                    ?>
                </div>
            </section>
        </div>
        <?php
    }

    private function renderMoneyKpi(
        string $label,
        int $amountMinor,
        string $currency,
        int $precision,
        $delta,
        string $help
    ): void {
        ?>
        <article class="hb-kpi-box">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html(
                Currency::formatMinor(
                    $amountMinor,
                    $currency,
                    $precision
                )
            ); ?></strong>
            <?php $this->renderDelta($delta); ?>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function renderSimpleKpi(
        string $label,
        string $value,
        $delta,
        string $help
    ): void {
        ?>
        <article class="hb-kpi-box hb-kpi-box--small">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <?php $this->renderDelta($delta); ?>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function renderPercentKpi(
        string $label,
        $value,
        $delta,
        string $help
    ): void {
        $formatted = $value === null
            ? '—'
            : number_format_i18n(
                (float) $value,
                1
            ) . '٪';

        $this->renderSimpleKpi(
            $label,
            $formatted,
            $delta,
            $help
        );
    }

    private function renderDelta($value): void
    {
        if ($value === null) {
            ?>
            <span class="hb-kpi-delta hb-kpi-delta--neutral">
                دوره قبل مبنای مقایسه ندارد
            </span>
            <?php
            return;
        }

        $number = (float) $value;
        $class = $number > 0
            ? 'positive'
            : ($number < 0 ? 'negative' : 'neutral');
        $sign = $number > 0
            ? '↑'
            : ($number < 0 ? '↓' : '•');
        ?>
        <span class="hb-kpi-delta hb-kpi-delta--<?php echo esc_attr($class); ?>">
            <?php echo esc_html(
                $sign
                . ' '
                . number_format_i18n(
                    abs($number),
                    1
                )
                . '٪'
            ); ?>
        </span>
        <?php
    }

    private function renderRatioRow(
        string $label,
        $value,
        int $max
    ): void {
        $numeric = $value === null
            ? 0.0
            : (float) $value;

        $width = max(
            0.0,
            min(
                (float) $max,
                $numeric
            )
        );
        ?>
        <div class="hb-kpi-ratio">
            <div>
                <span><?php echo esc_html($label); ?></span>
                <strong>
                    <?php echo $value === null
                        ? '—'
                        : esc_html(
                            number_format_i18n(
                                $numeric,
                                1
                            )
                            . '٪'
                        ); ?>
                </strong>
            </div>
            <div class="hb-kpi-ratio__track">
                <i style="width: <?php echo esc_attr(
                    (string) $width
                ); ?>%"></i>
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
            'all' => 'همه',
        );
        ?>
        <section class="hb-kpi-range">
            <nav>
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key
                            ? 'is-active'
                            : ''; ?>"
                        href="<?php echo esc_url(
                            add_query_arg(
                                array(
                                    'page' => 'hashieban-kpis',
                                    'range' => $key,
                                ),
                                admin_url('admin.php')
                            )
                        ); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="get">
                <input
                    type="hidden"
                    name="page"
                    value="hashieban-kpis"
                >
                <input
                    type="hidden"
                    name="range"
                    value="custom"
                >
                <label>
                    از
                    <input
                        type="text"
                        name="start_date"
                        value="<?php echo esc_attr(
                            JalaliDate::numeric($start)
                        ); ?>"
                        autocomplete="off"
                        data-jdp
                    >
                </label>
                <label>
                    تا
                    <input
                        type="text"
                        name="end_date"
                        value="<?php echo esc_attr(
                            JalaliDate::numeric($end)
                        ); ?>"
                        autocomplete="off"
                        data-jdp
                    >
                </label>
                <button
                    type="submit"
                    class="button button-primary"
                >اعمال</button>
            </form>
        </section>
        <?php
    }

    private function chartPayload(
        array $report,
        string $currency,
        int $precision
    ): array {
        $scores = (array) $report['component_scores'];

        return array(
            'currencyLabel' => Currency::label($currency),
            'pulse' => array(
                'labels' => array(
                    'درصد سود',
                    'رشد فروش',
                    'رشد سود',
                    'تکرار خرید',
                    'مرجوعی',
                    'کیفیت داده',
                ),
                'values' => array(
                    (int) ($scores['margin'] ?? 0),
                    (int) ($scores['revenue_growth'] ?? 0),
                    (int) ($scores['profit_growth'] ?? 0),
                    (int) ($scores['repeat'] ?? 0),
                    (int) ($scores['refund'] ?? 0),
                    (int) ($scores['data'] ?? 0),
                ),
            ),
            'growth' => array(
                'labels' => array(
                    'فروش',
                    'سود',
                    'سفارش',
                    'میانگین مبلغ سفارش',
                ),
                'values' => array(
                    $report['revenue_growth_percentage'],
                    $report['profit_growth_percentage'],
                    $report['order_growth_percentage'],
                    $report['aov_growth_percentage'],
                ),
            ),
            'costs' => array(
                'labels' => array(
                    'هزینه خرید',
                    'هزینه سفارش',
                    'هزینه ثابت سفارش',
                    'هزینه فروشگاه',
                ),
                'values' => array(
                    Currency::minorToDisplayNumber(
                        (int) $report['cogs_minor'],
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) $report['direct_costs_minor'],
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) $report['global_order_costs_minor'],
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) $report['store_expenses_minor'],
                        $currency,
                        $precision
                    ),
                ),
            ),
        );
    }

    private function scoreClass(int $score): string
    {
        if ($score >= 80) {
            return 'excellent';
        }

        if ($score >= 65) {
            return 'good';
        }

        if ($score >= 50) {
            return 'attention';
        }

        return 'risk';
    }

    private function resolveDateRange(): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable(
            'now',
            $timezone
        );
        $end = $now->setTime(
            23,
            59,
            59
        );

        $range = isset($_GET['range'])
            ? sanitize_key(
                wp_unslash($_GET['range'])
            )
            : '30d';

        switch ($range) {
            case '7d':
                $start = $now
                    ->modify('-6 days')
                    ->setTime(0, 0, 0);
                break;

            case '90d':
                $start = $now
                    ->modify('-89 days')
                    ->setTime(0, 0, 0);
                break;

            case '6m':
                $start = $now
                    ->modify('-6 months')
                    ->setTime(0, 0, 0);
                break;

            case 'year':
                $start = $now
                    ->modify('-1 year')
                    ->setTime(0, 0, 0);
                break;

            case 'all':
                $start = $this->resolveAllTimeStart(
                    $now
                );
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
                $start = $now
                    ->modify('-29 days')
                    ->setTime(0, 0, 0);
                break;

            case '30d':
            default:
                $range = '30d';
                $start = $now
                    ->modify('-29 days')
                    ->setTime(0, 0, 0);
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
            ? sanitize_text_field(
                wp_unslash($_GET['start_date'])
            )
            : '';

        $endValue = isset($_GET['end_date'])
            ? sanitize_text_field(
                wp_unslash($_GET['end_date'])
            )
            : '';

        if (
            $startValue === ''
            || $endValue === ''
        ) {
            return null;
        }

        $gregorianStart =
            JalaliDate::parseInputToGregorianYmd(
                $startValue
            );

        $gregorianEnd =
            JalaliDate::parseInputToGregorianYmd(
                $endValue
            );

        if (
            $gregorianStart === null
            || $gregorianEnd === null
        ) {
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

        if (
            ! $start
            || ! $end
            || $start > $end
        ) {
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
            $date = $orders[0]
                ->get_date_created();

            if ($date) {
                return (
                    new DateTimeImmutable(
                        '@'
                        . $date
                            ->getTimestamp()
                    )
                )
                    ->setTimezone(
                        wp_timezone()
                    )
                    ->setTime(
                        0,
                        0,
                        0
                    );
            }
        }

        return $fallback
            ->modify('-3 years')
            ->setTime(0, 0, 0);
    }
}
