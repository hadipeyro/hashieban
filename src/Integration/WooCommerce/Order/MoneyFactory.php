<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;
use InvalidArgumentException;

final class MoneyFactory
{
    /**
     * Convert a WooCommerce decimal value to Hashieban Money.
     *
     * @param mixed $amount
     */
    public function fromWooCommerceAmount(
        $amount,
        string $currency,
        int $precision
    ): Money {
        $normalized = wc_format_decimal(
            (string) $amount,
            $precision
        );

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'Invalid WooCommerce monetary value.'
            );
        }

        return new Money(
            $this->decimalToMinorAmount(
                $normalized,
                $precision
            ),
            $currency,
            $precision
        );
    }

    private function decimalToMinorAmount(
        string $amount,
        int $precision
    ): int {
        $negative = false;

        if (strpos($amount, '-') === 0) {
            $negative = true;
            $amount = substr($amount, 1);
        }

        $parts = explode('.', $amount, 2);

        $whole = $parts[0] !== ''
        ? $parts[0]
               : '0';

        $fraction = $parts[1] ?? '';

        if ($precision > 0) {
            $fraction = str_pad(
                substr($fraction, 0, $precision),
                $precision,
                '0'
            );

            $factor = 10 ** $precision;

            $minorAmount = ((int) $whole * $factor)
            + (int) $fraction;
        } else {
            $minorAmount = (int) $whole;
        }

        return $negative
        ? -$minorAmount
             : $minorAmount;
    }
}
