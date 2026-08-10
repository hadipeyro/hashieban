<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Support\Currency;
use Throwable;
use WC_Order;
use WC_Order_Item_Product;

final class OrderProfitCenterService
{
    private OrderAdapter $orderAdapter;
    private GlobalOrderCostRepository $globalCosts;
    private ProfitEngine $profitEngine;
    private MoneyFactory $moneyFactory;
    private DirectCostRepository $directCosts;
    private ExpenseCategoryRepository $categories;

    public function __construct(
        OrderAdapter $orderAdapter,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine,
        MoneyFactory $moneyFactory,
        DirectCostRepository $directCosts,
        ExpenseCategoryRepository $categories
    ) {
        $this->orderAdapter = $orderAdapter;
        $this->globalCosts = $globalCosts;
        $this->profitEngine = $profitEngine;
        $this->moneyFactory = $moneyFactory;
        $this->directCosts = $directCosts;
        $this->categories = $categories;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $filters = array()
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $filters = $this->normalizeFilters(
            $filters,
            $currency,
            $precision
        );

        $statuses = array('processing', 'completed', 'refunded');

        if (
            $filters['status'] !== 'all'
            && in_array($filters['status'], $statuses, true)
        ) {
            $statuses = array($filters['status']);
        }

        $rows = array();
        $page = 1;
        $maxPages = 1;

        do {
            $result = wc_get_orders(
                array(
                    'status' => $statuses,
                    'currency' => $currency,
                    'limit' => 100,
                    'page' => $page,
                    'paginate' => true,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'date_created' => $start->format('Y-m-d H:i:s')
                        . '...'
                        . $end->format('Y-m-d H:i:s'),
                )
            );

            if (! is_object($result) || ! isset($result->orders)) {
                break;
            }

            $maxPages = isset($result->max_num_pages)
                ? max(1, (int) $result->max_num_pages)
                : 1;

            foreach ((array) $result->orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                try {
                    $financial = $this->orderAdapter->fromOrder($order);
                    $orderGlobalCost = $this->globalCosts->totalForOrder(
                        $order,
                        $currency,
                        $precision
                    );
                    $profitResult = $this->profitEngine->calculateOrder(
                        $financial,
                        $orderGlobalCost
                    );
                } catch (Throwable $exception) {
                    continue;
                }

                $breakdown = $profitResult->breakdown();
                $date = $order->get_date_created();

                if (! $date) {
                    continue;
                }

                $customerName = $this->customerName($order);
                $email = sanitize_email((string) $order->get_billing_email());
                $phone = sanitize_text_field((string) $order->get_billing_phone());

                $shippingCategoryTotals = $this->directCosts->totalsByCategory($order);
                $shippingCostMinor = isset($shippingCategoryTotals['hb_shipping'])
                    ? (int) $shippingCategoryTotals['hb_shipping']
                    : 0;

                $row = array(
                    'order_id' => $order->get_id(),
                    'order_number' => (string) $order->get_order_number(),
                    'status' => (string) $order->get_status(),
                    'status_label' => wc_get_order_status_name($order->get_status()),
                    'created_at' => (new DateTimeImmutable('@' . $date->getTimestamp()))
                        ->setTimezone(wp_timezone()),
                    'customer_id' => (int) $order->get_customer_id(),
                    'customer_name' => $customerName,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'item_count' => (int) $order->get_item_count(),
                    'revenue_minor' => $breakdown->revenue()->minorAmount(),
                    'cogs_minor' => $breakdown->cogs()->minorAmount(),
                    'original_cogs_minor' => $financial->originalCogs()->minorAmount(),
                    'recovered_cogs_minor' => $financial->recoveredCogs()->minorAmount(),
                    'refunded_cogs_minor' => $financial->refundedCogs()->minorAmount(),
                    'unrecovered_refunded_cogs_minor' => $financial->unrecoveredRefundedCogs()->minorAmount(),
                    'unallocated_refund_minor' => $financial->unallocatedRefund()->minorAmount(),
                    'refund_count' => $financial->refundCount(),
                    'refunded_quantity' => $financial->refundedQuantity(),
                    'restocked_quantity' => $financial->restockedQuantity(),
                    'refund_warnings' => $financial->refundWarnings(),
                    'direct_costs_minor' => $breakdown->orderCosts()->minorAmount(),
                    'global_order_costs_minor' => $breakdown->globalOrderCosts()->minorAmount(),
                    'profit_minor' => $profitResult->profit()->minorAmount(),
                    'margin_percentage' => $profitResult->marginPercentage(),
                    'refund_minor' => $financial->refundAmount()->minorAmount(),
                    'refunded_tax_minor' => $financial->refundedTax()->minorAmount(),
                    'tax_charged_minor' => $financial->taxCharged()->minorAmount(),
                    'net_tax_minor' => $financial->netTax()->minorAmount(),
                    'shipping_revenue_minor' => $financial->shippingRevenue()->minorAmount(),
                    'shipping_cost_minor' => $shippingCostMinor,
                    'fee_revenue_minor' => $financial->feeRevenue()->minorAmount(),
                    'fee_discount_minor' => $financial->feeDiscounts()->minorAmount(),
                    'net_fee_revenue_minor' => $financial->netFeeRevenue()->minorAmount(),
                    'order_total_minor' => $financial->orderTotal()->minorAmount(),
                    'has_missing_data' => ! $profitResult->completeness()->isComplete(),
                    'missing_data' => $profitResult->completeness()->missingData(),
                    'edit_url' => $this->orderEditUrl($order),
                );

                if (! $this->matchesFilters($row, $filters)) {
                    continue;
                }

                $rows[] = $row;
            }

            $page++;
        } while ($page <= $maxPages);

        $rows = $this->sortRows(
            $rows,
            $filters['sort']
        );

        return $this->buildReport(
            $rows,
            $currency,
            $precision,
            $filters
        );
    }

    public function getOrderDetail(
        int $orderId
    ): ?array {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return null;
        }

        try {
            $financial = $this->orderAdapter->fromOrder($order);
        } catch (Throwable $exception) {
            return null;
        }

        $currency = $order->get_currency();
        $precision = wc_get_price_decimals();
        $globalOrderCost = $this->globalCosts->totalForOrder(
            $order,
            $currency,
            $precision
        );

        $profitResult = $this->profitEngine->calculateOrder(
            $financial,
            $globalOrderCost
        );

        $breakdown = $profitResult->breakdown();
        $date = $order->get_date_created();

        $items = array();
        $refundItems = $financial->refundItems();

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            $productId = $product ? (int) $product->get_id() : 0;
            $parentId = $product && method_exists($product, 'get_parent_id')
                ? (int) $product->get_parent_id()
                : 0;

            $editId = $parentId > 0 ? $parentId : $productId;
            $productUrl = $editId > 0
                ? get_edit_post_link($editId, 'raw')
                : '';

            try {
                $revenue = $this->moneyFactory->fromWooCommerceAmount(
                    $item->get_total(),
                    $currency,
                    $precision
                );
            } catch (Throwable $exception) {
                continue;
            }

            $cogsMinor = 0;

            try {
                $cogsMinor = $this->moneyFactory->fromWooCommerceAmount(
                    $item->get_cogs_value(),
                    $currency,
                    $precision
                )->minorAmount();
            } catch (Throwable $exception) {
                $cogsMinor = 0;
            }

            $refundRow = $refundItems[(int) $item->get_id()] ?? array();
            $refundedRevenueMinor = max(0, (int) ($refundRow['refund_revenue_minor'] ?? 0));
            $recoveredCogsMinor = min(
                max(0, $cogsMinor),
                max(0, (int) ($refundRow['recovered_cogs_minor'] ?? 0))
            );
            $effectiveRevenueMinor = $revenue->minorAmount() - $refundedRevenueMinor;
            $effectiveCogsMinor = max(0, $cogsMinor - $recoveredCogsMinor);
            $grossQuantity = max(0, (int) $item->get_quantity());
            $refundedQuantity = max(0, (int) ($refundRow['refunded_quantity'] ?? 0));

            $items[] = array(
                'product_id' => $productId,
                'name' => (string) $item->get_name(),
                'sku' => $product ? (string) $product->get_sku() : '',
                'quantity' => max(0, $grossQuantity - $refundedQuantity),
                'gross_quantity' => $grossQuantity,
                'refunded_quantity' => $refundedQuantity,
                'restocked_quantity' => max(0, (int) ($refundRow['restocked_quantity'] ?? 0)),
                'gross_revenue_minor' => $revenue->minorAmount(),
                'refunded_revenue_minor' => $refundedRevenueMinor,
                'revenue_minor' => $effectiveRevenueMinor,
                'original_cogs_minor' => $cogsMinor,
                'recovered_cogs_minor' => $recoveredCogsMinor,
                'cogs_minor' => $effectiveCogsMinor,
                'profit_minor' => $effectiveRevenueMinor - $effectiveCogsMinor,
                'edit_url' => is_string($productUrl) ? $productUrl : '',
            );
        }

        $directCostRows = array();
        $shippingCostMinor = 0;

        foreach ($this->directCosts->getCosts($order) as $cost) {
            $categoryId = sanitize_key((string) ($cost['category_id'] ?? ''));

            try {
                $money = $this->moneyFactory->fromWooCommerceAmount(
                    (string) ($cost['amount'] ?? '0'),
                    $currency,
                    $precision
                );
            } catch (Throwable $exception) {
                continue;
            }

            if ($categoryId === 'hb_shipping') {
                $shippingCostMinor += $money->minorAmount();
            }

            $directCostRows[] = array(
                'title' => sanitize_text_field((string) ($cost['title'] ?? 'هزینه سفارش')),
                'note' => sanitize_textarea_field((string) ($cost['note'] ?? '')),
                'amount_minor' => $money->minorAmount(),
                'category_id' => $categoryId,
                'category_name' => $this->categories->name($categoryId),
                'category_color' => $this->categories->color($categoryId),
            );
        }

        $customerId = (int) $order->get_customer_id();

        return array(
            'order_id' => $order->get_id(),
            'order_number' => (string) $order->get_order_number(),
            'status' => (string) $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'created_at' => $date
                ? (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone(wp_timezone())
                : null,
            'currency' => $currency,
            'precision' => $precision,
            'customer_id' => $customerId,
            'customer_name' => $this->customerName($order),
            'customer_email' => sanitize_email((string) $order->get_billing_email()),
            'customer_phone' => sanitize_text_field((string) $order->get_billing_phone()),
            'customer_edit_url' => $customerId > 0
                ? admin_url('user-edit.php?user_id=' . $customerId)
                : '',
            'order_edit_url' => $this->orderEditUrl($order),
            'revenue_minor' => $breakdown->revenue()->minorAmount(),
            'cogs_minor' => $breakdown->cogs()->minorAmount(),
            'original_cogs_minor' => $financial->originalCogs()->minorAmount(),
            'recovered_cogs_minor' => $financial->recoveredCogs()->minorAmount(),
            'refunded_cogs_minor' => $financial->refundedCogs()->minorAmount(),
            'unrecovered_refunded_cogs_minor' => $financial->unrecoveredRefundedCogs()->minorAmount(),
            'unallocated_refund_minor' => $financial->unallocatedRefund()->minorAmount(),
            'refund_count' => $financial->refundCount(),
            'refunded_quantity' => $financial->refundedQuantity(),
            'restocked_quantity' => $financial->restockedQuantity(),
            'refund_events' => $financial->refundEvents(),
            'refund_items' => $financial->refundItems(),
            'refund_warnings' => $financial->refundWarnings(),
            'direct_costs_minor' => $breakdown->orderCosts()->minorAmount(),
            'global_order_costs_minor' => $breakdown->globalOrderCosts()->minorAmount(),
            'profit_minor' => $profitResult->profit()->minorAmount(),
            'margin_percentage' => $profitResult->marginPercentage(),
            'refund_minor' => $financial->refundAmount()->minorAmount(),
            'refunded_tax_minor' => $financial->refundedTax()->minorAmount(),
            'tax_charged_minor' => $financial->taxCharged()->minorAmount(),
            'net_tax_minor' => $financial->netTax()->minorAmount(),
            'order_total_minor' => $financial->orderTotal()->minorAmount(),
            'shipping_revenue_minor' => $financial->shippingRevenue()->minorAmount(),
            'shipping_cost_minor' => $shippingCostMinor,
            'shipping_contribution_minor' => $financial->shippingRevenue()->minorAmount() - $shippingCostMinor,
            'fee_revenue_minor' => $financial->feeRevenue()->minorAmount(),
            'fee_discount_minor' => $financial->feeDiscounts()->minorAmount(),
            'net_fee_revenue_minor' => $financial->netFeeRevenue()->minorAmount(),
            'product_revenue_minor' => $financial->productRevenue()->minorAmount(),
            'has_missing_data' => ! $profitResult->completeness()->isComplete(),
            'missing_data' => $profitResult->completeness()->missingData(),
            'items' => $items,
            'direct_cost_rows' => $directCostRows,
        );
    }

    private function buildReport(
        array $rows,
        string $currency,
        int $precision,
        array $filters
    ): array {
        $totalRevenue = 0;
        $totalProfit = 0;
        $totalCogs = 0;
        $totalOriginalCogs = 0;
        $totalRecoveredCogs = 0;
        $totalUnrecoveredRefundedCogs = 0;
        $totalUnallocatedRefund = 0;
        $totalRefundAmount = 0;
        $refundOrderCount = 0;
        $refundedQuantity = 0;
        $restockedQuantity = 0;
        $totalDirectCosts = 0;
        $totalGlobalCosts = 0;
        $totalShippingRevenue = 0;
        $totalShippingCost = 0;
        $totalFeeRevenue = 0;
        $totalFeeDiscounts = 0;
        $totalTaxCharged = 0;
        $totalRefundedTax = 0;
        $profitable = 0;
        $loss = 0;
        $breakEven = 0;
        $incomplete = 0;

        $marginBuckets = array(
            'زیان‌ده' => 0,
            '۰ تا ۱۰٪' => 0,
            '۱۰ تا ۲۰٪' => 0,
            '۲۰ تا ۳۰٪' => 0,
            '۳۰ تا ۴۰٪' => 0,
            '۴۰٪ و بیشتر' => 0,
        );

        foreach ($rows as $row) {
            $totalRevenue += (int) $row['revenue_minor'];
            $totalProfit += (int) $row['profit_minor'];
            $totalCogs += (int) $row['cogs_minor'];
            $totalOriginalCogs += (int) ($row['original_cogs_minor'] ?? $row['cogs_minor']);
            $totalRecoveredCogs += (int) ($row['recovered_cogs_minor'] ?? 0);
            $totalUnrecoveredRefundedCogs += (int) ($row['unrecovered_refunded_cogs_minor'] ?? 0);
            $totalUnallocatedRefund += (int) ($row['unallocated_refund_minor'] ?? 0);
            $totalRefundAmount += (int) ($row['refund_minor'] ?? 0);
            $refundedQuantity += (int) ($row['refunded_quantity'] ?? 0);
            $restockedQuantity += (int) ($row['restocked_quantity'] ?? 0);
            if ((int) ($row['refund_count'] ?? 0) > 0 || (int) ($row['refund_minor'] ?? 0) > 0) {
                $refundOrderCount++;
            }
            $totalDirectCosts += (int) $row['direct_costs_minor'];
            $totalGlobalCosts += (int) $row['global_order_costs_minor'];
            $totalShippingRevenue += (int) $row['shipping_revenue_minor'];
            $totalShippingCost += (int) $row['shipping_cost_minor'];
            $totalFeeRevenue += (int) $row['fee_revenue_minor'];
            $totalFeeDiscounts += (int) $row['fee_discount_minor'];
            $totalTaxCharged += (int) $row['tax_charged_minor'];
            $totalRefundedTax += (int) $row['refunded_tax_minor'];

            if (! empty($row['has_missing_data'])) {
                $incomplete++;
            }

            if ((int) $row['profit_minor'] > 0) {
                $profitable++;
            } elseif ((int) $row['profit_minor'] < 0) {
                $loss++;
            } else {
                $breakEven++;
            }

            $margin = $row['margin_percentage'];

            if ($margin === null || (float) $margin < 0) {
                $marginBuckets['زیان‌ده']++;
            } elseif ((float) $margin < 10) {
                $marginBuckets['۰ تا ۱۰٪']++;
            } elseif ((float) $margin < 20) {
                $marginBuckets['۱۰ تا ۲۰٪']++;
            } elseif ((float) $margin < 30) {
                $marginBuckets['۲۰ تا ۳۰٪']++;
            } elseif ((float) $margin < 40) {
                $marginBuckets['۳۰ تا ۴۰٪']++;
            } else {
                $marginBuckets['۴۰٪ و بیشتر']++;
            }
        }

        $weightedMargin = $totalRevenue !== 0
            ? ($totalProfit / $totalRevenue) * 100
            : null;

        $byProfit = $rows;

        usort(
            $byProfit,
            static function (array $a, array $b): int {
                return ((int) $b['profit_minor']) <=> ((int) $a['profit_minor']);
            }
        );

        $byRevenue = $rows;

        usort(
            $byRevenue,
            static function (array $a, array $b): int {
                return ((int) $b['revenue_minor']) <=> ((int) $a['revenue_minor']);
            }
        );

        $byMargin = array_values(
            array_filter(
                $rows,
                static function (array $row): bool {
                    return $row['margin_percentage'] !== null;
                }
            )
        );

        usort(
            $byMargin,
            static function (array $a, array $b): int {
                return ((float) $b['margin_percentage']) <=> ((float) $a['margin_percentage']);
            }
        );

        $chartRows = $this->sampleRowsForChart($rows, 120);

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'filters' => $filters,
            'orders' => $rows,
            'order_count' => count($rows),
            'total_revenue_minor' => $totalRevenue,
            'total_profit_minor' => $totalProfit,
            'total_cogs_minor' => $totalCogs,
            'total_original_cogs_minor' => $totalOriginalCogs,
            'total_recovered_cogs_minor' => $totalRecoveredCogs,
            'total_unrecovered_refunded_cogs_minor' => $totalUnrecoveredRefundedCogs,
            'total_unallocated_refund_minor' => $totalUnallocatedRefund,
            'total_refund_minor' => $totalRefundAmount,
            'refund_order_count' => $refundOrderCount,
            'refunded_quantity' => $refundedQuantity,
            'restocked_quantity' => $restockedQuantity,
            'total_direct_costs_minor' => $totalDirectCosts,
            'total_global_order_costs_minor' => $totalGlobalCosts,
            'total_shipping_revenue_minor' => $totalShippingRevenue,
            'total_shipping_cost_minor' => $totalShippingCost,
            'total_shipping_contribution_minor' => $totalShippingRevenue - $totalShippingCost,
            'total_fee_revenue_minor' => $totalFeeRevenue,
            'total_fee_discount_minor' => $totalFeeDiscounts,
            'total_net_fee_revenue_minor' => $totalFeeRevenue - $totalFeeDiscounts,
            'total_tax_charged_minor' => $totalTaxCharged,
            'total_refunded_tax_minor' => $totalRefundedTax,
            'total_net_tax_minor' => $totalTaxCharged - $totalRefundedTax,
            'weighted_margin_percentage' => $weightedMargin,
            'profitable_count' => $profitable,
            'loss_count' => $loss,
            'break_even_count' => $breakEven,
            'incomplete_count' => $incomplete,
            'best_order' => isset($byProfit[0]) ? $byProfit[0] : null,
            'worst_order' => $byProfit !== array() ? $byProfit[count($byProfit) - 1] : null,
            'largest_order' => isset($byRevenue[0]) ? $byRevenue[0] : null,
            'highest_margin_order' => isset($byMargin[0]) ? $byMargin[0] : null,
            'top_profit_orders' => array_slice($byProfit, 0, 8),
            'top_loss_orders' => array_slice(array_reverse($byProfit), 0, 8),
            'margin_buckets' => $marginBuckets,
            'chart_rows' => $chartRows,
            'chart_sampled' => count($rows) > count($chartRows),
        );
    }

    private function normalizeFilters(
        array $filters,
        string $currency,
        int $precision
    ): array {
        $status = sanitize_key((string) ($filters['status'] ?? 'all'));
        $profitability = sanitize_key((string) ($filters['profitability'] ?? 'all'));
        $sort = sanitize_key((string) ($filters['sort'] ?? 'date_desc'));
        $search = sanitize_text_field((string) ($filters['q'] ?? ''));

        if (! in_array($status, array('all', 'processing', 'completed', 'refunded'), true)) {
            $status = 'all';
        }

        if (! in_array($profitability, array('all', 'profit', 'loss', 'break_even', 'incomplete'), true)) {
            $profitability = 'all';
        }

        if (! in_array(
            $sort,
            array(
                'date_desc',
                'date_asc',
                'revenue_desc',
                'profit_desc',
                'profit_asc',
                'margin_desc',
                'margin_asc',
            ),
            true
        )) {
            $sort = 'date_desc';
        }

        return array(
            'q' => $search,
            'status' => $status,
            'profitability' => $profitability,
            'sort' => $sort,
            'min_amount_minor' => $this->displayInputToMinor(
                (string) ($filters['min_amount'] ?? ''),
                $currency,
                $precision
            ),
            'max_amount_minor' => $this->displayInputToMinor(
                (string) ($filters['max_amount'] ?? ''),
                $currency,
                $precision
            ),
            'min_amount' => sanitize_text_field((string) ($filters['min_amount'] ?? '')),
            'max_amount' => sanitize_text_field((string) ($filters['max_amount'] ?? '')),
        );
    }

    private function matchesFilters(
        array $row,
        array $filters
    ): bool {
        if ($filters['q'] !== '') {
            $needle = $this->lower(Currency::toEnglishDigits($filters['q']));
            $haystack = implode(
                ' ',
                array(
                    (string) $row['order_id'],
                    (string) $row['order_number'],
                    (string) $row['customer_name'],
                    (string) $row['customer_email'],
                    (string) $row['customer_phone'],
                )
            );

            $haystack = $this->lower(Currency::toEnglishDigits($haystack));

            if (strpos($haystack, $needle) === false) {
                return false;
            }
        }

        if (
            $filters['min_amount_minor'] !== null
            && (int) $row['revenue_minor'] < (int) $filters['min_amount_minor']
        ) {
            return false;
        }

        if (
            $filters['max_amount_minor'] !== null
            && (int) $row['revenue_minor'] > (int) $filters['max_amount_minor']
        ) {
            return false;
        }

        switch ($filters['profitability']) {
            case 'profit':
                return (int) $row['profit_minor'] > 0;

            case 'loss':
                return (int) $row['profit_minor'] < 0;

            case 'break_even':
                return (int) $row['profit_minor'] === 0;

            case 'incomplete':
                return ! empty($row['has_missing_data']);

            case 'all':
            default:
                return true;
        }
    }

    private function sortRows(
        array $rows,
        string $sort
    ): array {
        usort(
            $rows,
            static function (array $a, array $b) use ($sort): int {
                switch ($sort) {
                    case 'date_asc':
                        return $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp();

                    case 'revenue_desc':
                        return ((int) $b['revenue_minor']) <=> ((int) $a['revenue_minor']);

                    case 'profit_desc':
                        return ((int) $b['profit_minor']) <=> ((int) $a['profit_minor']);

                    case 'profit_asc':
                        return ((int) $a['profit_minor']) <=> ((int) $b['profit_minor']);

                    case 'margin_desc':
                        $left = $a['margin_percentage'];
                        $right = $b['margin_percentage'];

                        if ($left === null && $right === null) {
                            return 0;
                        }

                        if ($left === null) {
                            return 1;
                        }

                        if ($right === null) {
                            return -1;
                        }

                        return ((float) $right) <=> ((float) $left);

                    case 'margin_asc':
                        $left = $a['margin_percentage'];
                        $right = $b['margin_percentage'];

                        if ($left === null && $right === null) {
                            return 0;
                        }

                        if ($left === null) {
                            return 1;
                        }

                        if ($right === null) {
                            return -1;
                        }

                        return ((float) $left) <=> ((float) $right);

                    case 'date_desc':
                    default:
                        return $b['created_at']->getTimestamp() <=> $a['created_at']->getTimestamp();
                }
            }
        );

        return $rows;
    }

    private function sampleRowsForChart(
        array $rows,
        int $limit
    ): array {
        $count = count($rows);

        if ($count <= $limit) {
            return $rows;
        }

        $sample = array();
        $step = $count / $limit;

        for ($i = 0; $i < $limit; $i++) {
            $index = min(
                $count - 1,
                (int) floor($i * $step)
            );

            $sample[] = $rows[$index];
        }

        return $sample;
    }

    private function displayInputToMinor(
        string $input,
        string $currency,
        int $precision
    ): ?int {
        if (trim($input) === '') {
            return null;
        }

        $storeDecimal = Currency::displayInputToStoreDecimal(
            $input,
            $currency,
            $precision
        );

        if ($storeDecimal === '') {
            return null;
        }

        try {
            return $this->moneyFactory->fromWooCommerceAmount(
                $storeDecimal,
                $currency,
                $precision
            )->minorAmount();
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function customerName(
        WC_Order $order
    ): string {
        $name = trim(
            sanitize_text_field((string) $order->get_billing_first_name())
            . ' '
            . sanitize_text_field((string) $order->get_billing_last_name())
        );

        if ($name !== '') {
            return $name;
        }

        $email = sanitize_email((string) $order->get_billing_email());

        if ($email !== '') {
            return $email;
        }

        return 'مشتری مهمان';
    }

    private function orderEditUrl(
        WC_Order $order
    ): string {
        if (method_exists($order, 'get_edit_order_url')) {
            $url = $order->get_edit_order_url();

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return admin_url(
            'post.php?post=' . $order->get_id() . '&action=edit'
        );
    }

    private function lower(
        string $value
    ): string {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
