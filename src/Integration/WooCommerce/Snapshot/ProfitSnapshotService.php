<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Snapshot;

use DateTimeImmutable;
use DateTimeInterface;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Integration\WooCommerce\Order\OrderFinancialData;
use Throwable;
use WC_Order;

final class ProfitSnapshotService
{
    private ProfitSnapshotRepository $repository;

    private OrderAdapter $orderAdapter;

    private GlobalOrderCostRepository $globalCosts;

    private ProfitEngine $profitEngine;

    private bool $capturing = false;

    public function __construct(
        ProfitSnapshotRepository $repository,
        OrderAdapter $orderAdapter,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine
    ) {
        $this->repository = $repository;
        $this->orderAdapter = $orderAdapter;
        $this->globalCosts = $globalCosts;
        $this->profitEngine = $profitEngine;
    }

    public function register(): void
    {
        add_action(
            'woocommerce_order_status_changed',
            array($this, 'captureOnStatusChange'),
            100,
            4
        );

        add_action(
            'woocommerce_order_refunded',
            array($this, 'captureOnRefund'),
            100,
            2
        );

        add_action(
            'hashieban_order_direct_costs_updated',
            array($this, 'captureOnDirectCosts'),
            100,
            1
        );
    }

    public function captureOnStatusChange(
        int $orderId,
        string $oldStatus,
        string $newStatus,
        $order = null
    ): void {
        if (
            ! in_array(
                $newStatus,
                array(
                    'processing',
                    'completed',
                    'refunded',
                ),
                true
            )
        ) {
            return;
        }

        $resolved =
            $order instanceof WC_Order
        ? $order
            : wc_get_order($orderId);

        if (! $resolved instanceof WC_Order) {
            return;
        }

        $this->capture(
            $resolved,
            'status:' . $newStatus,
            false
        );
    }

    public function captureOnRefund(
        int $orderId,
        int $refundId
    ): void {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return;
        }

        $this->capture(
            $order,
            'refund:' . $refundId,
            true
        );
    }

    public function captureOnDirectCosts(
        int $orderId
    ): void {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return;
        }

        if (
            ! in_array(
                $order->get_status(),
                array(
                    'processing',
                    'completed',
                    'refunded',
                ),
                true
            )
        ) {
            return;
        }

        $this->capture(
            $order,
            'direct-costs',
            true
        );
    }

    public function captureMissing(
        WC_Order $order,
        string $reason = 'backfill'
    ): array {
        if ($this->repository->has($order)) {
            return array(
                'status' => 'existing',
                'snapshot' =>
                    $this->repository
                         ->current($order),
            );
        }

        $snapshot =
            $this->capture(
                $order,
                $reason,
                false
            );

        return array(
            'status' =>
                $snapshot === null
            ? 'skipped'
                : 'created',
            'snapshot' => $snapshot,
        );
    }

    public function capture(
        WC_Order $order,
        string $reason,
        bool $allowRevision
    ): ?array {
        if ($this->capturing) {
            return null;
        }

        $current =
            $this->repository
                 ->current($order);

        if (
            $current !== null
            && ! $allowRevision
        ) {
            return $current;
        }

        $this->capturing = true;

        try {
            $financial =
                $this->orderAdapter
                     ->fromOrderLive($order);

            $currency =
                $financial->currency();

            $precision =
                wc_get_price_decimals();

            $globalCost =
                $this->globalCosts
                     ->total(
                         $currency,
                         $precision
                     );

            $profit =
                $this->profitEngine
                     ->calculateOrder(
                         $financial,
                         $globalCost
                     );

            $snapshot =
                $this->buildSnapshot(
                    $order,
                    $financial,
                    $globalCost->minorAmount(),
                    $profit->profit()->minorAmount(),
                    $profit->marginPercentage(),
                    $profit->completeness()->status(),
                    $profit->completeness()->missingData(),
                    $reason,
                    $current
                );

            if (
                $current !== null
                && (string) (
                    $current['fingerprint']
                    ?? ''
                ) === $snapshot['fingerprint']
            ) {
                return $current;
            }

            $this->globalCosts
                 ->snapshotForOrder(
                     $order,
                     $globalCost
                 );

            $this->repository
                 ->save(
                     $order,
                     $snapshot
                 );

            do_action(
                'hashieban_profit_snapshot_saved',
                $order->get_id(),
                $snapshot
            );

            return $snapshot;
        } catch (Throwable $exception) {
            return null;
        } finally {
            $this->capturing = false;
        }
    }

    private function buildSnapshot(
        WC_Order $order,
        OrderFinancialData $financial,
        int $globalOrderCostMinor,
        int $profitMinor,
        ?float $marginPercentage,
        string $completenessStatus,
        array $missingData,
        string $reason,
        ?array $current
    ): array {
        $financialData = array(
            'order_id' =>
                $financial->orderId(),
            'order_number' =>
                $financial->orderNumber(),
            'status' =>
                $financial->status(),
            'currency' =>
                $financial->currency(),
            'precision' =>
                wc_get_price_decimals(),
            'product_revenue_minor' =>
                $financial
                    ->productRevenue()
                    ->minorAmount(),
            'shipping_revenue_minor' =>
                $financial
                    ->shippingRevenue()
                    ->minorAmount(),
            'fee_revenue_minor' =>
                $financial
                    ->feeRevenue()
                    ->minorAmount(),
            'fee_discounts_minor' =>
                $financial
                    ->feeDiscounts()
                    ->minorAmount(),
            'refund_amount_minor' =>
                $financial
                    ->refundAmount()
                    ->minorAmount(),
            'refunded_tax_minor' =>
                $financial
                    ->refundedTax()
                    ->minorAmount(),
            'tax_charged_minor' =>
                $financial
                    ->taxCharged()
                    ->minorAmount(),
            'order_total_minor' =>
                $financial
                    ->orderTotal()
                    ->minorAmount(),
            'cogs_minor' =>
                $financial
                    ->cogs()
                    ->minorAmount(),
            'original_cogs_minor' =>
                $financial
                    ->originalCogs()
                    ->minorAmount(),
            'recovered_cogs_minor' =>
                $financial
                    ->recoveredCogs()
                    ->minorAmount(),
            'refunded_cogs_minor' =>
                $financial
                    ->refundedCogs()
                    ->minorAmount(),
            'unrecovered_refunded_cogs_minor' =>
                $financial
                    ->unrecoveredRefundedCogs()
                    ->minorAmount(),
            'unallocated_refund_minor' =>
                $financial
                    ->unallocatedRefund()
                    ->minorAmount(),
            'direct_costs_minor' =>
                $financial
                    ->directCosts()
                    ->minorAmount(),
            'refund_count' =>
                $financial->refundCount(),
            'refunded_quantity' =>
                $financial->refundedQuantity(),
            'restocked_quantity' =>
                $financial->restockedQuantity(),
            'refund_events' =>
                $this->normalizeValue(
                    $financial->refundEvents()
                ),
            'refund_items' =>
                $this->normalizeValue(
                    $financial->refundItems()
                ),
            'refund_warnings' =>
                $financial->refundWarnings(),
            'missing_data' =>
                $financial->missingData(),
            'global_order_cost_minor' =>
                $globalOrderCostMinor,
            'profit_minor' =>
                $profitMinor,
            'margin_percentage' =>
                $marginPercentage,
            'completeness_status' =>
                $completenessStatus,
            'completeness_missing_data' =>
                $missingData,
        );

        $fingerprintData = $financialData;

        unset(
            $fingerprintData['status'],
            $fingerprintData['order_number']
        );

        $encoded =
            wp_json_encode(
                $fingerprintData
            );

        $revision =
            $current === null
        ? 1
            : max(
                1,
                (int) (
                    $current['revision']
                    ?? 1
                )
            ) + 1;

        return array(
            'schema_version' => 1,
            'revision' => $revision,
            'calculation_version' =>
                defined('HASHIEBAN_VERSION')
            ? HASHIEBAN_VERSION
                : 'unknown',
            'captured_at_gmt' =>
                gmdate('c'),
            'reason' =>
                sanitize_key($reason),
            'order_status' =>
                sanitize_key(
                    (string) $order->get_status()
                ),
            'fingerprint' =>
                hash(
                    'sha256',
                    is_string($encoded)
                ? $encoded
                    : serialize(
                        $fingerprintData
                    )
                ),
            'financial' =>
                $financialData,
        );
    }

    private function normalizeValue(
        $value
    ) {
        if ($value instanceof DateTimeInterface) {
            return array(
                '__hashieban_datetime' =>
                    $value->format(
                        DateTimeInterface::ATOM
                    ),
            );
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = array();

        foreach ($value as $key => $item) {
            $normalized[$key] =
                $this->normalizeValue($item);
        }

        return $normalized;
    }
}
