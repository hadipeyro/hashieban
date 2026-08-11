<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Refund\RefundEngine;
use Hashieban\Integration\WooCommerce\Snapshot\ProfitSnapshotRepository;
use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;

final class OrderAdapter
{
    private MoneyFactory $moneyFactory;
    private DirectCostRepository $directCostRepository;
    private RefundEngine $refundEngine;
    private ProfitSnapshotRepository $snapshots;

    public function __construct(
        MoneyFactory $moneyFactory,
        DirectCostRepository $directCostRepository,
        RefundEngine $refundEngine,
        ProfitSnapshotRepository $snapshots
    ) {
        $this->moneyFactory = $moneyFactory;
        $this->directCostRepository = $directCostRepository;
        $this->refundEngine = $refundEngine;
        $this->snapshots = $snapshots;
    }

    public function fromOrderId(
        int $orderId
    ): OrderFinancialData {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            throw new RuntimeException('WooCommerce order not found.');
        }

        return $this->fromOrder($order);
    }

    public function fromOrder(
        WC_Order $order
    ): OrderFinancialData {
        $snapshot = $this->snapshots->current($order);

        if ($snapshot !== null) {
            $financial = $this->financialFromSnapshot($snapshot);

            if ($financial !== null) {
                return $financial;
            }
        }

        return $this->fromOrderLive($order);
    }

    public function fromOrderLive(
        WC_Order $order
    ): OrderFinancialData {
        $currency = $order->get_currency();
        $precision = wc_get_price_decimals();

        $productRevenue = $this->calculateProductRevenue(
            $order,
            $currency,
            $precision
        );

        $shippingRevenue = $this->moneyFactory->fromWooCommerceAmount(
            $order->get_shipping_total(),
            $currency,
            $precision
        );

        list($feeRevenue, $feeDiscounts) = $this->calculateFees(
            $order,
            $currency,
            $precision
        );

        $refund = $this->refundEngine->analyze($order);

        $refundAmount = new Money(
            (int) $refund['refund_ex_tax_minor'],
            $currency,
            $precision
        );

        $refundedTax = new Money(
            (int) $refund['refunded_tax_minor'],
            $currency,
            $precision
        );

        $taxCharged = $this->moneyFactory->fromWooCommerceAmount(
            $order->get_total_tax(),
            $currency,
            $precision
        );

        $orderTotal = $this->moneyFactory->fromWooCommerceAmount(
            $order->get_total(),
            $currency,
            $precision
        );

        list($originalCogs, $missingData) = $this->calculateCogs(
            $order,
            $currency,
            $precision
        );

        $recoveredCogsMinor = min(
            $originalCogs->minorAmount(),
            max(0, (int) $refund['recovered_cogs_minor'])
        );

        $recoveredCogs = new Money(
            $recoveredCogsMinor,
            $currency,
            $precision
        );

        $effectiveCogs = $originalCogs->subtract($recoveredCogs);

        $refundedCogs = new Money(
            max(0, (int) $refund['refunded_cogs_minor']),
            $currency,
            $precision
        );

        $unrecoveredRefundedCogs = new Money(
            max(0, (int) $refund['unrecovered_cogs_minor']),
            $currency,
            $precision
        );

        $unallocatedRefund = new Money(
            max(0, (int) $refund['unallocated_refund_minor']),
            $currency,
            $precision
        );

        $directCosts = $this->directCostRepository->total($order);

        return new OrderFinancialData(
            $order->get_id(),
            $order->get_order_number(),
            $order->get_status(),
            $currency,
            $productRevenue,
            $shippingRevenue,
            $feeRevenue,
            $feeDiscounts,
            $refundAmount,
            $refundedTax,
            $taxCharged,
            $orderTotal,
            $effectiveCogs,
            $originalCogs,
            $recoveredCogs,
            $refundedCogs,
            $unrecoveredRefundedCogs,
            $unallocatedRefund,
            $directCosts,
            (int) $refund['refund_count'],
            (int) $refund['refunded_quantity'],
            (int) $refund['restocked_quantity'],
            (array) $refund['events'],
            (array) $refund['items'],
            (array) $refund['warnings'],
            $missingData
        );
    }

    private function calculateProductRevenue(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $total = Money::zero($currency, $precision);

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $lineTotal = $this->moneyFactory->fromWooCommerceAmount(
                $item->get_total(),
                $currency,
                $precision
            );

            $total = $total->add($lineTotal);
        }

        return $total;
    }

    /**
     * Positive fee charges increase attributable revenue; negative fees reduce it.
     */
    private function calculateFees(
        WC_Order $order,
        string $currency,
        int $precision
    ): array {
        $positive = Money::zero($currency, $precision);
        $discounts = Money::zero($currency, $precision);

        foreach ($order->get_items('fee') as $fee) {
            $feeTotal = wc_format_decimal(
                (string) $fee->get_total(),
                $precision
            );

            if ($feeTotal === '') {
                continue;
            }

            $money = $this->moneyFactory->fromWooCommerceAmount(
                $feeTotal,
                $currency,
                $precision
            );

            if ($money->isPositive()) {
                $positive = $positive->add($money);
                continue;
            }

            if ($money->isNegative()) {
                $discounts = $discounts->add($money->negate());
            }
        }

        return array($positive, $discounts);
    }

    /**
     * Read the original historical COGS stored on order lines. Returned stock is
     * released separately by RefundEngine so refunded-but-not-returned goods keep
     * their cost in profitability.
     */
    private function calculateCogs(
        WC_Order $order,
        string $currency,
        int $precision
    ): array {
        $total = Money::zero($currency, $precision);
        $missingData = array();

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $cogsValue = $item->get_cogs_value();

            $cogs = $this->moneyFactory->fromWooCommerceAmount(
                $cogsValue,
                $currency,
                $precision
            );

            $total = $total->add($cogs);

            if ($cogs->isPositive()) {
                continue;
            }

            $product = $item->get_product();

            if (! $product) {
                $missingData[] = sprintf(
                    'هزینه خرید آیتم «%s» قابل بررسی نیست.',
                    $item->get_name()
                );
                continue;
            }

            $productCogs = $product->get_cogs_value();

            if ($productCogs === null || $productCogs === '') {
                $missingData[] = sprintf(
                    'هزینه خرید محصول «%s» ثبت نشده است.',
                    $item->get_name()
                );
            }
        }

        return array($total, $missingData);
    }

    private function financialFromSnapshot(
        array $snapshot
    ): ?OrderFinancialData {
        $data = isset($snapshot['financial'])
            && is_array($snapshot['financial'])
        ? $snapshot['financial']
            : null;

        if ($data === null) {
            return null;
        }

        $currency = strtoupper(
            trim(
                (string) (
                    $data['currency']
                    ?? ''
                )
            )
        );

        $precision = (int) (
            $data['precision']
            ?? -1
        );

        if (
            $currency === ''
            || $precision < 0
            || $precision > 6
        ) {
            return null;
        }

        $money = static function (
            array $source,
            string $key
        ) use (
            $currency,
            $precision
        ): Money {
            return new Money(
                (int) (
                    $source[$key]
                    ?? 0
                ),
                $currency,
                $precision
            );
        };

        return new OrderFinancialData(
            (int) (
                $data['order_id']
                ?? 0
            ),
            (string) (
                $data['order_number']
                ?? ''
            ),
            (string) (
                $data['status']
                ?? ''
            ),
            $currency,
            $money(
                $data,
                'product_revenue_minor'
            ),
            $money(
                $data,
                'shipping_revenue_minor'
            ),
            $money(
                $data,
                'fee_revenue_minor'
            ),
            $money(
                $data,
                'fee_discounts_minor'
            ),
            $money(
                $data,
                'refund_amount_minor'
            ),
            $money(
                $data,
                'refunded_tax_minor'
            ),
            $money(
                $data,
                'tax_charged_minor'
            ),
            $money(
                $data,
                'order_total_minor'
            ),
            $money(
                $data,
                'cogs_minor'
            ),
            $money(
                $data,
                'original_cogs_minor'
            ),
            $money(
                $data,
                'recovered_cogs_minor'
            ),
            $money(
                $data,
                'refunded_cogs_minor'
            ),
            $money(
                $data,
                'unrecovered_refunded_cogs_minor'
            ),
            $money(
                $data,
                'unallocated_refund_minor'
            ),
            $money(
                $data,
                'direct_costs_minor'
            ),
            (int) (
                $data['refund_count']
                ?? 0
            ),
            (int) (
                $data['refunded_quantity']
                ?? 0
            ),
            (int) (
                $data['restocked_quantity']
                ?? 0
            ),
            $this->restoreSnapshotValue(
                isset($data['refund_events'])
                && is_array($data['refund_events'])
            ? $data['refund_events']
                : array()
            ),
            isset($data['refund_items'])
            && is_array($data['refund_items'])
            ? $data['refund_items']
                : array(),
            isset($data['refund_warnings'])
            && is_array($data['refund_warnings'])
            ? $data['refund_warnings']
                : array(),
            isset($data['missing_data'])
            && is_array($data['missing_data'])
            ? $data['missing_data']
                : array()
        );
    }

    private function restoreSnapshotValue(
        $value
    ) {
        if (
            is_array($value)
            && count($value) === 1
            && isset(
                $value['__hashieban_datetime']
            )
        ) {
            try {
                return new DateTimeImmutable(
                    (string) $value[
                        '__hashieban_datetime'
                    ]
                );
            } catch (\Throwable $exception) {
                return null;
            }
        }

        if (! is_array($value)) {
            return $value;
        }

        $restored = array();

        foreach ($value as $key => $item) {
            $restored[$key] =
                $this->restoreSnapshotValue(
                    $item
                );
        }

        return $restored;
    }

}
