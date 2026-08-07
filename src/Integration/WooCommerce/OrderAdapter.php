<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce;

use Hashieban\Application\Order\OrderFinancialData;
use Hashieban\Domain\Money\Money;
use RuntimeException;
use WC_Order;
use WC_Order_Refund;

final class OrderAdapter
{
    private WooCommerceMoneyFactory $moneyFactory;

    public function __construct(
        WooCommerceMoneyFactory $moneyFactory
    ) {
        $this->moneyFactory = $moneyFactory;
    }

    public function get(int $orderId): OrderFinancialData
    {
        $order = wc_get_order($orderId);

        if (
            ! $order instanceof WC_Order
            || $order instanceof WC_Order_Refund
        ) {
            throw new RuntimeException(
                sprintf(
                    'WooCommerce order %d was not found.',
                    $orderId
                )
            );
        }

        $currency = $order->get_currency();
        $precision = wc_get_price_decimals();

        $productRevenue = $this->readProductRevenue(
            $order,
            $currency,
            $precision
        );

        $shippingRevenue = $this->moneyFactory->fromDecimal(
            $order->get_shipping_total(),
            $currency,
            $precision
        );

        $positiveFees = $this->readPositiveFees(
            $order,
            $currency,
            $precision
        );

        $refundedRevenue = $this->readRefundedRevenue(
            $order,
            $currency,
            $precision
        );

        $cogs = $this->moneyFactory->fromDecimal(
            $order->get_cogs_total_value(),
            $currency,
            $precision
        );

        return new OrderFinancialData(
            $order->get_id(),
            $order->get_status(),
            $currency,
            $precision,
            $productRevenue,
            $shippingRevenue,
            $positiveFees,
            $refundedRevenue,
            $cogs
        );
    }

    private function readProductRevenue(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $revenue = Money::zero(
            $currency,
            $precision
        );

        foreach ($order->get_items('line_item') as $item) {
            $revenue = $revenue->add(
                $this->moneyFactory->fromDecimal(
                    $item->get_total(),
                    $currency,
                    $precision
                )
            );
        }

        return $revenue;
    }

    private function readPositiveFees(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $fees = Money::zero(
            $currency,
            $precision
        );

        foreach ($order->get_items('fee') as $item) {
            $amount = $this->moneyFactory->fromDecimal(
                $item->get_total(),
                $currency,
                $precision
            );

            if (! $amount->isPositive()) {
                continue;
            }

            $fees = $fees->add($amount);
        }

        return $fees;
    }

    private function readRefundedRevenue(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $refundedTotal = $this->moneyFactory->fromDecimal(
            $order->get_total_refunded(),
            $currency,
            $precision
        );

        $refundedTax = $this->moneyFactory->fromDecimal(
            $order->get_total_tax_refunded(),
            $currency,
            $precision
        );

        return $refundedTotal->subtract(
            $refundedTax
        );
    }
}
