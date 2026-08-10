<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use WC_Product;

final class InventoryPurchaseInsightService
{
    private ProductProfitabilityService $productProfitability;
    private MoneyFactory $moneyFactory;

    public function __construct(
        ProductProfitabilityService $productProfitability,
        MoneyFactory $moneyFactory
    ) {
        $this->productProfitability = $productProfitability;
        $this->moneyFactory = $moneyFactory;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $leadDays = 14,
        int $targetCoverDays = 30
    ): array {
        $leadDays = max(1, min(180, $leadDays));
        $targetCoverDays = max(7, min(365, $targetCoverDays));

        $salesReport = $this->productProfitability->getReport(
            $start,
            $end
        );

        $currency = (string) $salesReport['currency'];
        $precision = (int) $salesReport['precision'];

        $salesByProduct = array();

        foreach ((array) $salesReport['products'] as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);

            if ($entityId <= 0) {
                continue;
            }

            $salesByProduct[$entityId] = $row;
        }

        $periodDays = max(
            1,
            (int) floor(
                ($end->getTimestamp() - $start->getTimestamp()) / DAY_IN_SECONDS
            ) + 1
        );

        $rows = array();
        $page = 1;
        $maxPages = 1;

        $summary = array(
            'tracked_products' => 0,
            'untracked_products' => 0,
            'out_of_stock' => 0,
            'reorder_now' => 0,
            'healthy' => 0,
            'slow_or_dead' => 0,
            'missing_cogs' => 0,
            'inventory_value_minor' => 0,
            'dead_stock_value_minor' => 0,
            'suggested_purchase_value_minor' => 0,
            'suggested_purchase_units' => 0,
        );

        do {
            $result = wc_get_products(
                array(
                    'status' => array(
                        'publish',
                        'private',
                    ),
                    'limit' => 100,
                    'page' => $page,
                    'paginate' => true,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                )
            );

            if (! is_object($result) || ! isset($result->products)) {
                break;
            }

            $maxPages = isset($result->max_num_pages)
                ? max(1, (int) $result->max_num_pages)
                : 1;

            foreach ($result->products as $product) {
                if (! $product instanceof WC_Product) {
                    continue;
                }

                if (
                    $product->is_type('variable')
                    || $product->is_type('grouped')
                    || $product->is_type('external')
                ) {
                    continue;
                }

                $productId = (int) $product->get_id();

                if ($productId <= 0) {
                    continue;
                }

                $salesRow = $salesByProduct[$productId] ?? array();

                $soldUnits = max(
                    0,
                    (float) ($salesRow['quantity'] ?? 0)
                );

                $dailyVelocity = $soldUnits / $periodDays;

                $managesStock = $product->managing_stock();
                $stockQuantityRaw = $product->get_stock_quantity();
                $stockQuantity = $stockQuantityRaw === null
                    ? null
                    : (float) $stockQuantityRaw;

                $stockStatus = (string) $product->get_stock_status();

                $cogsRaw = $product->get_cogs_value();
                $cogsMissing = $cogsRaw === null || $cogsRaw === '';

                $unitCogsMinor = 0;

                if (! $cogsMissing) {
                    $unitCogsMinor = $this->moneyFactory
                        ->fromWooCommerceAmount(
                            $cogsRaw,
                            $currency,
                            $precision
                        )
                        ->minorAmount();
                }

                $safeStock = $stockQuantity === null
                    ? 0.0
                    : max(0.0, $stockQuantity);

                $inventoryValueMinor = (int) round(
                    $safeStock * $unitCogsMinor
                );

                $daysCover = null;

                if (
                    $managesStock
                    && $stockQuantity !== null
                    && $dailyVelocity > 0
                ) {
                    $daysCover = max(
                        0.0,
                        $stockQuantity / $dailyVelocity
                    );
                }

                $reorderPointUnits = $dailyVelocity > 0
                    ? (int) ceil($dailyVelocity * $leadDays)
                    : 0;

                $targetUnits = $dailyVelocity > 0
                    ? (int) ceil(
                        $dailyVelocity
                        * ($leadDays + $targetCoverDays)
                    )
                    : 0;

                $suggestedPurchaseUnits = 0;

                if (
                    $managesStock
                    && $stockQuantity !== null
                    && $dailyVelocity > 0
                    && (
                        $stockQuantity <= 0
                        || $stockQuantity <= $reorderPointUnits
                    )
                ) {
                    $suggestedPurchaseUnits = max(
                        0,
                        (int) ceil($targetUnits - $stockQuantity)
                    );
                }

                $suggestedPurchaseValueMinor =
                    $cogsMissing
                        ? 0
                        : (int) round(
                            $suggestedPurchaseUnits
                            * $unitCogsMinor
                        );

                $status = $this->resolveStatus(
                    $managesStock,
                    $stockQuantity,
                    $stockStatus,
                    $soldUnits,
                    $dailyVelocity,
                    $daysCover,
                    $reorderPointUnits,
                    $targetCoverDays
                );

                if ($managesStock && $stockQuantity !== null) {
                    $summary['tracked_products']++;
                } else {
                    $summary['untracked_products']++;
                }

                if ($cogsMissing) {
                    $summary['missing_cogs']++;
                }

                switch ($status) {
                    case 'out':
                        $summary['out_of_stock']++;
                        break;

                    case 'reorder':
                        $summary['reorder_now']++;
                        break;

                    case 'healthy':
                        $summary['healthy']++;
                        break;

                    case 'slow':
                        $summary['slow_or_dead']++;
                        break;
                }

                $summary['inventory_value_minor'] += $inventoryValueMinor;
                $summary['suggested_purchase_units'] += $suggestedPurchaseUnits;
                $summary['suggested_purchase_value_minor'] += $suggestedPurchaseValueMinor;

                if (
                    $status === 'slow'
                    && $inventoryValueMinor > 0
                ) {
                    $summary['dead_stock_value_minor'] += $inventoryValueMinor;
                }

                $rows[] = array(
                    'product_id' => $productId,
                    'name' => $this->productName($product),
                    'sku' => (string) $product->get_sku(),
                    'edit_url' => get_edit_post_link($productId, ''),
                    'manages_stock' => $managesStock,
                    'stock_quantity' => $stockQuantity,
                    'stock_status' => $stockStatus,
                    'sold_units' => $soldUnits,
                    'daily_velocity' => $dailyVelocity,
                    'days_cover' => $daysCover,
                    'reorder_point_units' => $reorderPointUnits,
                    'target_units' => $targetUnits,
                    'suggested_purchase_units' => $suggestedPurchaseUnits,
                    'unit_cogs_minor' => $unitCogsMinor,
                    'cogs_missing' => $cogsMissing,
                    'inventory_value_minor' => $inventoryValueMinor,
                    'suggested_purchase_value_minor' => $suggestedPurchaseValueMinor,
                    'revenue_minor' => (int) ($salesRow['revenue_minor'] ?? 0),
                    'profit_minor' => (int) ($salesRow['profit_minor'] ?? 0),
                    'margin_percentage' => $salesRow['margin_percentage'] ?? null,
                    'status' => $status,
                );
            }

            $page++;
        } while ($page <= $maxPages);

        usort(
            $rows,
            static function (array $a, array $b): int {
                $priority = array(
                    'out' => 0,
                    'reorder' => 1,
                    'healthy' => 2,
                    'slow' => 3,
                    'untracked' => 4,
                );

                $aPriority = $priority[(string) $a['status']] ?? 9;
                $bPriority = $priority[(string) $b['status']] ?? 9;

                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }

                return (float) $b['daily_velocity']
                    <=> (float) $a['daily_velocity'];
            }
        );

        $topReorder = $this->topRows(
            $rows,
            'suggested_purchase_units',
            10,
            static function (array $row): bool {
                return (int) $row['suggested_purchase_units'] > 0;
            }
        );

        $topCapital = $this->topRows(
            $rows,
            'inventory_value_minor',
            10,
            static function (array $row): bool {
                return (int) $row['inventory_value_minor'] > 0;
            }
        );

        $fastMovers = $this->topRows(
            $rows,
            'daily_velocity',
            10,
            static function (array $row): bool {
                return (float) $row['daily_velocity'] > 0;
            }
        );

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'period_days' => $periodDays,
            'lead_days' => $leadDays,
            'target_cover_days' => $targetCoverDays,
            'summary' => $summary,
            'products' => $rows,
            'top_reorder' => $topReorder,
            'top_capital' => $topCapital,
            'fast_movers' => $fastMovers,
        );
    }

    private function resolveStatus(
        bool $managesStock,
        ?float $stockQuantity,
        string $stockStatus,
        float $soldUnits,
        float $dailyVelocity,
        ?float $daysCover,
        int $reorderPointUnits,
        int $targetCoverDays
    ): string {
        if (! $managesStock || $stockQuantity === null) {
            return 'untracked';
        }

        if ($stockStatus === 'outofstock' || $stockQuantity <= 0) {
            return 'out';
        }

        if (
            $dailyVelocity > 0
            && $stockQuantity <= $reorderPointUnits
        ) {
            return 'reorder';
        }

        if (
            $soldUnits <= 0
            || (
                $daysCover !== null
                && $daysCover > max(60, $targetCoverDays * 2)
            )
        ) {
            return 'slow';
        }

        return 'healthy';
    }

    private function topRows(
        array $rows,
        string $key,
        int $limit,
        callable $filter
    ): array {
        $filtered = array_values(
            array_filter(
                $rows,
                $filter
            )
        );

        usort(
            $filtered,
            static function (array $a, array $b) use ($key): int {
                return (float) ($b[$key] ?? 0)
                    <=> (float) ($a[$key] ?? 0);
            }
        );

        return array_slice(
            $filtered,
            0,
            $limit
        );
    }

    private function productName(
        WC_Product $product
    ): string {
        $name = trim(
            wp_strip_all_tags(
                (string) $product->get_name()
            )
        );

        if ($name !== '') {
            return $name;
        }

        return sprintf(
            'محصول #%d',
            (int) $product->get_id()
        );
    }
}
