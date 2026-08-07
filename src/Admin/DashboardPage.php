<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;

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
                esc_html__(
                    'شما اجازه دسترسی به این صفحه را ندارید.',
                    'hashieban'
                )
            );
        }

        [$start, $end, $activeRange] =
            $this->resolveDateRange();

        $data = $this->analytics->getDashboardData(
            $start,
            $end
        );

        ?>
        <div class="wrap hb-admin" dir="rtl">

            <div class="hb-header">
                <div>
                    <h1>حاشیه‌بان</h1>

                    <p>
                        تصویر واقعی‌تری از سودآوری فروشگاه شما
                    </p>
                </div>

                <div class="hb-period">
                    <?php
                    echo esc_html(
                        $start->format('Y/m/d')
                        . ' تا '
                        . $end->format('Y/m/d')
                    );
                    ?>
                </div>
            </div>

            <?php
            $this->renderRangeSelector(
                $activeRange,
                $start,
                $end
            );
            ?>

            <?php
            $this->renderCards($data);
            ?>

            <div class="hb-grid hb-grid-main">

                <section class="hb-panel">
                    <div class="hb-panel-heading">
                        <div>
                            <h2>روند فروش و سود</h2>
                            <p>
                                عملکرد سفارش‌های معتبر در بازه انتخاب‌شده
                            </p>
                        </div>
                    </div>

                    <?php
                    $this->renderChart(
                        $data['daily']
                    );
                    ?>
                </section>

                <section class="hb-panel hb-margin-panel">

                    <div class="hb-panel-heading">
                        <div>
                            <h2>حاشیه سود</h2>
                            <p>
                                نسبت سود ناخالص به درآمد
                            </p>
                        </div>
                    </div>

                    <?php
                    $this->renderMarginRing(
                        $data['margin_percentage']
                    );
                    ?>

                </section>

            </div>

            <?php
            $this->renderRecentOrders(
                $data
            );
            ?>

            <div class="hb-development-note">
                <strong>
                    مرحله فعلی:
                </strong>

                سود نمایش‌داده‌شده در این نسخه توسعه،
                سود ناخالص بر پایه فروش و COGS است.

                هزینه واقعی ارسال، بسته‌بندی، کارمزد درگاه
                و سایر هزینه‌های مستقیم در مراحل بعد به
                موتور سود اضافه می‌شوند.
            </div>

        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderCards(
        array $data
    ): void {
        ?>
        <div class="hb-kpis">

            <?php
            $this->renderMoneyCard(
                'فروش',
                $data['revenue_minor'],
                $data,
                'مجموع درآمد سفارش‌های معتبر'
            );

            $this->renderMoneyCard(
                'بهای تمام‌شده',
                $data['cogs_minor'],
                $data,
                'هزینه خرید کالاهای فروخته‌شده'
            );

            $this->renderMoneyCard(
                'سود ناخالص فعلی',
                $data['profit_minor'],
                $data,
                'فروش منهای بهای تمام‌شده',
                $data['profit_minor'] < 0
                    ? 'danger'
                    : 'success'
            );
            ?>

            <div class="hb-card">
                <span class="hb-card-label">
                    تعداد سفارش
                </span>

                <strong class="hb-card-value">
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int) $data['order_count']
                        )
                    );
                    ?>
                </strong>

                <span class="hb-card-help">
                    سفارش تکمیل‌شده یا در حال انجام
                </span>
            </div>

        </div>

        <div class="hb-health-row">

            <div class="hb-health-item">
                <span>وضعیت داده‌های مالی</span>

                <?php if ((int) $data['incomplete_count'] === 0) : ?>

                    <strong class="hb-good">
                        کامل
                    </strong>

                <?php else : ?>

                    <strong class="hb-warning">
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                (int) $data['incomplete_count']
                            )
                        );
                        ?>
                        سفارش نیازمند بررسی
                    </strong>

                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderMoneyCard(
        string $title,
        int $minorAmount,
        array $data,
        string $help,
        string $class = ''
    ): void {
        ?>
        <div class="hb-card <?php echo esc_attr($class); ?>">

            <span class="hb-card-label">
                <?php echo esc_html($title); ?>
            </span>

            <strong class="hb-card-value">
                <?php
                echo wp_kses_post(
                    $this->formatMoney(
                        $minorAmount,
                        (int) $data['precision'],
                        (string) $data['currency']
                    )
                );
                ?>
            </strong>

            <span class="hb-card-help">
                <?php echo esc_html($help); ?>
            </span>

        </div>
        <?php
    }

    /**
     * @param array<int, array<string, mixed>> $daily
     */
    private function renderChart(
        array $daily
    ): void {
        if ($daily === []) {
            ?>
            <div class="hb-empty-chart">
                هنوز سفارش معتبری در این بازه وجود ندارد.
            </div>
            <?php
            return;
        }

        $daily = array_slice(
            $daily,
            -14
        );

        $max = 1;

        foreach ($daily as $day) {
            $max = max(
                $max,
                (int) $day['revenue_minor']
            );
        }

        ?>
        <div class="hb-chart">

            <?php foreach ($daily as $day) : ?>

                <?php
                $revenueHeight = (
                    (int) $day['revenue_minor']
                    / $max
                ) * 100;

                $profitHeight = (
                    abs((int) $day['profit_minor'])
                    / $max
                ) * 100;

                $profitClass =
                    (int) $day['profit_minor'] < 0
                        ? 'negative'
                        : '';
                ?>

                <div class="hb-chart-day">

                    <div class="hb-bars">

                        <span
                            class="hb-bar revenue"
                            style="<?php
                            echo esc_attr(
                                'height:'
                                . max(
                                    4,
                                    $revenueHeight
                                )
                                . '%'
                            );
                            ?>"
                            title="فروش"
                        ></span>

                        <span
                            class="hb-bar profit <?php
                            echo esc_attr(
                                $profitClass
                            );
                            ?>"
                            style="<?php
                            echo esc_attr(
                                'height:'
                                . max(
                                    4,
                                    $profitHeight
                                )
                                . '%'
                            );
                            ?>"
                            title="سود"
                        ></span>

                    </div>

                    <span class="hb-chart-date">
                        <?php
                        echo esc_html(
                            wp_date(
                                'm/d',
                                (int) $day['timestamp']
                            )
                        );
                        ?>
                    </span>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="hb-chart-legend">
            <span>
                <i class="revenue"></i>
                فروش
            </span>

            <span>
                <i class="profit"></i>
                سود
            </span>
        </div>
        <?php
    }

    private function renderMarginRing(
        ?float $margin
    ): void {
        $display = $margin !== null
            ? round($margin, 1)
            : 0.0;

        $ring = max(
            0,
            min(
                100,
                $display
            )
        );

        ?>
        <div
            class="hb-margin-ring"
            style="<?php
            echo esc_attr(
                '--hb-margin:' . $ring
            );
            ?>"
        >
            <div class="hb-margin-ring-inner">

                <strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            $display,
                            1
                        )
                    );
                    ?>%
                </strong>

                <span>
                    حاشیه سود
                </span>

            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderRecentOrders(
        array $data
    ): void {
        ?>
        <section class="hb-panel hb-orders">

            <div class="hb-panel-heading">
                <div>
                    <h2>آخرین سفارش‌های این بازه</h2>

                    <p>
                        دیگر نیازی به واردکردن دستی شناسه سفارش نیست.
                    </p>
                </div>
            </div>

            <?php if ($data['recent_orders'] === []) : ?>

                <div class="hb-empty">
                    سفارشی برای نمایش پیدا نشد.
                </div>

            <?php else : ?>

                <div class="hb-table-wrap">

                    <table class="hb-table">

                        <thead>
                            <tr>
                                <th>سفارش</th>
                                <th>مشتری</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th>فروش</th>
                                <th>سود</th>
                                <th>حاشیه</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php
                        foreach (
                            $data['recent_orders']
                            as $order
                        ) :
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        #
                                        <?php
                                        echo esc_html(
                                            (string) $order['number']
                                        );
                                        ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) $order['customer']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) $order['date']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <span class="hb-status">
                                        <?php
                                        echo esc_html(
                                            (string) $order['status']
                                        );
                                        ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    echo wp_kses_post(
                                        $this->formatMoney(
                                            (int) $order['revenue_minor'],
                                            (int) $data['precision'],
                                            (string) $data['currency']
                                        )
                                    );
                                    ?>
                                </td>

                                <td class="<?php
                                echo (int) $order['profit_minor'] < 0
                                    ? 'hb-negative'
                                    : 'hb-positive';
                                ?>">
                                    <?php
                                    echo wp_kses_post(
                                        $this->formatMoney(
                                            (int) $order['profit_minor'],
                                            (int) $data['precision'],
                                            (string) $data['currency']
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    if (
                                        $order['margin_percentage']
                                        !== null
                                    ) {
                                        echo esc_html(
                                            number_format_i18n(
                                                (float) $order['margin_percentage'],
                                                1
                                            )
                                            . '%'
                                        );
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <a
                                        class="hb-link"
                                        href="<?php
                                        echo esc_url(
                                            (string) $order['edit_url']
                                        );
                                        ?>"
                                    >
                                        مشاهده سفارش
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>
        <?php
    }

    private function renderRangeSelector(
        string $active,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): void {
        $ranges = [
            '7d' => '۷ روز',
            '30d' => '۳۰ روز',
            '90d' => '۹۰ روز',
            'year' => 'یک سال',
        ];

        ?>
        <div class="hb-filters">

            <div class="hb-range-buttons">

                <?php foreach ($ranges as $key => $label) : ?>

                    <a
                        href="<?php
                        echo esc_url(
                            add_query_arg(
                                [
                                    'page' => 'hashieban',
                                    'hb_range' => $key,
                                ],
                                admin_url('admin.php')
                            )
                        );
                        ?>"
                        class="<?php
                        echo $active === $key
                            ? 'active'
                            : '';
                        ?>"
                    >
                        <?php echo esc_html($label); ?>
                    </a>

                <?php endforeach; ?>

            </div>

            <form
                method="get"
                action="<?php echo esc_url(admin_url('admin.php')); ?>"
                class="hb-custom-range"
            >
                <input
                    type="hidden"
                    name="page"
                    value="hashieban"
                >

                <input
                    type="hidden"
                    name="hb_range"
                    value="custom"
                >

                <label>
                    از
                    <input
                        type="date"
                        name="hb_from"
                        value="<?php
                        echo esc_attr(
                            $start->format('Y-m-d')
                        );
                        ?>"
                    >
                </label>

                <label>
                    تا
                    <input
                        type="date"
                        name="hb_to"
                        value="<?php
                        echo esc_attr(
                            $end->format('Y-m-d')
                        );
                        ?>"
                    >
                </label>

                <button
                    type="submit"
                    class="button"
                >
                  اعمال بازه
                </button>

            </form>

        </div>
        <?php
		}

		/**
		 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string}
		 */
		private function resolveDateRange(): array
		{
			$timezone = wp_timezone();

			$today = new DateTimeImmutable(
				'today',
				$timezone
			);

			$active = isset($_GET['hb_range'])
            ? sanitize_key(
                wp_unslash(
                    $_GET['hb_range']
                )
            )
					: '30d';

			switch ($active) {
				case '7d':
					$start = $today->modify('-6 days');
					break;

				case '90d':
					$start = $today->modify('-89 days');
					break;

				case 'year':
					$start = $today->modify('-1 year');
					break;

				case 'custom':
					$start = $this->parseDate(
						isset($_GET['hb_from'])
                        ? (string) wp_unslash($_GET['hb_from'])
                        : '',
						$today->modify('-29 days')
					);

					$today = $this->parseDate(
						isset($_GET['hb_to'])
                        ? (string) wp_unslash($_GET['hb_to'])
                        : '',
						$today
					);

					if ($start > $today) {
						[$start, $today] = [
							$today,
							$start,
						];
					}

					break;

				case '30d':
				default:
					$active = '30d';
					$start = $today->modify('-29 days');
					break;
			}

			return [
				$start,
				$today,
				$active,
			];
		}

		private function parseDate(
			string $date,
			DateTimeImmutable $fallback
		): DateTimeImmutable {
			$parsed = DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				sanitize_text_field($date),
				wp_timezone()
			);

			return $parsed instanceof DateTimeImmutable
            ? $parsed
				 : $fallback;
		}

		private function formatMoney(
			int $minorAmount,
			int $precision,
			string $currency
		): string {
			$amount = $minorAmount;

			if ($precision > 0) {
				$amount = $minorAmount
                / (10 ** $precision);
			}

			return wc_price(
				$amount,
				[
					'currency' => $currency,
				]
			);
		}
		}
