<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use WC_Order;

final class CustomerProfitabilityService
{
    private OrderAdapter $orderAdapter;

    private GlobalOrderCostRepository $globalCosts;

    private ProfitEngine $profitEngine;

    public function __construct(
        OrderAdapter $orderAdapter,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine
    ) {
        $this->orderAdapter = $orderAdapter;
        $this->globalCosts = $globalCosts;
        $this->profitEngine = $profitEngine;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $globalCostPerOrder = $this->globalCosts->total(
            $currency,
            $precision
        );

        $customers = array();
        $totalRevenueMinor = 0;
        $totalProfitMinor = 0;
        $totalCogsMinor = 0;
        $totalDirectCostsMinor = 0;
        $totalGlobalOrderCostsMinor = 0;
        $totalOrders = 0;
        $ordersWithRefunds = 0;
        $incompleteOrders = 0;

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

            $maxPages = isset($result->max_num_pages)
                ? max(1, (int) $result->max_num_pages)
                : 1;

            foreach ($result->orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $financial = $this->orderAdapter->fromOrder($order);
                $profitResult = $this->profitEngine->calculateOrder(
                    $financial,
                    $globalCostPerOrder
                );
                $breakdown = $profitResult->breakdown();

                $revenueMinor = $breakdown
                    ->revenue()
                    ->minorAmount();

                $profitMinor = $profitResult
                    ->profit()
                    ->minorAmount();

                $cogsMinor = $breakdown
                    ->cogs()
                    ->minorAmount();

                $directCostsMinor = $breakdown
                    ->orderCosts()
                    ->minorAmount();

                $globalOrderCostsMinor = $breakdown
                    ->globalOrderCosts()
                    ->minorAmount();

                $identity = $this->resolveCustomerIdentity($order);
                $key = $identity['key'];

                if (! isset($customers[$key])) {
                    $customers[$key] = array(
                        'key' => $key,
                        'customer_id' => (int) $identity['customer_id'],
                        'registered' => (bool) $identity['registered'],
                        'name' => (string) $identity['name'],
                        'email' => (string) $identity['email'],
                        'phone' => (string) $identity['phone'],
                        'edit_url' => (string) $identity['edit_url'],
                        'order_count' => 0,
                        'order_ids' => array(),
                        'revenue_minor' => 0,
                        'profit_minor' => 0,
                        'cogs_minor' => 0,
                        'direct_costs_minor' => 0,
                        'global_order_costs_minor' => 0,
                        'incomplete_orders' => 0,
                        'refund_orders' => 0,
                        'last_order_timestamp' => 0,
                        'last_order_id' => 0,
                    );
                }

                $customers[$key]['order_count']++;
                $customers[$key]['order_ids'][(string) $order->get_id()] = true;
                $customers[$key]['revenue_minor'] += $revenueMinor;
                $customers[$key]['profit_minor'] += $profitMinor;
                $customers[$key]['cogs_minor'] += $cogsMinor;
                $customers[$key]['direct_costs_minor'] += $directCostsMinor;
                $customers[$key]['global_order_costs_minor'] += $globalOrderCostsMinor;

                if ($profitResult->completeness()->isIncomplete()) {
                    $customers[$key]['incomplete_orders']++;
                    $incompleteOrders++;
                }

                if ((float) $order->get_total_refunded() > 0) {
                    $customers[$key]['refund_orders']++;
                    $ordersWithRefunds++;
                }

                $date = $order->get_date_created();

                if ($date) {
                    $timestamp = $date->getTimestamp();

                    if ($timestamp > (int) $customers[$key]['last_order_timestamp']) {
                        $customers[$key]['last_order_timestamp'] = $timestamp;
                        $customers[$key]['last_order_id'] = (int) $order->get_id();
                    }
                }

                $totalRevenueMinor += $revenueMinor;
                $totalProfitMinor += $profitMinor;
                $totalCogsMinor += $cogsMinor;
                $totalDirectCostsMinor += $directCostsMinor;
                $totalGlobalOrderCostsMinor += $globalOrderCostsMinor;
                $totalOrders++;
            }

            $page++;
        } while ($page <= $maxPages);

        $rows = array();
        $registeredCustomers = 0;
        $guestCustomers = 0;
        $repeatCustomers = 0;
        $lossCustomers = 0;
        $lowMarginCustomers = 0;

        foreach ($customers as $customer) {
            $revenueMinor = (int) $customer['revenue_minor'];
            $profitMinor = (int) $customer['profit_minor'];
            $orderCount = max(1, (int) $customer['order_count']);

            $margin = $revenueMinor !== 0
                ? ($profitMinor / $revenueMinor) * 100
                : null;

            $salesShare = $totalRevenueMinor > 0
                ? ($revenueMinor / $totalRevenueMinor) * 100
                : null;

            $profitShare = $totalProfitMinor > 0
                ? ($profitMinor / $totalProfitMinor) * 100
                : null;

            $averageOrderValueMinor = (int) round(
                $revenueMinor / $orderCount
            );

            $status = $this->resolveFinancialStatus(
                $profitMinor,
                $margin
            );

            if ((bool) $customer['registered']) {
                $registeredCustomers++;
            } else {
                $guestCustomers++;
            }

            if ($orderCount >= 2) {
                $repeatCustomers++;
            }

            if ($profitMinor < 0) {
                $lossCustomers++;
            }

            if (
                $profitMinor >= 0
                && $margin !== null
                && $margin < 10.0
            ) {
                $lowMarginCustomers++;
            }

            $rows[] = array(
                'key' => (string) $customer['key'],
                'customer_id' => (int) $customer['customer_id'],
                'registered' => (bool) $customer['registered'],
                'name' => (string) $customer['name'],
                'email' => (string) $customer['email'],
                'phone' => (string) $customer['phone'],
                'edit_url' => (string) $customer['edit_url'],
                'order_count' => $orderCount,
                'revenue_minor' => $revenueMinor,
                'profit_minor' => $profitMinor,
                'cogs_minor' => (int) $customer['cogs_minor'],
                'direct_costs_minor' => (int) $customer['direct_costs_minor'],
                'global_order_costs_minor' => (int) $customer['global_order_costs_minor'],
                'average_order_value_minor' => $averageOrderValueMinor,
                'margin_percentage' => $margin,
                'sales_share_percentage' => $salesShare,
                'profit_share_percentage' => $profitShare,
                'incomplete_orders' => (int) $customer['incomplete_orders'],
                'refund_orders' => (int) $customer['refund_orders'],
                'last_order_timestamp' => (int) $customer['last_order_timestamp'],
                'last_order_id' => (int) $customer['last_order_id'],
                'repeat_customer' => $orderCount >= 2,
                'financial_status' => $status,
            );
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                return (int) $b['profit_minor']
                    <=> (int) $a['profit_minor'];
            }
        );

        $this->appendRanks($rows);

        $customerCount = count($rows);
        $averageOrderValueMinor = $totalOrders > 0
            ? (int) round($totalRevenueMinor / $totalOrders)
            : 0;

        $averageRevenuePerCustomerMinor = $customerCount > 0
            ? (int) round($totalRevenueMinor / $customerCount)
            : 0;

        $weightedMargin = $totalRevenueMinor !== 0
            ? ($totalProfitMinor / $totalRevenueMinor) * 100
            : null;

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'total_revenue_minor' => $totalRevenueMinor,
            'total_profit_minor' => $totalProfitMinor,
            'total_cogs_minor' => $totalCogsMinor,
            'total_direct_costs_minor' => $totalDirectCostsMinor,
            'total_global_order_costs_minor' => $totalGlobalOrderCostsMinor,
            'weighted_margin_percentage' => $weightedMargin,
            'total_orders' => $totalOrders,
            'customer_count' => $customerCount,
            'registered_customer_count' => $registeredCustomers,
            'guest_customer_count' => $guestCustomers,
            'repeat_customer_count' => $repeatCustomers,
            'loss_customer_count' => $lossCustomers,
            'low_margin_customer_count' => $lowMarginCustomers,
            'orders_with_refunds' => $ordersWithRefunds,
            'incomplete_orders' => $incompleteOrders,
            'average_order_value_minor' => $averageOrderValueMinor,
            'average_revenue_per_customer_minor' => $averageRevenuePerCustomerMinor,
            'customers' => $rows,
            'top_by_revenue' => $this->topRows($rows, 'revenue_minor', 10, true),
            'top_by_profit' => $this->topRows($rows, 'profit_minor', 10, true),
            'bottom_by_profit' => $this->topRows($rows, 'profit_minor', 5, false),
            'top_by_orders' => $this->topRows($rows, 'order_count', 5, true),
        );
    }

    private function resolveCustomerIdentity(
        WC_Order $order
    ): array {
        $customerId = (int) $order->get_customer_id();
        $email = trim((string) $order->get_billing_email());
        $phone = trim((string) $order->get_billing_phone());
        $name = trim((string) $order->get_formatted_billing_full_name());

        if ($name === '') {
            $name = trim((string) $order->get_billing_company());
        }

        if ($name === '') {
            $name = $email !== ''
                ? $email
                : 'مشتری مهمان';
        }

        if ($customerId > 0) {
            $editUrl = get_edit_user_link($customerId);

            return array(
                'key' => 'user:' . $customerId,
                'customer_id' => $customerId,
                'registered' => true,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'edit_url' => is_string($editUrl) ? $editUrl : '',
            );
        }

        if ($email !== '') {
            return array(
                'key' => 'guest-email:' . strtolower($email),
                'customer_id' => 0,
                'registered' => false,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'edit_url' => '',
            );
        }

        if ($phone !== '') {
            $normalizedPhone = preg_replace('/\D+/', '', $phone);

            if (! is_string($normalizedPhone) || $normalizedPhone === '') {
                $normalizedPhone = $phone;
            }

            return array(
                'key' => 'guest-phone:' . $normalizedPhone,
                'customer_id' => 0,
                'registered' => false,
                'name' => $name,
                'email' => '',
                'phone' => $phone,
                'edit_url' => '',
            );
        }

        return array(
            'key' => 'guest-order:' . (int) $order->get_id(),
            'customer_id' => 0,
            'registered' => false,
            'name' => $name,
            'email' => '',
            'phone' => '',
            'edit_url' => '',
        );
    }

    private function resolveFinancialStatus(
        int $profitMinor,
        ?float $margin
    ): string {
        if ($profitMinor < 0) {
            return 'loss';
        }

        if (
            $margin !== null
            && $margin < 10.0
        ) {
            return 'low_margin';
        }

        return 'healthy';
    }

    private function appendRanks(
        array &$rows
    ): void {
        $this->appendRankForField(
            $rows,
            'revenue_minor',
            'revenue_rank'
        );

        $this->appendRankForField(
            $rows,
            'profit_minor',
            'profit_rank'
        );

        $this->appendRankForField(
            $rows,
            'order_count',
            'orders_rank'
        );
    }

    private function appendRankForField(
        array &$rows,
        string $field,
        string $rankField
    ): void {
        $indices = array_keys($rows);

        usort(
            $indices,
            static function (int $left, int $right) use ($rows, $field): int {
                return ($rows[$right][$field] ?? 0)
                    <=> ($rows[$left][$field] ?? 0);
            }
        );

        $rank = 1;

        foreach ($indices as $index) {
            $rows[$index][$rankField] = $rank;
            $rank++;
        }
    }

    private function topRows(
        array $rows,
        string $field,
        int $limit,
        bool $descending
    ): array {
        usort(
            $rows,
            static function (array $a, array $b) use ($field, $descending): int {
                $left = $a[$field] ?? 0;
                $right = $b[$field] ?? 0;

                if ($left == $right) {
                    return 0;
                }

                if ($descending) {
                    return $left < $right ? 1 : -1;
                }

                return $left > $right ? 1 : -1;
            }
        );

        return array_slice(
            $rows,
            0,
            max(0, $limit)
        );
    }
}
