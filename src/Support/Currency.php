<?php

declare(strict_types=1);

namespace Hashieban\Support;

final class Currency
{
    public static function storeCode(): string
    {
        return get_woocommerce_currency();
    }

    public static function precision(): int
    {
        return wc_get_price_decimals();
    }

    public static function label(
        ?string $currency = null
    ): string {
        $currency = $currency ?: self::storeCode();

        switch (strtoupper($currency)) {
            case 'IRR':
                return 'ریال';

            case 'IRT':
                return 'تومان';
        }

        $symbol = get_woocommerce_currency_symbol(
            $currency
        );

        if ($symbol !== '') {
            return wp_strip_all_tags($symbol);
        }

        return $currency;
    }

    public static function formatMinor(
        int $minorAmount,
        string $currency,
        int $precision
    ): string {
        $factor = 10 ** $precision;

        $amount = $factor > 1
        ? $minorAmount / $factor
				: $minorAmount;

        return number_format_i18n(
            $amount,
            $precision
        ) . ' ' . self::label($currency);
    }
}
