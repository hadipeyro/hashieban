<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;
use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;

final class OrderAdapter
{
    private MoneyFactory $moneyFactory;

    private DirectCostRepository $directCostRepository;

    public function __construct(
        MoneyFactory $moneyFactory,
        DirectCostRepository $directCostRepository
    ) {
        $this->moneyFactory = $moneyFactory;
        $this->directCostRepository =
            $directCostRepository;
    }

    public function fromOrderId(
        int $orderId
    ): OrderFinancialData {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            throw new RuntimeException(
                'WooCommerce order not found.'
            );
        }

        return $this->fromOrder($order);
    }

    public function fromOrder(
        WC_Order $order
    ): OrderFinancialData {
        $currency = $order->get_currency();
        $precision = wc_get_price_decimals();

        $productRevenue =
            $this->calculateProductRevenue(
                $order,
                $currency,
                $precision
            );

        $shippingRevenue =
            $this->moneyFactory
                 ->fromWooCommerceAmount(
                     $order->get_shipping_total(),
                     $currency,
                     $precision
                 );

        $feeRevenue =
            $this->calculatePositiveFees(
                $order,
                $currency,
                $precision
            );

        $refundAmount =
            $this->moneyFactory
                 ->fromWooCommerceAmount(
                     $order->get_total_refunded(),
                     $currency,
                     $precision
                 );

        list(
            $cogs,
            $missingData
        ) = $this->calculateCogs(
            $order,
            $currency,
            $precision
        );

        $directCosts =
            $this->directCostRepository
                 ->total($order);

        return new OrderFinancialData(
            $order->get_id(),
            $order->get_order_number(),
            $order->get_status(),
            $currency,
            $productRevenue,
            $shippingRevenue,
            $feeRevenue,
            $refundAmount,
            $cogs,
            $directCosts,
            $missingData
        );
    }

    private function calculateProductRevenue(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $total = Money::zero(
            $currency,
            $precision
        );

        foreach (
            $order->get_items('line_item')
            as $item
        ) {
            if (
                ! $item
                instanceof WC_Order_Item_Product
            ) {
                continue;
            }

            $lineTotal =
                $this->moneyFactory
                     ->fromWooCommerceAmount(
                         $item->get_total(),
                         $currency,
                         $precision
                     );

            $total = $total->add($lineTotal);
        }

        return $total;
    }

    private function calculatePositiveFees(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $total = Money::zero(
            $currency,
            $precision
        );

        foreach (
            $order->get_items('fee')
            as $fee
        ) {
            $feeTotal = wc_format_decimal(
                (string) $fee->get_total(),
                $precision
            );

            if ($feeTotal === '') {
                continue;
            }

            $money =
                $this->moneyFactory
                     ->fromWooCommerceAmount(
                         $feeTotal,
                         $currency,
                         $precision
                     );

            if (! $money->isPositive()) {
                continue;
            }

            $total = $total->add($money);
        }

        return $total;
    }

    private function calculateCogs(
        WC_Order $order,
        string $currency,
        int $precision
    ): array {
        $total = Money::zero(
            $currency,
            $precision
        );

        $missingData = array();

        foreach (
            $order->get_items('line_item')
            as $item
        ) {
            if (
                ! $item
                instanceof WC_Order_Item_Product
            ) {
                continue;
            }

            $cogsValue = $item->get_cogs_value();

            $cogs =
                $this->moneyFactory
                     ->fromWooCommerceAmount(
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

            $productCogs =
                $product->get_cogs_value();

            if (
                $productCogs === null
                || $productCogs === ''
            ) {
                $missingData[] = sprintf(
                    'COGS محصول «%s» ثبت نشده است.',
                    $item->get_name()
                );
            }
        }

        return array(
            $total,
            $missingData,
        );
    }
}
