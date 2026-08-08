<?php

declare(strict_types=1);

namespace Hashieban\Finance;

use Hashieban\Domain\Money\Money;

final class GlobalOrderCostRepository
{
    private const OPTION_KEY =
        'hashieban_global_order_costs';

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
}
