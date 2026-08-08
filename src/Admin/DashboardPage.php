<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Support\Currency;

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
                esc_html(
                    'شما اجازه دسترسی به این بخش را ندارید.'
                )
            );
        }

        $this->enqueueAssets();

        [$start, $end, $range] =
            $this->resolveDateRange();

        $data = $this->analytics
            ->getDashboardData(
                $start,
                $end
            );

        $currency = $data['currency'];
        $precision = (int) $data['precision'];

        $revenue =
            (int) $data['revenue_minor'];

        $cogs =
            (int) $data['cogs_minor'];

        $directCosts =
            (int) $data['direct_costs_minor'];

        $storeExpenses =
            (int) $data['store_expenses_minor'];

        $netProfit =
            (int) $data['net_profit_minor'];

        $totalExpenses =
            $cogs
            + $directCosts
            + $storeExpenses;

        $orderCount =
            (int) $data['order_count'];

        $margin =
            $data['margin_percentage'];

        $averageProfit =
            $orderCount > 0
                ? (int) round(
                    $netProfit / $orderCount
                )
                : 0;

        $bestRevenueBucket =
            $this->findBestBucket(
                $data['daily'],
                'revenue_minor'
            );

        $bestProfitBucket =
            $this->findBestBucket(
                $data['daily'],
                'profit_minor'
            );

        $currencyLabel =
            Currency::label($currency);

        ?>
        <div class="wrap hashieban-dashboard-v2">

            <div class="hb-dashboard-header">

                <div>
                    <h1>
                        حاشیه‌بان
                    </h1>

                    <p>
                        وضعیت واقعی مالی فروشگاه در بازه انتخاب‌شده
                    </p>
                </div>

                <div class="hb-currency-pill">
                    واحد مالی:
                    <strong>
                        <?php
                        echo esc_html(
                            $currencyLabel
                        );
                        ?>
                    </strong>
                </div>

            </div>

            <?php
            $this->renderRangeFilters(
                $range,
                $start,
                $end
            );
            ?>

            <section
                class="hb-main-profit-card <?php
                echo $netProfit < 0
                    ? 'is-loss'
                    : 'is-profit';
                ?>"
            >

                <div class="hb-main-profit-label">
                    سود خالص شما
                </div>

                <div class="hb-main-profit-value">
                    <?php
                    echo esc_html(
                        Currency::formatMinor(
                            $netProfit,
                            $currency,
                            $precision
                        )
                    );
                    ?>
                </div>

                <p>
                    مبلغی که بعد از کسر قیمت خرید کالاها،
                    هزینه‌های سفارش و هزینه‌های کلی فروشگاه
                    برای شما باقی مانده است.
                </p>

                <div class="hb-profit-confidence">
                    محاسبه بر اساس تمام هزینه‌هایی است که
                    تاکنون در حاشیه‌بان ثبت کرده‌اید.
                </div>

            </section>

            <section class="hb-kpi-grid">

                <?php
                $this->renderKpi(
                    'فروش کل',
                    Currency::formatMinor(
                        $revenue,
                        $currency,
                        $precision
                    ),
                    'کل درآمد ثبت‌شده در این بازه'
                );

                $this->renderKpi(
                    'کل هزینه‌ها',
                    Currency::formatMinor(
                        $totalExpenses,
                        $currency,
                        $precision
                    ),
                    'قیمت خرید کالا + هزینه سفارش + هزینه‌های فروشگاه'
                );

                $this->renderKpi(
                    'حاشیه سود خالص',
                    $margin !== null
                        ? number_format_i18n(
                            (float) $margin,
                            1
                        ) . '٪'
                        : '—',
                    'درصدی از فروش که تبدیل به سود شده'
                );

                $this->renderKpi(
                    'تعداد سفارش',
                    number_format_i18n(
                        $orderCount
                    ),
                    'سفارش‌های معتبر این بازه'
                );

                $this->renderKpi(
                    'میانگین سود هر سفارش',
                    Currency::formatMinor(
                        $averageProfit,
                        $currency,
                        $precision
                    ),
                    'میانگین سود خالص به ازای هر سفارش'
                );

                $this->renderKpi(
                    'اطلاعات ناقص',
                    number_format_i18n(
                        (int) $data[
                            'incomplete_count'
                        ]
                    ),
                    'سفارش‌هایی که اطلاعات مالی کامل ندارند'
                );
                ?>

            </section>

            <section class="hb-dashboard-grid">

                <div class="hb-panel hb-chart-panel">

                    <div class="hb-panel-header">

                        <div>
                            <h2>
                                روند مالی
                            </h2>

                            <p>
                                روی هر بازه کلیک کنید تا جزئیاتش را ببینید.
                            </p>
                        </div>

                        <div
                            class="hb-chart-switcher"
                            role="group"
                        >

                            <button
                                type="button"
                                class="hb-chart-switch is-active"
                                data-series="revenue"
                            >
                                فروش
                            </button>

                            <button
                                type="button"
                                class="hb-chart-switch"
                                data-series="profit"
                            >
                                سود خالص
                            </button>

                        </div>

                    </div>

                    <div
                        class="hb-live-chart"
                        id="hashieban-live-chart"
                        data-currency="<?php
                        echo esc_attr(
                            $currencyLabel
                        );
                        ?>"
                    >

                        <?php
                        foreach (
                            $data['daily']
                            as $bucket
                        ) :
                            ?>

                            <button
                                type="button"
                                class="hb-chart-column"
                                data-label="<?php
                                echo esc_attr(
                                    $bucket['label']
                                );
                                ?>"
                                data-revenue="<?php
                                echo esc_attr(
                                    (string) $bucket[
                                        'revenue_minor'
                                    ]
                                );
                                ?>"
                                data-profit="<?php
                                echo esc_attr(
                                    (string) $bucket[
                                        'profit_minor'
                                    ]
                                );
                                ?>"
                            >

                                <span
                                    class="hb-chart-value"
                                ></span>

                                <span
                                    class="hb-chart-bar-wrap"
                                >
                                    <span
                                        class="hb-chart-bar"
                                    ></span>
                                </span>

                                <span
                                    class="hb-chart-label"
                                >
                                    <?php
                                    echo esc_html(
                                        $bucket['label']
                                    );
                                    ?>
                                </span>

                            </button>

                        <?php endforeach; ?>

                    </div>

                    <div
                        class="hb-chart-detail"
                        id="hashieban-chart-detail"
                        hidden
                    >

                        <strong
                            id="hashieban-chart-detail-title"
                        ></strong>

                        <span>
                            فروش:
                            <b
                                id="hashieban-chart-detail-revenue"
                            ></b>
                        </span>

                        <span>
                            سود:
                            <b
                                id="hashieban-chart-detail-profit"
                            ></b>
                        </span>

                    </div>

                </div>

                <div class="hb-panel">

                    <div class="hb-panel-header">
                        <div>
                            <h2>
                                خلاصه عملکرد
                            </h2>

                            <p>
                                بهترین نقاط این بازه
                            </p>
                        </div>
                    </div>

                    <div class="hb-highlight-list">

                        <div class="hb-highlight-item">
                            <span>
                                بهترین بازه فروش
                            </span>

                            <strong>
                                <?php
                                echo esc_html(
                                    $bestRevenueBucket
                                        ? $bestRevenueBucket[
                                            'label'
                                        ]
                                        : '—'
                                );
                                ?>
                            </strong>

                            <?php
                            if ($bestRevenueBucket) :
                                ?>
                                <small>
                                    <?php
                                    echo esc_html(
                                        Currency::formatMinor(
                                            (int) $bestRevenueBucket[
                                                'revenue_minor'
                                            ],
                                            $currency,
                                            $precision
                                        )
                                    );
                                    ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="hb-highlight-item">
                            <span>
                                بیشترین سود
                            </span>

                            <strong>
                                <?php
                                echo esc_html(
                                    $bestProfitBucket
                                        ? $bestProfitBucket[
                                            'label'
                                        ]
                                        : '—'
                                );
                                ?>
                            </strong>

                            <?php
                            if ($bestProfitBucket) :
                                ?>
                                <small>
                                    <?php
                                    echo esc_html(
                                        Currency::formatMinor(
                                            (int) $bestProfitBucket[
                                                'profit_minor'
                                            ],
                                            $currency,
                                            $precision
                                        )
                                    );
                                    ?>
                                </small>
                            <?php endif; ?>
                        </div>

                    </div>

                </div>

            </section>

            <section class="hb-panel">

                <details class="hb-financial-details">

                    <summary>
                        جزئیات هزینه‌ها
                    </summary>

                    <p class="hb-details-help">
                        اگر فقط می‌خواهید بدانید چقدر سود کرده‌اید،
                        نیازی نیست این بخش را بررسی کنید.
                    </p>

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

            <section class="hb-panel">

                <div class="hb-panel-header">

                    <div>
                        <h2>
                            سفارش‌های اخیر
                        </h2>

                        <p>
                            فروش و سود هر سفارش
                        </p>
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

                        <?php
                        if (
                            $data[
                                'recent_orders'
                            ] === array()
                        ) :
                            ?>

                            <tr>
                                <td colspan="7">
                                    سفارشی در این بازه پیدا نشد.
                                </td>
                            </tr>

                        <?php
                        else :
                            foreach (
                                $data['recent_orders']
                                as $order
                            ) :
                                ?>

                                <tr>

                                    <td>
                                        <a
                                            href="<?php
                                            echo esc_url(
                                                $order[
                                                    'edit_url'
                                                ]
                                            );
                                            ?>"
                                        >
                                            #<?php
                                            echo esc_html(
                                                $order[
                                                    'number'
                                                ]
                                            );
                                            ?>
                                        </a>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $order[
                                                'customer'
                                            ]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $order['date']
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $order[
                                                'status'
                                            ]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            Currency::formatMinor(
                                                (int) $order[
                                                    'revenue_minor'
                                                ],
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
                                                    (int) $order[
                                                        'profit_minor'
                                                    ],
                                                    $currency,
                                                    $precision
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php
                                        echo $order[
                                            'margin_percentage'
                                        ] !== null
                                            ? esc_html(
                                                number_format_i18n(
                                                    (float) $order[
                                                        'margin_percentage'
                                                    ],
                                                    1
                                                )
                                                . '٪'
                                            )
                                            : '—';
                                        ?>
                                    </td>

                                </tr>

                            <?php
                            endforeach;
                        endif;
                        ?>

                        </tbody>

                    </table>

                </div>

            </section>

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
            '3m' => '۳ ماه',
            '6m' => '۶ ماه',
            'year' => 'یک سال',
        );

        ?>
        <div class="hb-range-bar">

            <div class="hb-range-buttons">

                <?php
                foreach (
                    $ranges as $key => $label
                ) :
                    ?>

                    <a
                        class="hb-range-button <?php
                        echo $range === $key
                            ? 'is-active'
                            : '';
                        ?>"
                        href="<?php
                        echo esc_url(
                            add_query_arg(
                                array(
                                    'page' =>
                                        'hashieban',
                                    'range' =>
                                        $key,
                                ),
                                admin_url(
                                    'admin.php'
                                )
                            )
                        );
                        ?>"
                    >
                        <?php
                        echo esc_html($label);
                        ?>
                    </a>

                <?php endforeach; ?>

            </div>

            <form
                method="get"
                class="hb-custom-range"
            >

                <input
                    type="hidden"
                    name="page"
                    value="hashieban"
                >

                <input
                    type="hidden"
                    name="range"
                    value="custom"
                >

                <label>
                    از
                    <input
                        type="date"
                        name="start_date"
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
                        name="end_date"
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
                    اعمال
                </button>

            </form>

        </div>
        <?php
    }

    private function renderKpi(
        string $title,
        string $value,
        string $description
    ): void {
        ?>
        <div class="hb-kpi-card">

            <span class="hb-kpi-title">
                <?php echo esc_html($title); ?>
            </span>

            <strong class="hb-kpi-value">
                <?php echo esc_html($value); ?>
            </strong>

            <small>
                <?php
                echo esc_html(
                    $description
                );
                ?>
            </small>

        </div>
        <?php
    }

    private function renderCostRow(
        string $title,
        int $amount,
        string $currency,
        int $precision,
        bool $total = false
    ): void {
        ?>
        <div
            class="hb-cost-row <?php
            echo $total
                ? 'is-total'
                : '';
            ?>"
        >

            <span>
                <?php
                echo esc_html($title);
                ?>
            </span>

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

		private function findBestBucket(
			array $buckets,
			string $field
		): ?array {
			$best = null;

			foreach ($buckets as $bucket) {
				if (
					! isset($bucket[$field])
				) {
					continue;
				}

				if (
					$best === null
					|| (int) $bucket[$field]
                    > (int) $best[$field]
				) {
					$best = $bucket;
				}
			}

			return $best;
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
                wp_unslash(
                    $_GET['range']
                )
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

				case '3m':
					$start = $now
                    ->modify('-3 months')
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

				case 'custom':
					$custom =
						$this->resolveCustomRange(
							$now
						);

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

		private function resolveCustomRange(
			DateTimeImmutable $now
		): ?array {
			$startValue = isset(
				$_GET['start_date']
			)
            ? sanitize_text_field(
                wp_unslash(
                    $_GET['start_date']
                )
            )
						: '';

			$endValue = isset(
				$_GET['end_date']
			)
            ? sanitize_text_field(
                wp_unslash(
                    $_GET['end_date']
                )
            )
					  : '';

			if (
				$startValue === ''
				|| $endValue === ''
			) {
				return null;
			}

			$start =
				DateTimeImmutable::createFromFormat(
					'!Y-m-d',
					$startValue,
					wp_timezone()
				);

			$end =
				DateTimeImmutable::createFromFormat(
					'!Y-m-d',
					$endValue,
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

		private function enqueueAssets(): void
		{
			wp_enqueue_style(
				'hashieban-dashboard-v2',
				plugins_url(
					'assets/admin/css/hashieban-dashboard.css',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION
			);

			wp_enqueue_script(
				'hashieban-dashboard-v2',
				plugins_url(
					'assets/admin/js/hashieban-dashboard.js',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION,
				true
			);
		}
		}
