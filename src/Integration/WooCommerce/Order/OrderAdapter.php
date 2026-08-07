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

    public function __construct(
        MoneyFactory $moneyFactory
    ) {
        $this->moneyFactory = $moneyFactory;
    }

    public function fromOrderId(
        int $orderId
    ): OrderFinancialData {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            throw new RuntimeException(
                'WooCommerce order could not be loaded.'
            );
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

        $shippingRevenue = $this->moneyFactory
								->fromWooCommerceAmount(
									$order->get_shipping_total(),
									$currency,
									$precision
								);

        $feeRevenue = $this->calculatePositiveFees(
            $order,
            $currency,
            $precision
        );

        $refundAmount = $this->moneyFactory
							 ->fromWooCommerceAmount(
								 $order->get_total_refunded(),
								 $currency,
								 $precision
							 );

        [$cogs, $missingData] = $this->calculateCogs(
            $order,
            $currency,
            $precision
        );

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

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $amount = $this->moneyFactory
						   ->fromWooCommerceAmount(
							   $item->get_total(),
							   $currency,
							   $precision
						   );

            $total = $total->add($amount);
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

        foreach ($order->get_fees() as $fee) {
            $feeValue = wc_format_decimal(
                (string) $fee->get_total(),
                $precision
            );

            if ((float) $feeValue <= 0) {
                continue;
            }

            $amount = $this->moneyFactory
						   ->fromWooCommerceAmount(
							   $feeValue,
							   $currency,
							   $precision
						   );

            $total = $total->add($amount);
        }

        return $total;
    }

    /**
     * @return array{0: Money, 1: string[]}
     */
    private function calculateCogs(
        WC_Order $order,
        string $currency,
        int $precision
    ): array {
        $total = Money::zero(
            $currency,
            $precision
        );

        $missingData = [];

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $cogsValue = $item->get_cogs_value();

            $cogs = $this->moneyFactory
						 ->fromWooCommerceAmount(
							 $cogsValue,
							 $currency,
							 $precision
						 );

            $total = $total->add($cogs);

            if ($cogsValue > 0) {
                continue;
            }

            $product = $item->get_product();

            if (! $product) {
                $missingData[] = sprintf(
                    'Missing product or COGS for order item %d.',
                    $item->get_id()
                );

                continue;
            }

            if ($product->get_cogs_value() === null) {
                $missingData[] = sprintf(
                    'Missing COGS for order item %d.',
                    $item->get_id()
                );
            }
        }

        return [
            $total,
            $missingData,
        ];
    }
}
