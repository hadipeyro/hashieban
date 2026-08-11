<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Refund;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Throwable;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Refund;

final class RefundEngine
{
    private MoneyFactory $moneyFactory;

    public function __construct(
        MoneyFactory $moneyFactory
    ) {
        $this->moneyFactory = $moneyFactory;
    }

    public function analyze(
        WC_Order $order
    ): array {
        $currency = (string) $order->get_currency();
        $precision = wc_get_price_decimals();

        $grossRefundMinor = $this->minor(
            $order->get_total_refunded(),
            $currency,
            $precision
        );

        $refundedTaxMinor = $this->minor(
            $order->get_total_tax_refunded(),
            $currency,
            $precision
        );

        $refundExTaxMinor = max(
            0,
            $grossRefundMinor - $refundedTaxMinor
        );

        $shippingRefundMinor = $this->minor(
            $order->get_total_shipping_refunded(),
            $currency,
            $precision
        );

        $productRefundMinor = 0;
        $feeRefundMinor = 0;
        $refundedCogsMinor = 0;
        $recoveredCogsMinor = 0;
        $refundedQuantity = 0;
        $restockedQuantity = 0;
        $itemRows = array();
        $warnings = array();

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId = (int) $item->get_id();
            $orderedQuantity = max(0, (int) $item->get_quantity());
            $itemRefundedQuantity = abs(
                (int) $order->get_qty_refunded_for_item($itemId)
            );

            $itemRefundMinor = $this->minor(
                $order->get_total_refunded_for_item($itemId),
                $currency,
                $precision
            );

            $productRefundMinor += $itemRefundMinor;
            $refundedQuantity += $itemRefundedQuantity;

            $restocked = max(
                0,
                (int) $item->get_meta('_restock_refunded_items', true)
            );

            $restocked = min(
                $restocked,
                $orderedQuantity,
                $itemRefundedQuantity
            );

            $restockedQuantity += $restocked;

            $originalCogsMinor = $this->minor(
                $item->get_cogs_value(),
                $currency,
                $precision
            );

            $itemRefundedCogsMinor = 0;

            if (method_exists($order, 'get_cogs_refunded_for_item')) {
                try {
                    $itemRefundedCogsMinor = $this->minor(
                        $order->get_cogs_refunded_for_item($itemId),
                        $currency,
                        $precision
                    );
                } catch (Throwable $exception) {
                    $itemRefundedCogsMinor = 0;
                }
            }

            if (
                $itemRefundedCogsMinor === 0
                && $orderedQuantity > 0
                && $itemRefundedQuantity > 0
                && $originalCogsMinor > 0
            ) {
                $itemRefundedCogsMinor = (int) round(
                    $originalCogsMinor
                    * min($itemRefundedQuantity, $orderedQuantity)
                    / $orderedQuantity
                );
            }

            $itemRecoveredCogsMinor = 0;

            if (
                $orderedQuantity > 0
                && $restocked > 0
                && $originalCogsMinor > 0
            ) {
                $itemRecoveredCogsMinor = (int) round(
                    $originalCogsMinor
                    * $restocked
                    / $orderedQuantity
                );
            }

            $itemRecoveredCogsMinor = min(
                $itemRecoveredCogsMinor,
                $originalCogsMinor
            );

            $refundedCogsMinor += $itemRefundedCogsMinor;
            $recoveredCogsMinor += $itemRecoveredCogsMinor;

            if (
                $itemRefundMinor > 0
                && $itemRefundedQuantity === 0
            ) {
                $warnings[] = sprintf(
                    'برای «%s» مبلغ بازگشت وجه ثبت شده اما تعداد کالای مرجوعی مشخص نیست؛ هزینه خرید این ردیف آزاد نشده است.',
                    $item->get_name()
                );
            }

            if (
                $itemRefundedQuantity > 0
                && $restocked < $itemRefundedQuantity
            ) {
                $warnings[] = sprintf(
                    'از %1$d عدد مرجوع‌شده «%2$s»، فقط %3$d عدد به موجودی برگشته است؛ هزینه خرید کالای برنگشته همچنان در محاسبات باقی می‌ماند.',
                    $itemRefundedQuantity,
                    $item->get_name(),
                    $restocked
                );
            }

            if (
                $itemRefundMinor === 0
                && $itemRefundedQuantity === 0
                && $restocked === 0
            ) {
                continue;
            }

            $itemRows[$itemId] = array(
                'item_id' => $itemId,
                'product_id' => (int) $item->get_product_id(),
                'variation_id' => (int) $item->get_variation_id(),
                'name' => (string) $item->get_name(),
                'ordered_quantity' => $orderedQuantity,
                'refunded_quantity' => $itemRefundedQuantity,
                'restocked_quantity' => $restocked,
                'refund_revenue_minor' => $itemRefundMinor,
                'refunded_cogs_minor' => $itemRefundedCogsMinor,
                'recovered_cogs_minor' => $itemRecoveredCogsMinor,
                'unrecovered_cogs_minor' => max(
                    0,
                    $itemRefundedCogsMinor - $itemRecoveredCogsMinor
                ),
            );
        }

        foreach ($order->get_items('fee') as $fee) {
            if (! $fee instanceof WC_Order_Item_Fee) {
                continue;
            }

            $feeRefundMinor += $this->minor(
                $order->get_total_refunded_for_item(
                    (int) $fee->get_id(),
                    'fee'
                ),
                $currency,
                $precision
            );
        }

        $allocatedRefundMinor =
            $productRefundMinor
            + $shippingRefundMinor
            + $feeRefundMinor;

        $unallocatedRefundMinor = max(
            0,
            $refundExTaxMinor - $allocatedRefundMinor
        );

        if ($unallocatedRefundMinor > 1) {
            $warnings[] = 'بخشی از بازگشت وجه فقط به‌صورت مبلغ کلی ثبت شده و به محصول، ارسال یا هزینه جانبی مشخصی قابل تخصیص نیست.';
        }

        $refundEvents = $this->refundEvents(
            $order,
            $currency,
            $precision
        );

        return array(
            'refund_count' => count($refundEvents),
            'gross_refund_minor' => $grossRefundMinor,
            'refund_ex_tax_minor' => $refundExTaxMinor,
            'refunded_tax_minor' => $refundedTaxMinor,
            'product_refund_minor' => $productRefundMinor,
            'shipping_refund_minor' => $shippingRefundMinor,
            'fee_refund_minor' => $feeRefundMinor,
            'allocated_refund_minor' => $allocatedRefundMinor,
            'unallocated_refund_minor' => $unallocatedRefundMinor,
            'refunded_cogs_minor' => $refundedCogsMinor,
            'recovered_cogs_minor' => $recoveredCogsMinor,
            'unrecovered_cogs_minor' => max(
                0,
                $refundedCogsMinor - $recoveredCogsMinor
            ),
            'refunded_quantity' => $refundedQuantity,
            'restocked_quantity' => $restockedQuantity,
            'has_refund' => $grossRefundMinor > 0 || $refundEvents !== array(),
            'has_unallocated_refund' => $unallocatedRefundMinor > 1,
            'items' => $itemRows,
            'events' => $refundEvents,
            'warnings' => array_values(array_unique($warnings)),
        );
    }

    private function refundEvents(
        WC_Order $order,
        string $currency,
        int $precision
    ): array {
        $rows = array();

        foreach ($order->get_refunds() as $refund) {
            if (! $refund instanceof WC_Order_Refund) {
                continue;
            }

            $date = $refund->get_date_created();
            $grossMinor = $this->minor(
                $refund->get_total(),
                $currency,
                $precision
            );
            $taxMinor = $this->minor(
                $refund->get_total_tax(),
                $currency,
                $precision
            );

            $rows[] = array(
                'refund_id' => (int) $refund->get_id(),
                'date' => $date
                    ? (new DateTimeImmutable('@' . $date->getTimestamp()))
                        ->setTimezone(wp_timezone())
                    : null,
                'reason' => sanitize_text_field((string) $refund->get_reason()),
                'gross_minor' => $grossMinor,
                'tax_minor' => $taxMinor,
                'ex_tax_minor' => max(0, $grossMinor - $taxMinor),
                'refunded_by' => (int) $refund->get_refunded_by(),
            );
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                $aTime = $a['date'] instanceof DateTimeImmutable
                    ? $a['date']->getTimestamp()
                    : 0;
                $bTime = $b['date'] instanceof DateTimeImmutable
                    ? $b['date']->getTimestamp()
                    : 0;

                return $bTime <=> $aTime;
            }
        );

        return $rows;
    }

    private function minor(
        $amount,
        string $currency,
        int $precision
    ): int {
        try {
            return abs(
                $this->moneyFactory
                    ->fromWooCommerceAmount(
                        (string) $amount,
                        $currency,
                        $precision
                    )
                    ->minorAmount()
            );
        } catch (Throwable $exception) {
            return 0;
        }
    }
}
