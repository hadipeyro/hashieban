<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use WC_Order;

final class AnalyticsService
{
    private const BATCH_SIZE = 100;

    private OrderAdapter $orderAdapter;

    public function __construct(
        OrderAdapter $orderAdapter
    ) {
        $this->orderAdapter = $orderAdapter;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $revenueMinor = 0;
        $cogsMinor = 0;
        $profitMinor = 0;

        $orderCount = 0;
        $incompleteCount = 0;

        $daily = [];
        $recentOrders = [];

        $page = 1;

        do {
            $result = wc_get_orders(
                [
                    'status' => [
                        'wc-processing',
                        'wc-completed',
                    ],
                    'currency' => $currency,
                    'date_created' => sprintf(
                        '%s...%s',
                        $start->format('Y-m-d'),
                        $end->format('Y-m-d')
                    ),
                    'limit' => self::BATCH_SIZE,
                    'paged' => $page,
                    'paginate' => true,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'return' => 'objects',
                ]
            );

            foreach ($result->orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $financial = $this->orderAdapter->fromOrder(
                    $order
                );

                $revenue = $financial
                    ->revenueBeforeDirectCosts()
                    ->minorAmount();

                $cogs = $financial
                    ->cogs()
                    ->minorAmount();

                $profit = $revenue - $cogs;

                $revenueMinor += $revenue;
                $cogsMinor += $cogs;
                $profitMinor += $profit;

                ++$orderCount;

                if ($financial->hasMissingData()) {
                    ++$incompleteCount;
                }

                $this->addDailyData(
                    $daily,
                    $order,
                    $revenue,
                    $profit
                );

                if (count($recentOrders) < 8) {
                    $recentOrders[] = $this->makeOrderRow(
                        $order,
                        $revenue,
                        $cogs,
                        $profit
                    );
                }
            }

            ++$page;

            $maxPages = (int) $result->max_num_pages;
        } while ($page <= $maxPages);

        $margin = null;

        if ($revenueMinor > 0) {
            $margin = (
                $profitMinor
                / $revenueMinor
            ) * 100;
        }

        ksort($daily);

        return [
            'currency' => $currency,
            'precision' => $precision,

            'revenue_minor' => $revenueMinor,
            'cogs_minor' => $cogsMinor,
            'profit_minor' => $profitMinor,

            'margin_percentage' => $margin,

            'order_count' => $orderCount,
            'incomplete_count' => $incompleteCount,

            'daily' => array_values($daily),
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $daily
     */
    private function addDailyData(
        array &$daily,
        WC_Order $order,
        int $revenue,
        int $profit
    ): void {
        $date = $order->get_date_created();

        if ($date === null) {
            return;
        }

        $key = $date->date('Y-m-d');

        if (! isset($daily[$key])) {
            $daily[$key] = [
                'date' => $key,
                'timestamp' => $date->getTimestamp(),
                'revenue_minor' => 0,
                'profit_minor' => 0,
                'orders' => 0,
            ];
        }

        $daily[$key]['revenue_minor'] += $revenue;
        $daily[$key]['profit_minor'] += $profit;
        ++$daily[$key]['orders'];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeOrderRow(
        WC_Order $order,
        int $revenue,
        int $cogs,
        int $profit
    ): array {
        $customerName = trim(
            $order->get_formatted_billing_full_name()
        );

        if ($customerName === '') {
            $customerName = 'مشتری مهمان';
        }

        $margin = null;

        if ($revenue > 0) {
            $margin = ($profit / $revenue) * 100;
        }

        $date = $order->get_date_created();

        return [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'customer' => $customerName,

            'date' => $date !== null
            ? $date->date_i18n('Y/m/d H:i')
                  : '—',

            'status' => wc_get_order_status_name(
                $order->get_status()
            ),

            'revenue_minor' => $revenue,
            'cogs_minor' => $cogs,
            'profit_minor' => $profit,
            'margin_percentage' => $margin,

            'edit_url' => $order->get_edit_order_url(),
        ];
    }
}
