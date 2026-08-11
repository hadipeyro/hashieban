<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Performance;

use Hashieban\Integration\WooCommerce\Attribution\SalesChannelClassifier;
use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Snapshot\ProfitSnapshotRepository;
use WC_Order;
use WC_Order_Item_Coupon;

final class OrderMetricsIndexer
{
    private OrderMetricsRepository $repository;

    private ProfitSnapshotRepository $snapshots;

    private DirectCostRepository $directCosts;

    private SalesChannelClassifier $channels;

    private MoneyFactory $moneyFactory;

    public function __construct(
        OrderMetricsRepository $repository,
        ProfitSnapshotRepository $snapshots,
        DirectCostRepository $directCosts,
        SalesChannelClassifier $channels,
        MoneyFactory $moneyFactory
    ) {
        $this->repository = $repository;
        $this->snapshots = $snapshots;
        $this->directCosts = $directCosts;
        $this->channels = $channels;
        $this->moneyFactory = $moneyFactory;
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
            'woocommerce_update_order',
            array($this, 'syncOrderUpdate'),
            140,
            2
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

    public function syncOrderUpdate(
        int $orderId,
        $order = null
    ): void {
        $resolved = $order instanceof WC_Order
            ? $order
            : wc_get_order($orderId);

        if (! $resolved instanceof WC_Order) {
            return;
        }

        if (! $this->isEligibleStatus($resolved->get_status())) {
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

        $attribution = $this->resolveAttribution($order);
        $couponDiscounts = $this->resolveCouponDiscounts(
            $order,
            (string) ($financial['currency'] ?? $order->get_currency()),
            wc_get_price_decimals()
        );
        $discountMinor = $this->moneyFactory
            ->fromWooCommerceAmount(
                $order->get_total_discount(true),
                (string) ($financial['currency'] ?? $order->get_currency()),
                wc_get_price_decimals()
            )
            ->minorAmount();

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
            'channel_key' => (string) ($attribution['channel_key'] ?? 'unknown'),
            'channel_name' => (string) ($attribution['channel_name'] ?? 'بدون داده منبع'),
            'channel_group' => (string) ($attribution['channel_group'] ?? 'unknown'),
            'attribution_known' => ! empty($attribution['known']),
            'attribution_source_type' => (string) ($attribution['source_type'] ?? ''),
            'attribution_source' => (string) ($attribution['source'] ?? ''),
            'attribution_medium' => (string) ($attribution['medium'] ?? ''),
            'attribution_campaign' => (string) ($attribution['campaign'] ?? ''),
            'attribution_referrer_domain' => (string) ($attribution['referrer_domain'] ?? ''),
            'coupon_count' => count($couponDiscounts),
            'discount_minor' => max(0, $discountMinor),
        );

        $indexed = $this->repository
            ->replaceOrder(
                $row,
                $this->directCosts
                     ->totalsByCategory($order),
                $couponDiscounts
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

    private function resolveCouponDiscounts(
        WC_Order $order,
        string $currency,
        int $precision
    ): array {
        $discounts = array();

        foreach ($order->get_items('coupon') as $coupon) {
            if (! $coupon instanceof WC_Order_Item_Coupon) {
                continue;
            }

            $code = trim((string) $coupon->get_code());

            if ($code === '') {
                continue;
            }

            $minor = $this->moneyFactory
                ->fromWooCommerceAmount(
                    $coupon->get_discount(),
                    $currency,
                    $precision
                )
                ->minorAmount();

            if (! isset($discounts[$code])) {
                $discounts[$code] = 0;
            }

            $discounts[$code] += max(0, $minor);
        }

        return $discounts;
    }

    private function resolveAttribution(
        WC_Order $order
    ): array {
        $classification = $this->channels->classify(
            array(
                'source_type' => (string) $order->get_meta(
                    '_wc_order_attribution_source_type',
                    true
                ),
                'source' => (string) $order->get_meta(
                    '_wc_order_attribution_utm_source',
                    true
                ),
                'medium' => (string) $order->get_meta(
                    '_wc_order_attribution_utm_medium',
                    true
                ),
                'campaign' => (string) $order->get_meta(
                    '_wc_order_attribution_utm_campaign',
                    true
                ),
                'referrer' => (string) $order->get_meta(
                    '_wc_order_attribution_referrer',
                    true
                ),
            )
        );

        $filtered = apply_filters(
            'hashieban_sales_channel_classification',
            $classification,
            $order
        );

        if (! is_array($filtered)) {
            $filtered = $classification;
        }

        $key = sanitize_key(
            (string) ($filtered['channel_key'] ?? 'unknown')
        );

        if ($key === '') {
            $key = 'unknown';
        }

        return array(
            'channel_key' => $this->limit($key, 100),
            'channel_name' => $this->limit(
                sanitize_text_field(
                    (string) ($filtered['channel_name'] ?? 'بدون داده منبع')
                ),
                191
            ),
            'channel_group' => $this->limit(
                sanitize_key(
                    (string) ($filtered['channel_group'] ?? 'unknown')
                ),
                32
            ),
            'known' => ! empty($filtered['known']),
            'source_type' => $this->limit(
                sanitize_key(
                    (string) ($filtered['source_type'] ?? '')
                ),
                64
            ),
            'source' => $this->limit(
                sanitize_text_field(
                    (string) ($filtered['source'] ?? '')
                ),
                191
            ),
            'medium' => $this->limit(
                sanitize_text_field(
                    (string) ($filtered['medium'] ?? '')
                ),
                191
            ),
            'campaign' => $this->limit(
                sanitize_text_field(
                    (string) ($filtered['campaign'] ?? '')
                ),
                191
            ),
            'referrer_domain' => $this->limit(
                sanitize_text_field(
                    (string) ($filtered['referrer_domain'] ?? '')
                ),
                191
            ),
        );
    }

    private function limit(
        string $value,
        int $length
    ): string {
        $value = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
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
