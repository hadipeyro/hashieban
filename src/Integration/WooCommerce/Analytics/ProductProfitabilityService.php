<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Refund\RefundEngine;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class ProductProfitabilityService
{
    private MoneyFactory $moneyFactory;
    private RefundEngine $refundEngine;

    public function __construct(
        MoneyFactory $moneyFactory,
        RefundEngine $refundEngine
    ) {
        $this->moneyFactory = $moneyFactory;
        $this->refundEngine = $refundEngine;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $products = array();
        $totalRevenueMinor = 0;
        $totalCogsMinor = 0;
        $totalGrossUnits = 0;
        $totalNetUnits = 0;
        $totalRefundedUnits = 0;
        $totalRestockedUnits = 0;
        $totalRefundedRevenueMinor = 0;
        $totalRecoveredCogsMinor = 0;
        $unallocatedRefundMinor = 0;
        $ordersWithRefunds = 0;

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

            if (! is_object($result) || ! isset($result->orders)) {
                break;
            }

            $maxPages = isset($result->max_num_pages)
                ? max(1, (int) $result->max_num_pages)
                : 1;

            foreach ($result->orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $refund = $this->refundEngine->analyze($order);
                $refundItems = (array) $refund['items'];

                if (! empty($refund['has_refund'])) {
                    $ordersWithRefunds++;
                }

                $unallocatedRefundMinor += (int) $refund['unallocated_refund_minor'];

                foreach ($order->get_items('line_item') as $item) {
                    if (! $item instanceof WC_Order_Item_Product) {
                        continue;
                    }

                    $grossQuantity = max(0, (int) $item->get_quantity());

                    if ($grossQuantity === 0) {
                        continue;
                    }

                    $refundRow = $refundItems[(int) $item->get_id()] ?? array();
                    $refundedQuantity = max(0, (int) ($refundRow['refunded_quantity'] ?? 0));
                    $restockedQuantity = max(0, (int) ($refundRow['restocked_quantity'] ?? 0));
                    $netQuantity = max(0, $grossQuantity - $refundedQuantity);

                    $product = $item->get_product();

                    if (! $product instanceof WC_Product) {
                        $product = null;
                    }

                    $productId = (int) $item->get_product_id();
                    $variationId = (int) $item->get_variation_id();
                    $entityId = $variationId > 0 ? $variationId : $productId;
                    $key = $entityId > 0
                        ? 'id:' . $entityId
                        : 'name:' . md5((string) $item->get_name());

                    $grossLineRevenue = $this->moneyFactory
                        ->fromWooCommerceAmount(
                            $item->get_total(),
                            $currency,
                            $precision
                        )
                        ->minorAmount();

                    $refundedLineRevenue = max(
                        0,
                        (int) ($refundRow['refund_revenue_minor'] ?? 0)
                    );

                    $netLineRevenue = $grossLineRevenue - $refundedLineRevenue;

                    $originalLineCogs = $this->moneyFactory
                        ->fromWooCommerceAmount(
                            $item->get_cogs_value(),
                            $currency,
                            $precision
                        )
                        ->minorAmount();

                    $recoveredLineCogs = min(
                        max(0, $originalLineCogs),
                        max(0, (int) ($refundRow['recovered_cogs_minor'] ?? 0))
                    );

                    $effectiveLineCogs = max(
                        0,
                        $originalLineCogs - $recoveredLineCogs
                    );

                    $missingCogs = $this->isCogsMissing(
                        $item,
                        $product,
                        $originalLineCogs
                    );

                    if (! isset($products[$key])) {
                        $products[$key] = array(
                            'entity_id' => $entityId,
                            'product_id' => $productId,
                            'variation_id' => $variationId,
                            'name' => $this->resolveProductName($item, $product),
                            'sku' => $product instanceof WC_Product
                                ? (string) $product->get_sku()
                                : '',
                            'edit_url' => $this->resolveEditUrl($entityId, $productId),
                            'quantity' => 0,
                            'gross_quantity' => 0,
                            'refunded_quantity' => 0,
                            'restocked_quantity' => 0,
                            'order_ids' => array(),
                            'revenue_minor' => 0,
                            'gross_revenue_minor' => 0,
                            'refunded_revenue_minor' => 0,
                            'cogs_minor' => 0,
                            'original_cogs_minor' => 0,
                            'recovered_cogs_minor' => 0,
                            'profit_minor' => 0,
                            'missing_cogs_lines' => 0,
                            'line_count' => 0,
                        );
                    }

                    $products[$key]['quantity'] += $netQuantity;
                    $products[$key]['gross_quantity'] += $grossQuantity;
                    $products[$key]['refunded_quantity'] += $refundedQuantity;
                    $products[$key]['restocked_quantity'] += $restockedQuantity;
                    $products[$key]['order_ids'][(string) $order->get_id()] = true;
                    $products[$key]['revenue_minor'] += $netLineRevenue;
                    $products[$key]['gross_revenue_minor'] += $grossLineRevenue;
                    $products[$key]['refunded_revenue_minor'] += $refundedLineRevenue;
                    $products[$key]['cogs_minor'] += $effectiveLineCogs;
                    $products[$key]['original_cogs_minor'] += $originalLineCogs;
                    $products[$key]['recovered_cogs_minor'] += $recoveredLineCogs;
                    $products[$key]['line_count']++;

                    if ($missingCogs) {
                        $products[$key]['missing_cogs_lines']++;
                    }

                    $totalRevenueMinor += $netLineRevenue;
                    $totalCogsMinor += $effectiveLineCogs;
                    $totalGrossUnits += $grossQuantity;
                    $totalNetUnits += $netQuantity;
                    $totalRefundedUnits += $refundedQuantity;
                    $totalRestockedUnits += $restockedQuantity;
                    $totalRefundedRevenueMinor += $refundedLineRevenue;
                    $totalRecoveredCogsMinor += $recoveredLineCogs;
                }
            }

            $page++;
        } while ($page <= $maxPages);

        $totalProfitMinor = $totalRevenueMinor - $totalCogsMinor;
        $rows = array();
        $productsWithMissingCogs = 0;

        foreach ($products as $product) {
            $revenueMinor = (int) $product['revenue_minor'];
            $cogsMinor = (int) $product['cogs_minor'];
            $profitMinor = $revenueMinor - $cogsMinor;
            $quantity = (int) $product['quantity'];
            $grossQuantity = (int) $product['gross_quantity'];
            $refundedQuantity = (int) $product['refunded_quantity'];
            $missingLines = (int) $product['missing_cogs_lines'];

            if ($missingLines > 0) {
                $productsWithMissingCogs++;
            }

            $margin = $revenueMinor !== 0
                ? ($profitMinor / $revenueMinor) * 100
                : null;

            $salesShare = $totalRevenueMinor !== 0
                ? ($revenueMinor / $totalRevenueMinor) * 100
                : null;

            $profitShare = $totalProfitMinor !== 0
                ? ($profitMinor / $totalProfitMinor) * 100
                : null;

            $averageSellingPriceMinor = $quantity > 0
                ? (int) round($revenueMinor / $quantity)
                : 0;

            $returnRate = $grossQuantity > 0
                ? ($refundedQuantity / $grossQuantity) * 100
                : null;

            $rows[] = array(
                'entity_id' => (int) $product['entity_id'],
                'product_id' => (int) $product['product_id'],
                'variation_id' => (int) $product['variation_id'],
                'name' => (string) $product['name'],
                'sku' => (string) $product['sku'],
                'edit_url' => (string) $product['edit_url'],
                'quantity' => $quantity,
                'gross_quantity' => $grossQuantity,
                'refunded_quantity' => $refundedQuantity,
                'restocked_quantity' => (int) $product['restocked_quantity'],
                'return_rate_percentage' => $returnRate,
                'order_count' => count($product['order_ids']),
                'revenue_minor' => $revenueMinor,
                'gross_revenue_minor' => (int) $product['gross_revenue_minor'],
                'refunded_revenue_minor' => (int) $product['refunded_revenue_minor'],
                'cogs_minor' => $cogsMinor,
                'original_cogs_minor' => (int) $product['original_cogs_minor'],
                'recovered_cogs_minor' => (int) $product['recovered_cogs_minor'],
                'profit_minor' => $profitMinor,
                'margin_percentage' => $margin,
                'sales_share_percentage' => $salesShare,
                'profit_share_percentage' => $profitShare,
                'average_selling_price_minor' => $averageSellingPriceMinor,
                'missing_cogs_lines' => $missingLines,
                'line_count' => (int) $product['line_count'],
                'cogs_complete' => $missingLines === 0,
            );
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                return (int) $b['profit_minor'] <=> (int) $a['profit_minor'];
            }
        );

        $this->appendRanks($rows);

        $topByRevenue = $this->topRows($rows, 'revenue_minor', 10, true);
        $topByProfit = $this->topRows($rows, 'profit_minor', 10, true);
        $bottomByProfit = $this->topRows($rows, 'profit_minor', 5, false);
        $topByQuantity = $this->topRows($rows, 'quantity', 5, true);
        $topByReturns = $this->topRows($rows, 'refunded_quantity', 5, true);

        $weightedMargin = $totalRevenueMinor !== 0
            ? ($totalProfitMinor / $totalRevenueMinor) * 100
            : null;

        $overallReturnRate = $totalGrossUnits > 0
            ? ($totalRefundedUnits / $totalGrossUnits) * 100
            : null;

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'total_revenue_minor' => $totalRevenueMinor,
            'total_cogs_minor' => $totalCogsMinor,
            'total_profit_minor' => $totalProfitMinor,
            'weighted_margin_percentage' => $weightedMargin,
            'total_units' => $totalNetUnits,
            'gross_units' => $totalGrossUnits,
            'refunded_units' => $totalRefundedUnits,
            'restocked_units' => $totalRestockedUnits,
            'return_rate_percentage' => $overallReturnRate,
            'refunded_revenue_minor' => $totalRefundedRevenueMinor,
            'recovered_cogs_minor' => $totalRecoveredCogsMinor,
            'unallocated_refund_minor' => $unallocatedRefundMinor,
            'product_count' => count($rows),
            'products_with_missing_cogs' => $productsWithMissingCogs,
            'orders_with_refunds' => $ordersWithRefunds,
            'products' => $rows,
            'top_by_revenue' => $topByRevenue,
            'top_by_profit' => $topByProfit,
            'bottom_by_profit' => $bottomByProfit,
            'top_by_quantity' => $topByQuantity,
            'top_by_returns' => $topByReturns,
        );
    }

    private function isCogsMissing(
        WC_Order_Item_Product $item,
        ?WC_Product $product,
        int $lineCogsMinor
    ): bool {
        if ($lineCogsMinor > 0) {
            return false;
        }

        if (! $product instanceof WC_Product) {
            return true;
        }

        $productCogs = $product->get_cogs_value();

        return $productCogs === null || $productCogs === '';
    }

    private function resolveProductName(
        WC_Order_Item_Product $item,
        ?WC_Product $product
    ): string {
        if ($product instanceof WC_Product) {
            $name = trim((string) $product->get_name());

            if ($name !== '') {
                return $name;
            }
        }

        $name = trim((string) $item->get_name());

        return $name !== '' ? $name : 'محصول بدون نام';
    }

    private function resolveEditUrl(
        int $entityId,
        int $productId
    ): string {
        $editId = $entityId > 0 ? $entityId : $productId;

        if ($editId <= 0) {
            return '';
        }

        $url = get_edit_post_link($editId, 'raw');

        return is_string($url) ? $url : '';
    }

    private function appendRanks(
        array &$rows
    ): void {
        $profitSorted = $rows;
        $revenueSorted = $rows;
        $quantitySorted = $rows;

        usort($profitSorted, static function (array $a, array $b): int {
            return (int) $b['profit_minor'] <=> (int) $a['profit_minor'];
        });
        usort($revenueSorted, static function (array $a, array $b): int {
            return (int) $b['revenue_minor'] <=> (int) $a['revenue_minor'];
        });
        usort($quantitySorted, static function (array $a, array $b): int {
            return (int) $b['quantity'] <=> (int) $a['quantity'];
        });

        $profitRanks = $this->buildRankMap($profitSorted);
        $revenueRanks = $this->buildRankMap($revenueSorted);
        $quantityRanks = $this->buildRankMap($quantitySorted);

        foreach ($rows as &$row) {
            $key = $this->rowKey($row);
            $row['profit_rank'] = $profitRanks[$key] ?? 0;
            $row['revenue_rank'] = $revenueRanks[$key] ?? 0;
            $row['quantity_rank'] = $quantityRanks[$key] ?? 0;
        }
        unset($row);
    }

    private function buildRankMap(array $rows): array
    {
        $map = array();
        $rank = 1;

        foreach ($rows as $row) {
            $map[$this->rowKey($row)] = $rank;
            $rank++;
        }

        return $map;
    }

    private function rowKey(array $row): string
    {
        $entityId = (int) ($row['entity_id'] ?? 0);

        if ($entityId > 0) {
            return 'id:' . $entityId;
        }

        return 'name:' . md5((string) ($row['name'] ?? ''));
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

        return array_slice($rows, 0, $limit);
    }
}
