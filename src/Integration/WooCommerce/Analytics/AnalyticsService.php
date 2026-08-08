<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use WC_Order;

final class AnalyticsService
{
    private OrderAdapter $orderAdapter;

    private StoreExpenseRepository $expenseRepository;

    public function __construct(
        OrderAdapter $orderAdapter,
        StoreExpenseRepository $expenseRepository
    ) {
        $this->orderAdapter = $orderAdapter;
        $this->expenseRepository = $expenseRepository;
    }

    public function getDashboardData(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $revenueMinor = 0;
        $cogsMinor = 0;
        $directCostsMinor = 0;
        $orderProfitMinor = 0;

        $orderCount = 0;
        $incompleteCount = 0;

        $recentOrders = array();
        $buckets = array();

        $bucketMode = $this->resolveBucketMode(
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
                    'currency' => $currency,
                    'limit' => 100,
                    'page' => $page,
                    'paginate' => true,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'date_created' =>
                        $start->format('Y-m-d H:i:s')
								  . '...'
								  . $end->format('Y-m-d H:i:s'),
                )
            );

            if (
                ! is_object($result)
                || ! isset($result->orders)
            ) {
                break;
            }

            $orders = $result->orders;

            $maxPages = isset($result->max_num_pages)
            ? max(
                1,
                (int) $result->max_num_pages
            )
					  : 1;

            foreach ($orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $financial = $this->orderAdapter
								  ->fromOrder($order);

                $revenue = $financial
                    ->revenueBeforeDirectCosts()
                    ->minorAmount();

                $cogs = $financial
                    ->cogs()
                    ->minorAmount();

                $directCosts = $financial
                    ->directCosts()
                    ->minorAmount();

                $grossProfit =
                    $revenue - $cogs;

                $orderProfit =
                    $grossProfit - $directCosts;

                $revenueMinor += $revenue;
                $cogsMinor += $cogs;
                $directCostsMinor += $directCosts;
                $orderProfitMinor += $orderProfit;

                $orderCount++;

                if (
                    $financial->hasMissingData()
                ) {
                    $incompleteCount++;
                }

                $date = $order->get_date_created();

                if ($date) {
                    $bucketKey = $this->bucketKey(
                        $date,
                        $bucketMode
                    );

                    if (
                        ! isset(
                            $buckets[$bucketKey]
                        )
                    ) {
                        $buckets[$bucketKey] =
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
                    ]['profit_minor'] +=
                        $orderProfit;

                    $buckets[
                        $bucketKey
                    ]['direct_costs_minor'] +=
                        $directCosts;
                }

                if (
                    count($recentOrders) < 8
                ) {
                    $customer = trim(
                        $order
                            ->get_formatted_billing_full_name()
                    );

                    if ($customer === '') {
                        $customer = 'مهمان';
                    }

                    $margin = null;

                    if ($revenue !== 0) {
                        $margin =
                            ($orderProfit / $revenue)
                        * 100;
                    }

                    $recentOrders[] = array(
                        'id' =>
                            $order->get_id(),

                        'number' =>
                            $order->get_order_number(),

                        'customer' =>
                            $customer,

                        'date' =>
                            $date
                        ? $date->format(
                            'Y/m/d H:i'
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
                            $margin,

                        'edit_url' =>
                            $order->get_edit_order_url(),
                    );
                }
            }

            $page++;

        } while ($page <= $maxPages);

        $storeExpensesMoney =
            $this->expenseRepository
                 ->sumBetween(
                     $start,
                     $end,
                     $currency,
                     $precision
                 );

        $storeExpensesMinor =
            $storeExpensesMoney
                ->minorAmount();

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
                    $expense['expense_date']
                )
            ) {
                continue;
            }

            $expenseDate =
                DateTimeImmutable::createFromFormat(
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
                    $buckets[$bucketKey]
                )
            ) {
                $buckets[$bucketKey] =
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

        $grossProfitMinor =
            $revenueMinor
            - $cogsMinor;

        $netProfitMinor =
            $orderProfitMinor
            - $storeExpensesMinor;

        $marginPercentage = null;

        if ($revenueMinor !== 0) {
            $marginPercentage =
                ($netProfitMinor / $revenueMinor)
            * 100;
        }

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
                $grossProfitMinor,

            'direct_costs_minor' =>
                $directCostsMinor,

            'store_expenses_minor' =>
                $storeExpensesMinor,

            'order_profit_minor' =>
                $orderProfitMinor,

            'net_profit_minor' =>
                $netProfitMinor,

            /*
             * Compatibility with the
             * current DashboardPage.
             */
            'profit_minor' =>
                $netProfitMinor,

            'margin_percentage' =>
                $marginPercentage,

            'order_count' =>
                $orderCount,

            'incomplete_count' =>
                $incompleteCount,

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

        if ($days <= 190) {
            return 'week';
        }

        return 'month';
    }

    private function bucketKey(
        DateTimeInterface $date,
        string $mode
    ): string {
        if ($mode === 'month') {
            return $date->format(
                'Y-m'
            );
        }

        if ($mode === 'week') {
            return $date->format(
                'o-W'
            );
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
            $label = $date->format(
                'Y/m'
            );
        } elseif ($mode === 'week') {
            $label =
                'هفته '
                . $date->format('W');
        } else {
            $label = $date->format(
                'm/d'
            );
        }

        return array(
            'key' =>
                $this->bucketKey(
                    $date,
                    $mode
                ),

            /*
             * DashboardPage currently
             * uses this value for dates.
             */
            'timestamp' =>
                $date->getTimestamp(),

            'label' =>
                $label,

            'revenue_minor' =>
                0,

            'profit_minor' =>
                0,

            'direct_costs_minor' =>
                0,

            'store_expenses_minor' =>
                0,
        );
    }
}
