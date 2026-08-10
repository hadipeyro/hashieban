<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use Hashieban\Domain\Money\Money;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Integration\WooCommerce\Performance\OrderMetricsRepository;
use Hashieban\Support\JalaliDate;
use WC_Order;

final class AnalyticsService
{
    private OrderAdapter $orderAdapter;
    private StoreExpenseRepository $expenseRepository;
    private GlobalOrderCostRepository $globalCosts;
    private ProfitEngine $profitEngine;
    private DirectCostRepository $directCosts;
    private ExpenseCategoryRepository $categories;
    private OrderMetricsRepository $orderMetrics;

    public function __construct(
        OrderAdapter $orderAdapter,
        StoreExpenseRepository $expenseRepository,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine,
        DirectCostRepository $directCosts,
        ExpenseCategoryRepository $categories,
        OrderMetricsRepository $orderMetrics
    ) {
        $this->orderAdapter =
            $orderAdapter;

        $this->expenseRepository =
            $expenseRepository;

        $this->globalCosts =
            $globalCosts;

        $this->profitEngine =
            $profitEngine;

        $this->directCosts =
            $directCosts;

        $this->categories =
            $categories;

        $this->orderMetrics =
            $orderMetrics;
    }

    public function getDashboardData(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        if ($this->orderMetrics->isReady()) {
            $indexed = $this->getIndexedDashboardData(
                $start,
                $end
            );

            if ($indexed !== null) {
                return $indexed;
            }
        }

        return $this->getLegacyDashboardData(
            $start,
            $end
        );
    }

    private function getLegacyDashboardData(
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
        $categoryTotals = array();

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
                        'refunded',
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

                $orderGlobalCost =
                    $this->globalCosts
                         ->totalForOrder(
                             $order,
                             $currency,
                             $precision
                         );

                $profitResult =
                    $this->profitEngine
                         ->calculateOrder(
                             $financial,
                             $orderGlobalCost
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

                foreach (
                    $this->directCosts
                         ->totalsByCategory($order)
                    as $categoryId => $amountMinor
                ) {
                    $this->addCategoryAmount(
                        $categoryTotals,
                        (string) $categoryId,
                        (int) $amountMinor
                    );
                }

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

        $expenseCategoryRows =
            $this->expenseRepository
                 ->totalsByCategoryBetween(
                     $start,
                     $end,
                     $currency
                 );

        foreach (
            $expenseCategoryRows
            as $expenseCategory
        ) {
            $categoryId = sanitize_key(
                (string) (
                    $expenseCategory['category_id']
                    ?? ''
                )
            );

            if ($categoryId === '') {
                $categoryId =
                    $this->categories
                         ->fallbackId();
            }

            $this->addCategoryAmount(
                $categoryTotals,
                $categoryId,
                (int) (
                    $expenseCategory['amount_minor']
                    ?? 0
                )
            );
        }

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

        $categoryBreakdown = array();

        foreach (
            $categoryTotals
            as $categoryId => $amountMinor
        ) {
            if ((int) $amountMinor <= 0) {
                continue;
            }

            $category =
                $this->categories
                     ->find((string) $categoryId);

            $categoryBreakdown[] = array(
                'id' => (string) $categoryId,
                'name' => $category
                    ? (string) ($category['name'] ?? 'سایر')
                    : 'سایر',
                'color' => $category
                    ? (string) ($category['color'] ?? '#64748b')
                    : '#64748b',
                'amount_minor' =>
                    (int) $amountMinor,
            );
        }

        usort(
            $categoryBreakdown,
            static function (
                array $left,
                array $right
            ): int {
                return $right['amount_minor']
                    <=> $left['amount_minor'];
            }
        );

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

            'expense_category_breakdown' =>
                $categoryBreakdown,

            'performance_mode' =>
                'legacy',
        );
    }

    private function getIndexedDashboardData(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): ?array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $summary = $this->orderMetrics
            ->summaryBetween(
                $start,
                $end,
                $currency
            );

        if ($summary === array()) {
            return null;
        }

        $revenueMinor = (int) ($summary['revenue_minor'] ?? 0);
        $cogsMinor = (int) ($summary['cogs_minor'] ?? 0);
        $directCostsMinor = (int) ($summary['direct_costs_minor'] ?? 0);
        $globalCostsMinor = (int) ($summary['global_order_costs_minor'] ?? 0);
        $orderCount = (int) ($summary['order_count'] ?? 0);
        $incompleteCount = (int) ($summary['incomplete_count'] ?? 0);

        $globalCostPerOrder = $this->globalCosts
            ->total(
                $currency,
                $precision
            );

        $bucketMode = $this->resolveBucketMode(
            $start,
            $end
        );

        $buckets = array();

        foreach (
            $this->orderMetrics->dailyBetween(
                $start,
                $end,
                $currency
            ) as $row
        ) {
            $dayKey = (string) ($row['day_key'] ?? '');

            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dayKey,
                wp_timezone()
            );

            if (! $date) {
                continue;
            }

            $bucketKey = $this->bucketKey(
                $date,
                $bucketMode
            );

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = $this->newBucket(
                    $date,
                    $bucketMode
                );
            }

            $buckets[$bucketKey]['revenue_minor'] +=
                (int) ($row['revenue_minor'] ?? 0);
            $buckets[$bucketKey]['cogs_minor'] +=
                (int) ($row['cogs_minor'] ?? 0);
            $buckets[$bucketKey]['direct_costs_minor'] +=
                (int) ($row['direct_costs_minor'] ?? 0);
            $buckets[$bucketKey]['global_order_costs_minor'] +=
                (int) ($row['global_order_costs_minor'] ?? 0);
            $buckets[$bucketKey]['profit_minor'] +=
                (int) ($row['profit_minor'] ?? 0);
        }

        $categoryTotals = array();

        foreach (
            $this->orderMetrics->categoryTotalsBetween(
                $start,
                $end,
                $currency
            ) as $categoryRow
        ) {
            $this->addCategoryAmount(
                $categoryTotals,
                (string) ($categoryRow['category_id'] ?? ''),
                (int) ($categoryRow['amount_minor'] ?? 0)
            );
        }

        $storeExpenses = $this->expenseRepository
            ->sumBetween(
                $start,
                $end,
                $currency,
                $precision
            );

        $expenseRows = $this->expenseRepository
            ->totalsByDateBetween(
                $start,
                $end,
                $currency
            );

        $expenseCategoryRows = $this->expenseRepository
            ->totalsByCategoryBetween(
                $start,
                $end,
                $currency
            );

        foreach ($expenseCategoryRows as $expenseCategory) {
            $categoryId = sanitize_key(
                (string) ($expenseCategory['category_id'] ?? '')
            );

            if ($categoryId === '') {
                $categoryId = $this->categories
                    ->fallbackId();
            }

            $this->addCategoryAmount(
                $categoryTotals,
                $categoryId,
                (int) ($expenseCategory['amount_minor'] ?? 0)
            );
        }

        foreach ($expenseRows as $expense) {
            if (empty($expense['expense_date'])) {
                continue;
            }

            $expenseDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                (string) $expense['expense_date'],
                wp_timezone()
            );

            if (! $expenseDate) {
                continue;
            }

            $bucketKey = $this->bucketKey(
                $expenseDate,
                $bucketMode
            );

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = $this->newBucket(
                    $expenseDate,
                    $bucketMode
                );
            }

            $expenseMinor = (int) ($expense['amount_minor'] ?? 0);
            $buckets[$bucketKey]['store_expenses_minor'] += $expenseMinor;
            $buckets[$bucketKey]['profit_minor'] -= $expenseMinor;
        }

        ksort($buckets);

        $storeProfit = $this->profitEngine
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

        $breakdown = $storeProfit->breakdown();
        $categoryBreakdown = array();

        foreach ($categoryTotals as $categoryId => $amountMinor) {
            if ((int) $amountMinor <= 0) {
                continue;
            }

            $category = $this->categories
                ->find((string) $categoryId);

            $categoryBreakdown[] = array(
                'id' => (string) $categoryId,
                'name' => $category
                    ? (string) ($category['name'] ?? 'سایر')
                    : 'سایر',
                'color' => $category
                    ? (string) ($category['color'] ?? '#64748b')
                    : '#64748b',
                'amount_minor' => (int) $amountMinor,
            );
        }

        usort(
            $categoryBreakdown,
            static function (
                array $left,
                array $right
            ): int {
                return $right['amount_minor']
                    <=> $left['amount_minor'];
            }
        );

        $recentOrders = array();

        foreach (
            $this->orderMetrics->recentBetween(
                $start,
                $end,
                $currency,
                8
            ) as $metricRow
        ) {
            $order = wc_get_order(
                (int) ($metricRow['order_id'] ?? 0)
            );

            if (! $order instanceof WC_Order) {
                continue;
            }

            $customer = trim(
                $order->get_formatted_billing_full_name()
            );

            if ($customer === '') {
                $customer = 'مهمان';
            }

            $date = $order->get_date_created();
            $marginBps = $metricRow['margin_bps'] ?? null;

            $recentOrders[] = array(
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'customer' => $customer,
                'date' => $date
                    ? JalaliDate::format($date)
                        . ' - '
                        . $date->format('H:i')
                    : '—',
                'status' => wc_get_order_status_name(
                    $order->get_status()
                ),
                'revenue_minor' =>
                    (int) ($metricRow['revenue_minor'] ?? 0),
                'profit_minor' =>
                    (int) ($metricRow['profit_minor'] ?? 0),
                'margin_percentage' =>
                    $marginBps === null
                ? null
                    : ((int) $marginBps) / 100,
                'complete' =>
                    empty($metricRow['incomplete']),
                'edit_url' =>
                    $order->get_edit_order_url(),
            );
        }

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'revenue_minor' => $revenueMinor,
            'cogs_minor' => $cogsMinor,
            'gross_profit_minor' =>
                $breakdown->revenue()
                    ->subtract($breakdown->cogs())
                    ->minorAmount(),
            'direct_costs_minor' => $directCostsMinor,
            'global_order_costs_minor' => $globalCostsMinor,
            'store_expenses_minor' =>
                $storeExpenses->minorAmount(),
            'order_profit_minor' =>
                $breakdown->profitBeforeStoreExpenses()
                    ->minorAmount(),
            'net_profit_minor' =>
                $storeProfit->profit()->minorAmount(),
            'profit_minor' =>
                $storeProfit->profit()->minorAmount(),
            'margin_percentage' =>
                $storeProfit->marginPercentage(),
            'order_count' => $orderCount,
            'incomplete_count' => $incompleteCount,
            'complete' =>
                $storeProfit->completeness()->isComplete(),
            'global_cost_per_order_minor' =>
                $globalCostPerOrder->minorAmount(),
            'daily' => array_values($buckets),
            'recent_orders' => $recentOrders,
            'bucket_mode' => $bucketMode,
            'expense_category_breakdown' =>
                $categoryBreakdown,
            'performance_mode' => 'indexed',
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

    private function addCategoryAmount(
        array &$totals,
        string $categoryId,
        int $amountMinor
    ): void {
        if ($amountMinor <= 0) {
            return;
        }

        $categoryId = sanitize_key(
            $categoryId
        );

        if ($categoryId === '') {
            $categoryId =
                $this->categories
                     ->fallbackId();
        }

        if (! isset($totals[$categoryId])) {
            $totals[$categoryId] = 0;
        }

        $totals[$categoryId] +=
            $amountMinor;
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
