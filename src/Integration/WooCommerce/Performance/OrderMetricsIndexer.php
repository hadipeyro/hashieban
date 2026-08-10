<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Performance;

use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Snapshot\ProfitSnapshotRepository;
use WC_Order;

final class OrderMetricsIndexer
{
    private OrderMetricsRepository $repository;

    private ProfitSnapshotRepository $snapshots;

    private DirectCostRepository $directCosts;

    public function __construct(
        OrderMetricsRepository $repository,
        ProfitSnapshotRepository $snapshots,
        DirectCostRepository $directCosts
    ) {
        $this->repository = $repository;
        $this->snapshots = $snapshots;
        $this->directCosts = $directCosts;
    }

    public function register(): void
    {
        add_action(
            'hashieban_profit_snapshot_saved',
            array($this, 'indexSnapshot'),
            10,
            2
        );

        add_action(
            'woocommerce_order_status_changed',
            array($this, 'syncStatus'),
            120,
            4
        );

        add_action(
            'woocommerce_before_delete_order',
            array($this, 'deleteOrder'),
            10,
            1
        );
    }

    public function indexSnapshot(
        int $orderId,
        array $snapshot
    ): void {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return;
        }

        $this->indexOrder(
            $order,
            $snapshot
        );
    }

    public function syncStatus(
        int $orderId,
        string $oldStatus,
        string $newStatus,
        $order = null
    ): void {
        if (! $this->isEligibleStatus($newStatus)) {
            $this->repository
                 ->deleteOrder($orderId);
            return;
        }

        $resolved = $order instanceof WC_Order
            ? $order
            : wc_get_order($orderId);

        if (! $resolved instanceof WC_Order) {
            return;
        }

        $this->indexOrder($resolved);
    }

    public function deleteOrder(int $orderId): void
    {
        $this->repository
             ->deleteOrder($orderId);

        delete_transient(
            'hashieban_wp_dashboard_widget_v1'
        );
    }

    public function indexOrder(
        WC_Order $order,
        ?array $snapshot = null
    ): bool {
        if (! $this->isEligibleStatus($order->get_status())) {
            $this->repository
                 ->deleteOrder($order->get_id());
            return true;
        }

        $snapshot = $snapshot
            ?? $this->snapshots->current($order);

        if (! is_array($snapshot)) {
            return false;
        }

        $financial = isset($snapshot['financial'])
            && is_array($snapshot['financial'])
        ? $snapshot['financial']
            : array();

        if ($financial === array()) {
            return false;
        }

        $date = $order->get_date_created();

        if (! $date) {
            return false;
        }

        $revenueMinor =
            (int) ($financial['product_revenue_minor'] ?? 0)
            + (int) ($financial['shipping_revenue_minor'] ?? 0)
            + (int) ($financial['fee_revenue_minor'] ?? 0)
            - (int) ($financial['fee_discounts_minor'] ?? 0)
            - (int) ($financial['refund_amount_minor'] ?? 0);

        $margin = $financial['margin_percentage'] ?? null;
        $marginBps = is_numeric($margin)
            ? (int) round(((float) $margin) * 100)
            : null;

        $row = array(
            'order_id' => $order->get_id(),
            'order_date_local' => $date->format('Y-m-d H:i:s'),
            'status' => $order->get_status(),
            'currency' => (string) ($financial['currency'] ?? $order->get_currency()),
            'revenue_minor' => $revenueMinor,
            'cogs_minor' => (int) ($financial['cogs_minor'] ?? 0),
            'direct_costs_minor' => (int) ($financial['direct_costs_minor'] ?? 0),
            'global_order_costs_minor' => (int) ($financial['global_order_cost_minor'] ?? 0),
            'profit_minor' => (int) ($financial['profit_minor'] ?? ($snapshot['profit_minor'] ?? 0)),
            'incomplete' => (string) ($financial['completeness_status'] ?? '') !== 'complete',
            'margin_bps' => $marginBps,
            'snapshot_revision' => max(1, (int) ($snapshot['revision'] ?? 1)),
        );

        $indexed = $this->repository
            ->replaceOrder(
                $row,
                $this->directCosts
                     ->totalsByCategory($order)
            );

        if ($indexed) {
            delete_transient(
                'hashieban_wp_dashboard_widget_v1'
            );
        }

        return $indexed;
    }

    public function prepareRebuild(): void
    {
        $this->repository->clearAll();
    }

    public function finishRebuild(): void
    {
        $this->repository->markReady();
    }

    public function indexedCount(): int
    {
        return $this->repository
            ->countIndexed();
    }

    public function isReady(): bool
    {
        return $this->repository
            ->isReady();
    }

    private function isEligibleStatus(
        string $status
    ): bool {
        return in_array(
            $status,
            array(
                'processing',
                'completed',
                'refunded',
            ),
            true
        );
    }
}
