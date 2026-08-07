<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce;

use Hashieban\Domain\Money\Money;
use InvalidArgumentException;

final class WooCommerceMoneyFactory
{
    /**
     * @param mixed $amount
     */
    public function fromDecimal(
        $amount,
        string $currency,
        int $precision
    ): Money {
        $normalized = wc_format_decimal(
            $amount,
            $precision
        );

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'Invalid WooCommerce monetary amount.'
            );
        }

        $negative = strpos($normalized, '-') === 0;

        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        $parts = explode('.', $normalized, 2);

        $whole = $parts[0] !== ''
        ? $parts[0]
               : '0';

        $fraction = $parts[1] ?? '';

        if ($precision > 0) {
            $fraction = str_pad(
                $fraction,
                $precision,
                '0',
                STR_PAD_RIGHT
            );

            $fraction = substr(
                $fraction,
                0,
                $precision
            );
        } else {
            $fraction = '';
        }

        $multiplier = 10 ** $precision;

        $minorAmount = ((int) $whole * $multiplier);

        if ($fraction !== '') {
            $minorAmount += (int) $fraction;
        }

        if ($negative) {
            $minorAmount *= -1;
        }

        return new Money(
            $minorAmount,
            $currency,
            $precision
        );
    }
}
