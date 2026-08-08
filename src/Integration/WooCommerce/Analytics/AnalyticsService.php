<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use Hashieban\Domain\Money\Money;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Support\JalaliDate;
use WC_Order;

final class AnalyticsService
{
    private OrderAdapter $orderAdapter;
    private StoreExpenseRepository $expenseRepository;
    private GlobalOrderCostRepository $globalCosts;
    private ProfitEngine $profitEngine;

    public function __construct(
        OrderAdapter $orderAdapter,
        StoreExpenseRepository $expenseRepository,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine
    ) {
        $this->orderAdapter =
            $orderAdapter;

        $this->expenseRepository =
            $expenseRepository;

        $this->globalCosts =
            $globalCosts;

        $this->profitEngine =
            $profitEngine;
    }

    public function getDashboardData(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency =
            get_woocommerce_currency();

        $precision =
            wc_get_price_decimals();

        $globalCostPerOrder =
            $this->globalCosts->total(
                $currency,
                $precision
            );

        $revenueMinor = 0;
        $cogsMinor = 0;
        $directCostsMinor = 0;
        $globalCostsMinor = 0;

        $orderCount = 0;
        $incompleteCount = 0;

        $recentOrders = array();
        $buckets = array();

        $bucketMode =
            $this->resolveBucketMode(
                $start,
                $end
            );

        $page = 1;
        $maxPages = 1;

        do {
            $result = wc_get_orders(
                array(
                    'status' => array(
                        'processing',
                        'completed',
                    ),
                    'currency' =>
                        $currency,
                    'limit' => 100,
                    'page' => $page,
                    'paginate' => true,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'date_created' =>
                        $start->format(
                            'Y-m-d H:i:s'
                        )
								  . '...'
								  . $end->format(
									  'Y-m-d H:i:s'
								  ),
                )
            );

            if (
                ! is_object($result)
                || ! isset($result->orders)
            ) {
                break;
            }

            $maxPages =
                isset(
                    $result->max_num_pages
                )
            ? max(
                1,
                (int) $result
                            ->max_num_pages
            )
                : 1;

            foreach (
                $result->orders
                as $order
            ) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $financial =
                    $this->orderAdapter
                         ->fromOrder($order);

                $profitResult =
                    $this->profitEngine
                         ->calculateOrder(
                             $financial,
                             $globalCostPerOrder
                         );

                $breakdown =
                    $profitResult
                        ->breakdown();

                $revenue =
                    $breakdown
                        ->revenue()
                        ->minorAmount();

                $cogs =
                    $breakdown
                        ->cogs()
                        ->minorAmount();

                $directCosts =
                    $breakdown
                        ->orderCosts()
                        ->minorAmount();

                $globalOrderCosts =
                    $breakdown
                        ->globalOrderCosts()
                        ->minorAmount();

                $orderProfit =
                    $profitResult
                        ->profit()
                        ->minorAmount();

                $revenueMinor +=
                    $revenue;

                $cogsMinor +=
                    $cogs;

                $directCostsMinor +=
                    $directCosts;

                $globalCostsMinor +=
                    $globalOrderCosts;

                $orderCount++;

                if (
                    $profitResult
                        ->completeness()
                        ->isIncomplete()
                ) {
                    $incompleteCount++;
                }

                $date =
                    $order
                        ->get_date_created();

                if ($date) {
                    $bucketKey =
                        $this->bucketKey(
                            $date,
                            $bucketMode
                        );

                    if (
                        ! isset(
                            $buckets[
                                $bucketKey
                            ]
                        )
                    ) {
                        $buckets[
                            $bucketKey
                        ] =
                            $this->newBucket(
                                $date,
                                $bucketMode
                            );
                    }

                    $buckets[
                        $bucketKey
                    ]['revenue_minor'] +=
                        $revenue;

                    $buckets[
                        $bucketKey
                    ]['cogs_minor'] +=
                        $cogs;

                    $buckets[
                        $bucketKey
                    ]['direct_costs_minor'] +=
                        $directCosts;

                    $buckets[
                        $bucketKey
                    ]['global_order_costs_minor'] +=
                        $globalOrderCosts;

                    $buckets[
                        $bucketKey
                    ]['profit_minor'] +=
                        $orderProfit;
                }

                if (
                    count($recentOrders)
                    < 8
                ) {
                    $customer = trim(
                        $order
                            ->get_formatted_billing_full_name()
                    );

                    if ($customer === '') {
                        $customer = 'مهمان';
                    }

                    $recentOrders[] =
                        array(
                            'id' =>
                                $order->get_id(),

                            'number' =>
                                $order
                                    ->get_order_number(),

                            'customer' =>
                                $customer,

                            'date' =>
                                $date
                            ? JalaliDate::format(
                                $date
                            )
                            . ' - '
                            . $date->format(
                                'H:i'
                            )
                                  : '—',

                            'status' =>
                                wc_get_order_status_name(
                                    $order->get_status()
                                ),

                            'revenue_minor' =>
                                $revenue,

                            'profit_minor' =>
                                $orderProfit,

                            'margin_percentage' =>
                                $profitResult
                                    ->marginPercentage(),

                            'complete' =>
                                $profitResult
                                    ->completeness()
                                    ->isComplete(),

                            'edit_url' =>
                                $order
                                    ->get_edit_order_url(),
                        );
                }
            }

            $page++;

        } while ($page <= $maxPages);

        $storeExpenses =
            $this->expenseRepository
                 ->sumBetween(
                     $start,
                     $end,
                     $currency,
                     $precision
                 );

        $expenseRows =
            $this->expenseRepository
                 ->totalsByDateBetween(
                     $start,
                     $end,
                     $currency
                 );

        foreach ($expenseRows as $expense) {
            if (
                empty(
                    $expense[
                        'expense_date'
                    ]
                )
            ) {
                continue;
            }

            $expenseDate =
                DateTimeImmutable
                ::createFromFormat(
                    '!Y-m-d',
                    $expense[
                        'expense_date'
                    ],
                    wp_timezone()
                );

            if (! $expenseDate) {
                continue;
            }

            $bucketKey =
                $this->bucketKey(
                    $expenseDate,
                    $bucketMode
                );

            if (
                ! isset(
                    $buckets[
                        $bucketKey
                    ]
                )
            ) {
                $buckets[
                    $bucketKey
                ] =
                    $this->newBucket(
                        $expenseDate,
                        $bucketMode
                    );
            }

            $expenseMinor =
                (int) $expense[
                    'amount_minor'
                ];

            $buckets[
                $bucketKey
            ]['store_expenses_minor'] +=
                $expenseMinor;

            $buckets[
                $bucketKey
            ]['profit_minor'] -=
                $expenseMinor;
        }

        ksort($buckets);

        $storeProfit =
            $this->profitEngine
                 ->calculateStore(
                     new Money(
                         $revenueMinor,
                         $currency,
                         $precision
                     ),
                     new Money(
                         $cogsMinor,
                         $currency,
                         $precision
                     ),
                     new Money(
                         $directCostsMinor,
                         $currency,
                         $precision
                     ),
                     new Money(
                         $globalCostsMinor,
                         $currency,
                         $precision
                     ),
                     $storeExpenses,
                     $incompleteCount
                 );

        $breakdown =
            $storeProfit
                ->breakdown();

        return array(
            'currency' =>
                $currency,

            'precision' =>
                $precision,

            'revenue_minor' =>
                $revenueMinor,

            'cogs_minor' =>
                $cogsMinor,

            'gross_profit_minor' =>
                $breakdown
                    ->revenue()
                    ->subtract(
                        $breakdown->cogs()
                    )
                    ->minorAmount(),

            'direct_costs_minor' =>
                $directCostsMinor,

            'global_order_costs_minor' =>
                $globalCostsMinor,

            'store_expenses_minor' =>
                $storeExpenses
                    ->minorAmount(),

            'order_profit_minor' =>
                $breakdown
                    ->profitBeforeStoreExpenses()
                    ->minorAmount(),

            'net_profit_minor' =>
                $storeProfit
                    ->profit()
                    ->minorAmount(),

            'profit_minor' =>
                $storeProfit
                    ->profit()
                    ->minorAmount(),

            'margin_percentage' =>
                $storeProfit
                    ->marginPercentage(),

            'order_count' =>
                $orderCount,

            'incomplete_count' =>
                $incompleteCount,

            'complete' =>
                $storeProfit
                    ->completeness()
                    ->isComplete(),

            'global_cost_per_order_minor' =>
                $globalCostPerOrder
                    ->minorAmount(),

            'daily' =>
                array_values($buckets),

            'recent_orders' =>
                $recentOrders,

            'bucket_mode' =>
                $bucketMode,
        );
    }

    public function getSummary(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        return $this->getDashboardData(
            $start,
            $end
        );
    }

    private function resolveBucketMode(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): string {
        $days = max(
            1,
            (int) $start
                ->diff($end)
                ->format('%a')
        );

        if ($days <= 45) {
            return 'day';
        }

        if ($days <= 220) {
            return 'week';
        }

        return 'month';
    }

    private function bucketKey(
        DateTimeInterface $date,
        string $mode
    ): string {
        if ($mode === 'month') {
            list(
                $year,
                $month
            ) = JalaliDate::parts($date);

            return sprintf(
                '%04d-%02d',
                $year,
                $month
            );
        }

        if ($mode === 'week') {
            return $this
                ->weekStart($date)
                ->format('Y-m-d');
        }

        return $date->format(
            'Y-m-d'
        );
    }

    private function newBucket(
        DateTimeInterface $date,
        string $mode
    ): array {
        if ($mode === 'month') {
            $label =
                JalaliDate::monthLabel(
                    $date
                );
        } elseif ($mode === 'week') {
            $label =
                JalaliDate::weekRangeLabel(
                    $this->weekStart(
                        $date
                    )
                );
        } else {
            $label =
                JalaliDate::shortNumeric(
                    $date
                );
        }

        return array(
            'key' =>
                $this->bucketKey(
                    $date,
                    $mode
                ),

            'timestamp' =>
                $date->getTimestamp(),

            'label' =>
                $label,

            'revenue_minor' => 0,
            'cogs_minor' => 0,
            'direct_costs_minor' => 0,
            'global_order_costs_minor' => 0,
            'store_expenses_minor' => 0,
            'profit_minor' => 0,
        );
    }

    private function weekStart(
        DateTimeInterface $date
    ): DateTimeImmutable {
        $copy = new DateTimeImmutable(
            '@' . $date->getTimestamp()
        );

        $copy = $copy->setTimezone(
            wp_timezone()
        );

        /*
         * PHP:
         * Sunday = 0
         * ...
         * Saturday = 6
         */
        $weekday =
            (int) $copy->format('w');

        $offset =
            ($weekday + 1) % 7;

        if ($offset > 0) {
            $copy = $copy->modify(
                '-' . $offset . ' days'
            );
        }

        return $copy->setTime(
            0,
            0,
            0
        );
    }
}
