<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;
use Hashieban\Integration\WooCommerce\Refund\RefundEngine;
use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;

final class OrderAdapter
{
    private MoneyFactory $moneyFactory;
    private DirectCostRepository $directCostRepository;
    private RefundEngine $refundEngine;

    public function __construct(
        MoneyFactory $moneyFactory,
        DirectCostRepository $directCostRepository,
        RefundEngine $refundEngine
    ) {
        $this->moneyFactory = $moneyFactory;
        $this->directCostRepository = $directCostRepository;
        $this->refundEngine = $refundEngine;
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
                    'COGS محصول آیتم «%s» قابل بررسی نیست.',
                    $item->get_name()
                );
                continue;
            }

            $productCogs = $product->get_cogs_value();

            if ($productCogs === null || $productCogs === '') {
                $missingData[] = sprintf(
                    'COGS محصول «%s» ثبت نشده است.',
                    $item->get_name()
                );
            }
        }

        return array($total, $missingData);
    }
}
