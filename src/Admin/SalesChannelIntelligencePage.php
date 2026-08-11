<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\SalesChannelIntelligenceService;
use Hashieban\Security\Capabilities;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class SalesChannelIntelligencePage
{
    private SalesChannelIntelligenceService $service;

    public function __construct(
        SalesChannelIntelligenceService $service
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

        list($start, $end, $range) = $this->resolveDateRange();
        $report = $this->service->getReport($start, $end);
        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];

        wp_localize_script(
            'hashieban-sales-channels',
            'hashiebanSalesChannels',
            $this->chartPayload(
                $report,
                $currency,
                $precision
            )
        );
        ?>
        <div class="wrap hb-channel-page">
            <section class="hb-channel-hero">
                <div>
                    <span class="hb-channel-hero__eyebrow">حاشیه‌بان · هوش کانال‌های فروش</span>
                    <h1>مشتری‌ها از کجا می‌آیند؟</h1>
                    <p>
                        فروش و سود سفارش‌های ورودی از ترب، ایمالز، گوگل، شبکه‌های اجتماعی،
                        کمپین‌ها و ورود مستقیم را کنار هم مقایسه کن.
                    </p>
                    <div class="hb-channel-hero__meta">
                        <span>
                            بازه:
                            <strong><?php echo esc_html(
                                JalaliDate::format($start)
                                . ' تا '
                                . JalaliDate::format($end)
                            ); ?></strong>
                        </span>
                        <span>
                            پوشش منبع:
                            <strong><?php echo esc_html(
                                number_format_i18n(
                                    (float) $report['attribution_coverage_percentage'],
                                    1
                                ) . '٪'
                            ); ?></strong>
                        </span>
                    </div>
                </div>

                <div class="hb-channel-coverage hb-channel-coverage--<?php echo esc_attr(
                    $this->coverageClass(
                        (float) $report['attribution_coverage_percentage'],
                        (int) $report['order_count']
                    )
                ); ?>">
                    <small>کیفیت داده منبع</small>
                    <strong><?php echo esc_html(
                        number_format_i18n(
                            (float) $report['attribution_coverage_percentage'],
                            1
                        ) . '٪'
                    ); ?></strong>
                    <b><?php echo esc_html(
                        (string) $report['coverage_status']
                    ); ?></b>
                    <em>
                        سفارش‌های بدون داده منبع حدس زده نمی‌شوند.
                    </em>
                </div>
            </section>

            <?php
            $this->renderRangeFilters(
                $range,
                $start,
                $end
            );
            ?>

            <?php if ($this->service->isAttributionDisabled()) : ?>
                <div class="notice notice-warning hb-channel-notice">
                    <p>
                        <strong>ثبت منبع سفارش در ووکامرس غیرفعال است.</strong>
                        برای سفارش‌های جدید از مسیر
                        «ووکامرس ← پیکربندی ← پیشرفته ← امکانات ← نسبت‌دهی سفارش»
                        آن را فعال کن. داده سفارش‌های قدیمی به‌صورت ساختگی تکمیل نمی‌شود.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (empty($report['index_ready'])) : ?>
                <section class="hb-channel-state hb-channel-state--building">
                    <span class="dashicons dashicons-update"></span>
                    <div>
                        <h2>در حال آماده‌سازی هوش کانال‌ها</h2>
                        <p>
                            ساختار شاخص سریع ارتقا پیدا کرده و حاشیه‌بان در پس‌زمینه سفارش‌ها را دوباره فهرست می‌کند.
                            تا پایان کار، گزارش ناقص نمایش داده نمی‌شود.
                        </p>
                    </div>
                    <a class="button" href="<?php echo esc_url(
                        admin_url('admin.php?page=hashieban-bulk-tools')
                    ); ?>">وضعیت ابزارهای گروهی</a>
                </section>
            <?php else : ?>
                <section class="hb-channel-kpis">
                    <?php
                    $this->renderMoneyKpi(
                        'فروش قابل تحلیل',
                        (int) $report['revenue_minor'],
                        $currency,
                        $precision,
                        'جمع فروش سفارش‌های این بازه'
                    );

                    $this->renderMoneyKpi(
                        'سود سفارش‌ها',
                        (int) $report['profit_minor'],
                        $currency,
                        $precision,
                        'قبل از هزینه عمومی فروشگاه و هزینه تبلیغات کانال'
                    );

                    $this->renderSimpleKpi(
                        'سفارش',
                        number_format_i18n(
                            (int) $report['order_count']
                        ),
                        'سفارش‌های پردازش‌شده، تکمیل‌شده و مرجوع‌شده'
                    );

                    $this->renderSimpleKpi(
                        'منبع مشخص',
                        number_format_i18n(
                            (int) $report['attributed_order_count']
                        ),
                        'شامل ورود مستقیم و ثبت دستیِ قابل تشخیص'
                    );

                    $this->renderChannelKpi(
                        'بیشترین فروش',
                        $report['best_sales_channel'],
                        'revenue_minor',
                        $currency,
                        $precision
                    );

                    $this->renderChannelKpi(
                        'بیشترین سود سفارش',
                        $report['best_profit_channel'],
                        'profit_minor',
                        $currency,
                        $precision
                    );
                    ?>
                </section>

                <?php if ((int) $report['order_count'] <= 0) : ?>
                    <section class="hb-channel-state">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <div>
                            <h2>برای این بازه سفارشی پیدا نشد</h2>
                            <p>یک بازه زمانی بزرگ‌تر انتخاب کن تا کانال‌های فروش قابل مقایسه شوند.</p>
                        </div>
                    </section>
                <?php else : ?>
                    <section class="hb-channel-chart-grid">
                        <article class="hb-channel-card hb-channel-card--chart">
                            <header>
                                <div>
                                    <h2>فروش در برابر سود</h2>
                                    <p>ممکن است کانال پرفروش، پرسودترین کانال نباشد.</p>
                                </div>
                            </header>
                            <div class="hb-channel-chart-wrap">
                                <canvas id="hashieban-channel-value-chart"></canvas>
                            </div>
                        </article>

                        <article class="hb-channel-card hb-channel-card--chart">
                            <header>
                                <div>
                                    <h2>سهم سفارش کانال‌ها</h2>
                                    <p>سفارش‌ها از کدام مسیرها وارد فروشگاه شده‌اند؟</p>
                                </div>
                            </header>
                            <div class="hb-channel-chart-wrap hb-channel-chart-wrap--donut">
                                <canvas id="hashieban-channel-order-chart"></canvas>
                            </div>
                        </article>
                    </section>

                    <section class="hb-channel-card hb-channel-card--table">
                        <header>
                            <div>
                                <h2>مقایسه کانال‌های فروش</h2>
                                <p>
                                    «سود سفارش» هزینه خرید کالا و هزینه‌های مستقیم/ثابت سفارش را در نظر می‌گیرد؛
                                    هزینه عمومی فروشگاه یا بودجه تبلیغات بین کانال‌ها سرشکن نشده است.
                                </p>
                            </div>
                        </header>

                        <div class="hb-channel-table-scroll">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>کانال</th>
                                        <th>سفارش</th>
                                        <th>فروش</th>
                                        <th>سهم فروش</th>
                                        <th>سود سفارش</th>
                                        <th>درصد سود</th>
                                        <th>وضعیت داده</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((array) $report['channels'] as $channel) : ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html(
                                                    (string) ($channel['channel_name'] ?? '—')
                                                ); ?></strong>
                                                <small><?php echo esc_html(
                                                    $this->groupLabel(
                                                        (string) ($channel['channel_group'] ?? '')
                                                    )
                                                ); ?></small>
                                            </td>
                                            <td><?php echo esc_html(
                                                number_format_i18n(
                                                    (int) ($channel['order_count'] ?? 0)
                                                )
                                            ); ?></td>
                                            <td><?php echo esc_html(
                                                Currency::formatMinor(
                                                    (int) ($channel['revenue_minor'] ?? 0),
                                                    $currency,
                                                    $precision
                                                )
                                            ); ?></td>
                                            <td><?php echo esc_html(
                                                $this->formatPercent(
                                                    $channel['sales_share_percentage'] ?? null
                                                )
                                            ); ?></td>
                                            <td class="<?php echo (int) ($channel['profit_minor'] ?? 0) < 0
                                                ? 'is-negative'
                                                : 'is-positive'; ?>"><?php echo esc_html(
                                                Currency::formatMinor(
                                                    (int) ($channel['profit_minor'] ?? 0),
                                                    $currency,
                                                    $precision
                                                )
                                            ); ?></td>
                                            <td><?php echo esc_html(
                                                $this->formatPercent(
                                                    $channel['margin_percentage'] ?? null
                                                )
                                            ); ?></td>
                                            <td>
                                                <?php if ((string) ($channel['channel_key'] ?? '') === 'unknown') : ?>
                                                    <span class="hb-channel-badge hb-channel-badge--warning">منبع نامشخص</span>
                                                <?php elseif ((int) ($channel['incomplete_count'] ?? 0) > 0) : ?>
                                                    <span class="hb-channel-badge hb-channel-badge--warning">مالی ناقص</span>
                                                <?php else : ?>
                                                    <span class="hb-channel-badge hb-channel-badge--ok">آماده</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="hb-channel-insights">
                        <?php foreach ((array) $report['insights'] as $insight) : ?>
                            <article class="hb-channel-insight hb-channel-insight--<?php echo esc_attr(
                                (string) ($insight['type'] ?? 'info')
                            ); ?>">
                                <span class="dashicons <?php echo esc_attr(
                                    $this->insightIcon(
                                        (string) ($insight['type'] ?? 'info')
                                    )
                                ); ?>"></span>
                                <div>
                                    <strong><?php echo esc_html(
                                        (string) ($insight['title'] ?? '')
                                    ); ?></strong>
                                    <p><?php echo esc_html(
                                        (string) ($insight['description'] ?? '')
                                    ); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <section class="hb-channel-detail-grid">
                        <article class="hb-channel-card hb-channel-card--table">
                            <header>
                                <div>
                                    <h2>کمپین‌های دارای کد رهگیری</h2>
                                    <p>اگر لینک تبلیغاتی UTM داشته باشد، عملکرد کمپین اینجا جمع می‌شود.</p>
                                </div>
                            </header>
                            <?php $this->renderCampaignTable(
                                (array) $report['campaigns'],
                                $currency,
                                $precision
                            ); ?>
                        </article>

                        <article class="hb-channel-card hb-channel-card--table">
                            <header>
                                <div>
                                    <h2>سایت‌های ارجاع‌دهنده</h2>
                                    <p>دامنه‌هایی که قبل از ورود مشتری به فروشگاه ثبت شده‌اند.</p>
                                </div>
                            </header>
                            <?php $this->renderReferrerTable(
                                (array) $report['referrers'],
                                $currency,
                                $precision
                            ); ?>
                        </article>
                    </section>

                    <section class="hb-channel-footnote">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p>
                            این نسخه منبع سفارش‌های <strong>داخل سایت ووکامرس</strong> را تحلیل می‌کند.
                            اتصال مستقیم سفارش‌های ثبت‌شده داخل دیجی‌کالا یا باسلام، مرحله جداگانه‌ای است و اینجا با ورودی سایت اشتباه گرفته نمی‌شود.
                        </p>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderMoneyKpi(
        string $label,
        int $amountMinor,
        string $currency,
        int $precision,
        string $help
    ): void {
        ?>
        <article class="hb-channel-kpi">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html(
                Currency::formatMinor(
                    $amountMinor,
                    $currency,
                    $precision
                )
            ); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function renderSimpleKpi(
        string $label,
        string $value,
        string $help
    ): void {
        ?>
        <article class="hb-channel-kpi">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($help); ?></small>
        </article>
        <?php
    }

    private function renderChannelKpi(
        string $label,
        $channel,
        string $metric,
        string $currency,
        int $precision
    ): void {
        $channel = is_array($channel)
            ? $channel
            : null;
        ?>
        <article class="hb-channel-kpi hb-channel-kpi--highlight">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo $channel === null
                ? '—'
                : esc_html(
                    (string) ($channel['channel_name'] ?? '—')
                ); ?></strong>
            <small><?php echo $channel === null
                ? 'داده کافی نیست'
                : esc_html(
                    Currency::formatMinor(
                        (int) ($channel[$metric] ?? 0),
                        $currency,
                        $precision
                    )
                ); ?></small>
        </article>
        <?php
    }

    private function renderCampaignTable(
        array $rows,
        string $currency,
        int $precision
    ): void {
        if ($rows === array()) {
            ?>
            <div class="hb-channel-empty">در این بازه کمپین UTM ثبت نشده است.</div>
            <?php
            return;
        }
        ?>
        <div class="hb-channel-table-scroll">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>کمپین</th>
                        <th>منبع / مسیر</th>
                        <th>سفارش</th>
                        <th>فروش</th>
                        <th>سود</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html(
                                (string) ($row['campaign'] ?? '—')
                            ); ?></strong></td>
                            <td>
                                <?php echo esc_html(
                                    (string) ($row['source'] ?? '—')
                                ); ?>
                                <?php if ((string) ($row['medium'] ?? '') !== '') : ?>
                                    <small><?php echo esc_html(
                                        (string) $row['medium']
                                    ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(
                                number_format_i18n(
                                    (int) ($row['order_count'] ?? 0)
                                )
                            ); ?></td>
                            <td><?php echo esc_html(
                                Currency::formatMinor(
                                    (int) ($row['revenue_minor'] ?? 0),
                                    $currency,
                                    $precision
                                )
                            ); ?></td>
                            <td><?php echo esc_html(
                                Currency::formatMinor(
                                    (int) ($row['profit_minor'] ?? 0),
                                    $currency,
                                    $precision
                                )
                            ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderReferrerTable(
        array $rows,
        string $currency,
        int $precision
    ): void {
        if ($rows === array()) {
            ?>
            <div class="hb-channel-empty">در این بازه سایت ارجاع‌دهنده‌ای ثبت نشده است.</div>
            <?php
            return;
        }
        ?>
        <div class="hb-channel-table-scroll">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>دامنه</th>
                        <th>کانال</th>
                        <th>سفارش</th>
                        <th>فروش</th>
                        <th>سود</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><strong dir="ltr"><?php echo esc_html(
                                (string) ($row['referrer_domain'] ?? '—')
                            ); ?></strong></td>
                            <td><?php echo esc_html(
                                (string) ($row['channel_name'] ?? '—')
                            ); ?></td>
                            <td><?php echo esc_html(
                                number_format_i18n(
                                    (int) ($row['order_count'] ?? 0)
                                )
                            ); ?></td>
                            <td><?php echo esc_html(
                                Currency::formatMinor(
                                    (int) ($row['revenue_minor'] ?? 0),
                                    $currency,
                                    $precision
                                )
                            ); ?></td>
                            <td><?php echo esc_html(
                                Currency::formatMinor(
                                    (int) ($row['profit_minor'] ?? 0),
                                    $currency,
                                    $precision
                                )
                            ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
        <section class="hb-channel-range">
            <nav>
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="<?php echo $range === $key
                            ? 'is-active'
                            : ''; ?>"
                        href="<?php echo esc_url(
                            add_query_arg(
                                array(
                                    'page' => 'hashieban-channels',
                                    'range' => $key,
                                ),
                                admin_url('admin.php')
                            )
                        ); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="get">
                <input type="hidden" name="page" value="hashieban-channels">
                <input type="hidden" name="range" value="custom">
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
                <button type="submit" class="button button-primary">اعمال</button>
            </form>
        </section>
        <?php
    }

    private function chartPayload(
        array $report,
        string $currency,
        int $precision
    ): array {
        $labels = array();
        $sales = array();
        $profits = array();
        $orders = array();

        foreach ((array) $report['channels'] as $channel) {
            $labels[] = (string) ($channel['channel_name'] ?? '—');
            $sales[] = Currency::minorToDisplayNumber(
                (int) ($channel['revenue_minor'] ?? 0),
                $currency,
                $precision
            );
            $profits[] = Currency::minorToDisplayNumber(
                (int) ($channel['profit_minor'] ?? 0),
                $currency,
                $precision
            );
            $orders[] = (int) ($channel['order_count'] ?? 0);
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'labels' => $labels,
            'sales' => $sales,
            'profits' => $profits,
            'orders' => $orders,
        );
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
                return (
                    new DateTimeImmutable(
                        '@' . $date->getTimestamp()
                    )
                )->setTimezone(wp_timezone())
                    ->setTime(0, 0, 0);
            }
        }

        return $fallback->modify('-1 year')->setTime(0, 0, 0);
    }

    private function coverageClass(
        float $coverage,
        int $orders
    ): string {
        if ($orders <= 0) {
            return 'empty';
        }

        if ($coverage >= 80.0) {
            return 'good';
        }

        if ($coverage >= 50.0) {
            return 'medium';
        }

        return 'risk';
    }

    private function groupLabel(string $group): string
    {
        $labels = array(
            'comparison' => 'مقایسه‌گر خرید',
            'social' => 'شبکه اجتماعی',
            'search' => 'موتور جست‌وجو',
            'email' => 'ایمیل',
            'direct' => 'مستقیم',
            'manual' => 'ثبت مدیریتی',
            'campaign' => 'کمپین',
            'referral' => 'ارجاع',
            'other' => 'سایر',
            'unknown' => 'نامشخص',
        );

        return $labels[$group] ?? 'سایر';
    }

    private function insightIcon(string $type): string
    {
        $icons = array(
            'success' => 'dashicons-yes-alt',
            'warning' => 'dashicons-warning',
            'danger' => 'dashicons-dismiss',
            'info' => 'dashicons-info-outline',
        );

        return $icons[$type] ?? 'dashicons-info-outline';
    }

    private function formatPercent($value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        return number_format_i18n((float) $value, 1) . '٪';
    }
}
