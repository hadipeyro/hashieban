<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class DashboardPage
{
    private AnalyticsService $analytics;

    public function __construct(
        AnalyticsService $analytics
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

        $this->enqueueAssets();

        [$start, $end, $range] = $this->resolveDateRange();

        $data = $this->analytics->getDashboardData(
            $start,
            $end
        );

        $currency = $data['currency'];
        $precision = (int) $data['precision'];

        $revenue = (int) $data['revenue_minor'];
        $cogs = (int) $data['cogs_minor'];
        $directCosts = (int) $data['direct_costs_minor'];
        $globalOrderCosts = (int) ($data['global_order_costs_minor'] ?? 0);
        $storeExpenses = (int) $data['store_expenses_minor'];
        $netProfit = (int) $data['net_profit_minor'];

        $totalExpenses = $cogs
            + $directCosts
            + $globalOrderCosts
            + $storeExpenses;

        $orderCount = (int) $data['order_count'];
        $margin = $data['margin_percentage'];

        $averageProfit = $orderCount > 0
            ? (int) round($netProfit / $orderCount)
            : 0;

        $currencyLabel = Currency::label($currency);

        $navigation = $this->buildNavigationUrls(
            $range,
            $start,
            $end
        );

        $chartPayload = $this->buildChartPayload(
            $data,
            $currency,
            $precision,
            $navigation
        );

        ?>
        <div class="wrap hashieban-dashboard-v3">

            <section class="hb-hero">
                <div class="hb-hero__content">
                    <div class="hb-hero__eyebrow">حاشیه‌بان</div>
                    <h1>مرکز فرمان سودآوری فروشگاه</h1>
                    <p>
                        سود خالص، هزینه‌ها، روند فروش و وضعیت واقعی مالی
                        فروشگاه شما در یک نگاه.
                    </p>

                    <div class="hb-hero__meta">
                        <span>واحد نمایش: <strong><?php echo esc_html($currencyLabel); ?></strong></span>
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
                    </div>

                    <div class="hb-hero__actions">
                        <a href="<?php echo esc_url($navigation['analytics']); ?>">مرکز تحلیل‌ها</a>
                        <a href="<?php echo esc_url($navigation['orders']); ?>">بررسی سفارش‌ها</a>
                        <a href="<?php echo esc_url($navigation['alerts']); ?>">هشدارهای مدیریتی</a>
                    </div>
                </div>

                <div class="hb-hero__profit <?php echo $netProfit < 0 ? 'is-loss' : 'is-profit'; ?>">
                    <span>سود خالص شما</span>
                    <strong>
                        <?php
                        echo esc_html(
                            Currency::formatMinor(
                                $netProfit,
                                $currency,
                                $precision
                            )
                        );
                        ?>
                    </strong>
                    <small>
                        مبلغی که بعد از کسر همه هزینه‌ها برای شما مانده است
                    </small>
                </div>
            </section>

            <?php if (! OnboardingPage::isDismissed()) : ?>
                <section class="hb-onboarding-dashboard-card">
                    <div class="hb-onboarding-dashboard-card__copy">
                        <span class="hb-onboarding-dashboard-card__icon">
                            <span class="dashicons dashicons-lightbulb"></span>
                        </span>
                        <div>
                            <strong>راه‌اندازی سریع حاشیه‌بان</strong>
                            <p>در چند دقیقه مطمئن شو COGS، واحد پول، نقشه ایران و سرعت گزارش‌ها آماده‌اند.</p>
                        </div>
                    </div>
                    <div class="hb-onboarding-dashboard-card__actions">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=hashieban-onboarding')); ?>">شروع بررسی</a>
                        <a class="hb-onboarding-dashboard-card__dismiss" href="<?php echo esc_url(OnboardingPage::dismissUrl('dashboard')); ?>">دیگر نمایش نده</a>
                    </div>
                </section>
            <?php endif; ?>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <section class="hb-kpi-grid">
                <?php
                $this->renderKpi(
                    'فروش کل',
                    Currency::formatMinor($revenue, $currency, $precision),
                    'کل درآمد ثبت‌شده در این بازه',
                    $navigation['reports']
                );

                $this->renderKpi(
                    'کل هزینه‌ها',
                    Currency::formatMinor($totalExpenses, $currency, $precision),
                    'قیمت خرید کالا + هزینه سفارش + هزینه ثابت + هزینه کلی',
                    $navigation['expense_intelligence']
                );

                $this->renderKpi(
                    'حاشیه سود خالص',
                    $margin !== null
                        ? number_format_i18n((float) $margin, 1) . '٪'
                        : '—',
                    'درصدی از فروش که به سود خالص تبدیل شده',
                    $navigation['alerts']
                );

                $this->renderKpi(
                    'تعداد سفارش',
                    number_format_i18n($orderCount),
                    'تعداد سفارش‌های معتبر در این بازه',
                    $navigation['orders']
                );

                $this->renderKpi(
                    'میانگین سود هر سفارش',
                    Currency::formatMinor($averageProfit, $currency, $precision),
                    'میانگین سود خالص به ازای هر سفارش',
                    $navigation['orders']
                );

                $this->renderKpi(
                    'هزینه ثابت هر سفارش',
                    Currency::formatMinor(
                        (int) ($data['global_cost_per_order_minor'] ?? 0),
                        $currency,
                        $precision
                    ),
                    'هزینه‌ای که در تنظیمات روی همه سفارش‌ها اعمال می‌شود',
                    $navigation['settings']
                );

                $this->renderKpi(
                    'هزینه‌های کلی فروشگاه',
                    Currency::formatMinor($storeExpenses, $currency, $precision),
                    'هزینه‌هایی که جدا از سفارش‌ها ثبت شده‌اند',
                    $navigation['expenses']
                );

                $this->renderKpi(
                    'اطلاعات ناقص',
                    number_format_i18n((int) $data['incomplete_count']),
                    'تعداد سفارش‌هایی که اطلاعات مالی کامل ندارند',
                    $navigation['data_health']
                );
                ?>
            </section>

            <section class="hb-drilldown-strip">
                <div class="hb-drilldown-strip__intro">
                    <span class="hb-drilldown-strip__eyebrow">جزئیات هوشمند</span>
                    <strong>از عدد کلی برو سراغ دلیلش</strong>
                    <small>روی هر کارت یا نمودار کلیک کن تا مستقیم وارد تحلیل مرتبط شوی.</small>
                </div>

                <a class="hb-drilldown-link" href="<?php echo esc_url($navigation['products']); ?>">
                    <span>محصولات</span>
                    <strong>کدام کالا سود ساخت؟</strong>
                </a>

                <a class="hb-drilldown-link" href="<?php echo esc_url($navigation['customers']); ?>">
                    <span>مشتریان</span>
                    <strong>بهترین مشتری‌ها چه کسانی‌اند؟</strong>
                </a>

                <a class="hb-drilldown-link" href="<?php echo esc_url($navigation['time']); ?>">
                    <span>زمان</span>
                    <strong>رشد و افت از کجا آمده؟</strong>
                </a>

                <a class="hb-drilldown-link" href="<?php echo esc_url($navigation['geo']); ?>">
                    <span>جغرافیا</span>
                    <strong>کدام استان پول‌سازتر است؟</strong>
                </a>
            </section>

            <section class="hb-chart-layout">
                <div class="hb-card hb-card--xl">
                    <div class="hb-card__header">
                        <div>
                            <h2>نمودار روند مالی</h2>
                            <p>نمودار اصلی را به‌دلخواه بین ستونی، خطی و ناحیه‌ای تغییر بده.</p>
                        </div>

                        <div class="hb-toolbar">
                            <div class="hb-chip-group" id="hb-series-switcher">
                                <button type="button" class="is-active" data-series="revenue">فروش</button>
                                <button type="button" data-series="profit">سود خالص</button>
                                <button type="button" data-series="expenses">هزینه‌ها</button>
                            </div>

                            <div class="hb-chip-group" id="hb-trend-type-switcher">
                                <button type="button" class="is-active" data-type="bar">ستونی</button>
                                <button type="button" data-type="line">خطی</button>
                                <button type="button" data-type="area">ناحیه‌ای</button>
                            </div>
                        </div>
                    </div>

                    <div class="hb-canvas-wrap">
                        <canvas id="hashieban-trend-chart"></canvas>
                    </div>
                </div>

                <div class="hb-card">
                    <div class="hb-card__header">
                        <div>
                            <h2>ترکیب هزینه‌ها</h2>
                            <p>ساختار هزینه‌های فروشگاه را ببین.</p>
                        </div>

                        <div class="hb-chip-group" id="hb-composition-type-switcher">
                            <button type="button" class="is-active" data-type="doughnut">دونات</button>
                            <button type="button" data-type="pie">دایره‌ای</button>
                            <button type="button" data-type="polarArea">قطبی</button>
                        </div>
                    </div>

                    <div class="hb-canvas-wrap hb-canvas-wrap--sm">
                        <canvas id="hashieban-composition-chart"></canvas>
                    </div>
                </div>

                <div class="hb-card">
                    <div class="hb-card__header">
                        <div>
                            <h2>مقایسه فروش، هزینه و سود</h2>
                            <p>یک دید سریع از تصویر کلی بازه.</p>
                        </div>
                    </div>

                    <div class="hb-canvas-wrap hb-canvas-wrap--sm">
                        <canvas id="hashieban-summary-chart"></canvas>
                    </div>
                </div>
            </section>

            <section class="hb-card">
                <details class="hb-financial-details">
                    <summary>جزئیات مالی</summary>

                    <div class="hb-cost-breakdown">
                        <?php
                        $this->renderCostRow(
                            'قیمت خرید کالاها',
                            $cogs,
                            $currency,
                            $precision
                        );

                        $this->renderCostRow(
                            'هزینه‌های ثبت‌شده روی سفارش‌ها',
                            $directCosts,
                            $currency,
                            $precision
                        );

                        $this->renderCostRow(
                            'هزینه ثابت همه سفارش‌ها',
                            $globalOrderCosts,
                            $currency,
                            $precision
                        );

                        $this->renderCostRow(
                            'هزینه‌های کلی فروشگاه',
                            $storeExpenses,
                            $currency,
                            $precision
                        );

                        $this->renderCostRow(
                            'مجموع هزینه‌ها',
                            $totalExpenses,
                            $currency,
                            $precision,
                            true
                        );
                        ?>
                    </div>
                </details>
            </section>

            <section class="hb-card">
                <div class="hb-card__header">
                    <div>
                        <h2>سفارش‌های اخیر</h2>
                        <p>نمایی سریع از فروش و سود سفارش‌های آخر</p>
                    </div>
                </div>

                <div class="hb-table-wrap">
                    <table class="widefat striped hb-orders-table">
                        <thead>
                            <tr>
                                <th>سفارش</th>
                                <th>مشتری</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th>فروش</th>
                                <th>سود سفارش</th>
                                <th>حاشیه سود</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($data['recent_orders'] === array()) : ?>
                                <tr>
                                    <td colspan="7">سفارشی در این بازه پیدا نشد.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($data['recent_orders'] as $order) : ?>
                                    <tr>
                                        <td>
                                            <a
                                                class="hb-order-drilldown"
                                                href="<?php echo esc_url(add_query_arg(
                                                    array(
                                                        'page' => 'hashieban-orders',
                                                        'order_id' => (int) $order['id'],
                                                    ),
                                                    admin_url('admin.php')
                                                )); ?>"
                                            >
                                                #<?php echo esc_html($order['number']); ?>
                                                <span>جزئیات مالی</span>
                                            </a>
                                        </td>

                                        <td><?php echo esc_html($order['customer']); ?></td>
                                        <td><?php echo esc_html($order['date']); ?></td>
                                        <td><?php echo esc_html($order['status']); ?></td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                Currency::formatMinor(
                                                    (int) $order['revenue_minor'],
                                                    $currency,
                                                    $precision
                                                )
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    Currency::formatMinor(
                                                        (int) $order['profit_minor'],
                                                        $currency,
                                                        $precision
                                                    )
                                                );
                                                ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?php
                                            echo $order['margin_percentage'] !== null
                                                ? esc_html(
                                                    number_format_i18n(
                                                        (float) $order['margin_percentage'],
                                                        1
                                                    ) . '٪'
                                                )
                                                : '—';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <script
                id="hashieban-dashboard-data"
                type="application/json"
            ><?php echo wp_json_encode($chartPayload); ?></script>

        </div>
        <?php
    }

    private function buildChartPayload(
        array $data,
        string $currency,
        int $precision,
        array $navigation
    ): array {
        $labels = array();
        $revenue = array();
        $profit = array();
        $expenses = array();

        foreach ($data['daily'] as $bucket) {
            $labels[] = (string) $bucket['label'];

            $bucketRevenue = (int) ($bucket['revenue_minor'] ?? 0);
            $bucketProfit = (int) ($bucket['profit_minor'] ?? 0);
            $bucketExpenses =
                (int) ($bucket['cogs_minor'] ?? 0)
                + (int) ($bucket['direct_costs_minor'] ?? 0)
                + (int) ($bucket['global_order_costs_minor'] ?? 0)
                + (int) ($bucket['store_expenses_minor'] ?? 0);

            $revenue[] = Currency::minorToDisplayNumber(
                $bucketRevenue,
                $currency,
                $precision
            );

            $profit[] = Currency::minorToDisplayNumber(
                $bucketProfit,
                $currency,
                $precision
            );

            $expenses[] = Currency::minorToDisplayNumber(
                $bucketExpenses,
                $currency,
                $precision
            );
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'navigation' => array(
                'trend' => $navigation['time'],
                'composition' => array(
                    $navigation['products'],
                    $navigation['expense_intelligence'],
                    $navigation['settings'],
                    $navigation['expenses'],
                ),
                'summary' => array(
                    $navigation['reports'],
                    $navigation['expense_intelligence'],
                    $navigation['reports'],
                ),
            ),
            'trend' => array(
                'labels' => $labels,
                'revenue' => $revenue,
                'profit' => $profit,
                'expenses' => $expenses,
            ),
            'composition' => array(
                'labels' => array(
                    'قیمت خرید کالاها',
                    'هزینه سفارش‌ها',
                    'هزینه ثابت سفارش',
                    'هزینه‌های کلی فروشگاه',
                ),
                'values' => array(
                    Currency::minorToDisplayNumber(
                        (int) $data['cogs_minor'],
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) $data['direct_costs_minor'],
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) ($data['global_order_costs_minor'] ?? 0),
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) $data['store_expenses_minor'],
                        $currency,
                        $precision
                    ),
                ),
            ),
            'summary' => array(
                'labels' => array(
                    'فروش',
                    'هزینه',
                    'سود خالص',
                ),
                'values' => array(
                    Currency::minorToDisplayNumber(
                        (int) $data['revenue_minor'],
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (
                            (int) $data['cogs_minor']
                            + (int) $data['direct_costs_minor']
                            + (int) ($data['global_order_costs_minor'] ?? 0)
                            + (int) $data['store_expenses_minor']
                        ),
                        $currency,
                        $precision
                    ),
                    Currency::minorToDisplayNumber(
                        (int) $data['net_profit_minor'],
                        $currency,
                        $precision
                    ),
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
            '2y' => '۲ سال',
            '3y' => '۳ سال',
            'all' => 'همه',
        );

        ?>
        <div class="hb-range-bar">
            <div class="hb-range-buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a
                        class="hb-range-button <?php echo $range === $key ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(
                            array(
                                'page' => 'hashieban',
                                'range' => $key,
                            ),
                            admin_url('admin.php')
                        )); ?>"
                    >
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="hb-custom-range">
                <input type="hidden" name="page" value="hashieban">
                <input type="hidden" name="range" value="custom">

                <label>
                    از
                    <input
                        type="text"
                        name="start_date"
                        value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>"
                        autocomplete="off"
                        data-jdp
                        placeholder="۱۴۰۵/۰۵/۰۱"
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
                        placeholder="۱۴۰۵/۰۵/۱۷"
                    >
                </label>

                <button type="submit" class="button">اعمال</button>
            </form>
        </div>
        <?php
    }

    private function renderKpi(
        string $title,
        string $value,
        string $description,
        string $url = ''
    ): void {
        $tag = $url !== '' ? 'a' : 'div';
        ?>
        <<?php echo esc_html($tag); ?>
            class="hb-kpi-card <?php echo $url !== '' ? 'hb-kpi-card--link' : ''; ?>"
            <?php if ($url !== '') : ?>href="<?php echo esc_url($url); ?>"<?php endif; ?>
        >
            <span class="hb-kpi-title"><?php echo esc_html($title); ?></span>
            <strong class="hb-kpi-value"><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($description); ?></small>
            <?php if ($url !== '') : ?>
                <span class="hb-kpi-open">مشاهده تحلیل ←</span>
            <?php endif; ?>
        </<?php echo esc_html($tag); ?>>
        <?php
    }

    private function buildNavigationUrls(
        string $range,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $rangeArgs = array(
            'range' => $range,
        );

        if ($range === 'custom') {
            $rangeArgs['start_date'] = JalaliDate::numeric($start);
            $rangeArgs['end_date'] = JalaliDate::numeric($end);
        }

        $url = static function (
            string $page,
            array $extra = array()
        ) use ($rangeArgs): string {
            return add_query_arg(
                array_merge(
                    array('page' => $page),
                    $rangeArgs,
                    $extra
                ),
                admin_url('admin.php')
            );
        };

        return array(
            'analytics' => $url('hashieban-analytics'),
            'products' => $url('hashieban-products'),
            'customers' => $url('hashieban-customers'),
            'time' => $url('hashieban-time'),
            'orders' => $url('hashieban-orders'),
            'alerts' => $url('hashieban-alerts'),
            'reports' => $url('hashieban-reports'),
            'expense_intelligence' => $url('hashieban-expense-intelligence'),
            'data_health' => $url('hashieban-data-health'),
            'geo' => $url('hashieban-geo'),
            'expenses' => $url('hashieban-expenses'),
            'settings' => $url('hashieban-settings'),
        );
    }

    private function renderCostRow(
        string $title,
        int $amount,
        string $currency,
        int $precision,
        bool $total = false
    ): void {
        ?>
        <div class="hb-cost-row <?php echo $total ? 'is-total' : ''; ?>">
            <span><?php echo esc_html($title); ?></span>
            <strong>
              <?php
              echo esc_html(
                  Currency::formatMinor(
                      $amount,
                      $currency,
                      $precision
                  )
              );
              ?>
            </strong>
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

			if (is_array($orders) && isset($orders[0]) && $orders[0] instanceof \WC_Order) {
				$date = $orders[0]->get_date_created();

				if ($date) {
					return (new DateTimeImmutable(
						'@' . $date->getTimestamp()
					))
								   ->setTimezone(wp_timezone())
								   ->setTime(0, 0, 0);
				}
			}

			return $fallback->modify('-3 years')->setTime(0, 0, 0);
		}

		private function enqueueAssets(): void
		{
			wp_enqueue_style(
				'hashieban-onboarding',
				plugins_url(
					'assets/admin/css/hashieban-onboarding.css',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION
			);

			wp_enqueue_style(
				'hashieban-dashboard-v3',
				plugins_url(
					'assets/admin/css/hashieban-dashboard.css',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION
			);

			wp_enqueue_script(
				'hashieban-chartjs',
				plugins_url(
					'assets/vendor/chartjs/chart.umd.js',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION,
				true
			);

			wp_enqueue_script(
				'hashieban-dashboard-v3',
				plugins_url(
					'assets/admin/js/hashieban-dashboard.js',
					HASHIEBAN_FILE
				),
				array(
					'hashieban-chartjs',
				),
				HASHIEBAN_VERSION,
				true
			);
		}
		}
