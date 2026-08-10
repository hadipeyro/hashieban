<?php

declare(strict_types=1);

namespace Hashieban\Finance;

use Hashieban\Domain\Money\Money;
use WC_Order;

final class GlobalOrderCostRepository
{
    private const OPTION_KEY =
        'hashieban_global_order_costs';

    private const ORDER_SNAPSHOT_META_KEY =
        '_hashieban_global_order_cost_snapshot';

    public function all(): array
    {
        $rules = get_option(
            self::OPTION_KEY,
            array()
        );

        return is_array($rules)
        ? $rules
             : array();
    }

    public function save(
        array $rules
    ): void {
        update_option(
            self::OPTION_KEY,
            array_values($rules)
        );
    }

    public function total(
        string $currency,
        int $precision
    ): Money {
        $total = Money::zero(
            $currency,
            $precision
        );

        foreach ($this->all() as $rule) {
            if (
                ! is_array($rule)
                || empty($rule['currency'])
                || $rule['currency']
                !== $currency
            ) {
                continue;
            }

            if (
                (int) (
                    $rule['precision']
                    ?? -1
                ) !== $precision
            ) {
                continue;
            }

            $money = new Money(
                (int) (
                    $rule['amount_minor']
                    ?? 0
                ),
                $currency,
                $precision
            );

            $total = $total->add(
                $money
            );
        }

        return $total;
    }

    public function totalForOrder(
        WC_Order $order,
        string $currency,
        int $precision
    ): Money {
        $snapshot =
            $order->get_meta(
                self::ORDER_SNAPSHOT_META_KEY,
                true
            );

        if (is_array($snapshot)) {
            $snapshotCurrency =
                strtoupper(
                    trim(
                        (string) (
                            $snapshot['currency']
                            ?? ''
                        )
                    )
                );

            $snapshotPrecision =
                (int) (
                    $snapshot['precision']
                    ?? -1
                );

            if (
                $snapshotCurrency
                    === strtoupper($currency)
                && $snapshotPrecision
                    === $precision
            ) {
                return new Money(
                    (int) (
                        $snapshot['amount_minor']
                        ?? 0
                    ),
                    $currency,
                    $precision
                );
            }
        }

        return $this->total(
            $currency,
            $precision
        );
    }

    public function snapshotForOrder(
        WC_Order $order,
        Money $money
    ): void {
        $order->update_meta_data(
            self::ORDER_SNAPSHOT_META_KEY,
            array(
                'amount_minor' =>
                    $money->minorAmount(),
                'currency' =>
                    $money->currency(),
                'precision' =>
                    $money->precision(),
                'captured_at_gmt' =>
                    gmdate('c'),
            )
        );
    }

}
