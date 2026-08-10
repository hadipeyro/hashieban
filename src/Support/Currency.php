<?php

declare(strict_types=1);

namespace Hashieban\Support;

final class Currency
{
    private const DISPLAY_OPTION =
        'hashieban_display_unit';

    public const MODE_STORE = 'store';
    public const MODE_TOMAN = 'toman';

    public static function storeCode(): string
    {
        return get_woocommerce_currency();
    }

    public static function precision(): int
    {
        return wc_get_price_decimals();
    }

    public static function displayMode(
        ?string $currency = null
    ): string {
        $currency = $currency ?: self::storeCode();

        $saved = (string) get_option(
            self::DISPLAY_OPTION,
            ''
        );

        if (
            $saved === self::MODE_TOMAN
            && self::canUseToman($currency)
        ) {
            return self::MODE_TOMAN;
        }

        if ($saved === self::MODE_STORE) {
            return self::MODE_STORE;
        }

        /*
         * Iranian stores get Toman by default,
         * without changing WooCommerce storage.
         */
        if (self::canUseToman($currency)) {
            return self::MODE_TOMAN;
        }

        return self::MODE_STORE;
    }

    public static function setDisplayMode(
        string $mode
    ): void {
        if (
            $mode !== self::MODE_STORE
            && $mode !== self::MODE_TOMAN
        ) {
            $mode = self::MODE_STORE;
        }

        if (
            $mode === self::MODE_TOMAN
            && ! self::canUseToman()
        ) {
            $mode = self::MODE_STORE;
        }

        update_option(
            self::DISPLAY_OPTION,
            $mode
        );
    }

    public static function canUseToman(
        ?string $currency = null
    ): bool {
        $currency = strtoupper(
            $currency ?: self::storeCode()
        );

        return in_array(
            $currency,
            array(
                'IRR',
                'IRT',
            ),
            true
        );
    }

    public static function label(
        ?string $currency = null
    ): string {
        $currency = strtoupper(
            $currency ?: self::storeCode()
        );

        if (
            self::displayMode($currency)
            === self::MODE_TOMAN
        ) {
            return 'تومان';
        }

        return self::storeLabel($currency);
    }

    public static function storeLabel(
        ?string $currency = null
    ): string {
        $currency = strtoupper(
            $currency ?: self::storeCode()
        );

        if ($currency === 'IRR') {
            return 'ریال';
        }

        if ($currency === 'IRT') {
            return 'تومان';
        }

        $symbol =
            get_woocommerce_currency_symbol(
                $currency
            );

        if ($symbol !== '') {
            return wp_strip_all_tags(
                $symbol
            );
        }

        return $currency;
    }

    public static function formatMinor(
        int $minorAmount,
        string $currency,
        int $precision
    ): string {
        $value =
            self::minorToDisplayNumber(
                $minorAmount,
                $currency,
                $precision
            );

        $decimals = 0;

        if (
            abs($value - round($value))
            > 0.000001
        ) {
            $decimals = min(
                2,
                $precision + 1
            );
        }

        $isNegative = $value < 0;
        $formattedValue = number_format_i18n(
            abs($value),
            $decimals
        );

        if ($isNegative) {
            /*
             * Keep the minus sign visually before the amount in RTL
             * admin screens. LRM is invisible and only stabilizes bidi.
             */
            $formattedValue = "\u{200E}−"
                . $formattedValue
                . "\u{200E}";
        }

        return $formattedValue
             . ' '
             . self::label($currency);
    }

    public static function minorToDisplayNumber(
        int $minorAmount,
        string $currency,
        int $precision
    ): float {
        $factor = 10 ** $precision;

        $value =
            $factor > 1
        ? $minorAmount / $factor
            : $minorAmount;

        if (
            self::displayMode($currency)
            === self::MODE_TOMAN
            && strtoupper($currency) === 'IRR'
        ) {
            $value /= 10;
        }

        return (float) $value;
    }

    public static function minorToDisplayInput(
        int $minorAmount,
        string $currency,
        int $precision
    ): string {
        $value =
            self::minorToDisplayNumber(
                $minorAmount,
                $currency,
                $precision
            );

        $formatted = number_format(
            $value,
            4,
            '.',
            ''
        );

        return self::trimDecimalZeros(
            $formatted
        );
    }

    public static function storeDecimalToDisplayInput(
        string $amount,
        string $currency
    ): string {
        $amount = self::normalizeNumber(
            $amount
        );

        if ($amount === '') {
            return '';
        }

        if (
            self::displayMode($currency)
            === self::MODE_TOMAN
            && strtoupper($currency) === 'IRR'
        ) {
            return self::divideDecimalByTen(
                $amount
            );
        }

        return self::trimDecimalZeros(
            $amount
        );
    }

    public static function displayInputToStoreDecimal(
        string $amount,
        string $currency,
        int $precision
    ): string {
        $amount = self::normalizeNumber(
            $amount
        );

        if ($amount === '') {
            return '';
        }

        if (
            self::displayMode($currency)
            === self::MODE_TOMAN
            && strtoupper($currency) === 'IRR'
        ) {
            $amount =
                self::multiplyDecimalByTen(
                    $amount
                );
        }

        return wc_format_decimal(
            $amount,
            $precision
        );
    }

    public static function normalizeNumber(
        string $value
    ): string {
        $value = trim(
            self::toEnglishDigits($value)
        );

        $value = str_replace(
            array(
                ',',
                '٬',
                ' ',
            ),
            '',
            $value
        );

        $value = str_replace(
            '٫',
            '.',
            $value
        );

        if (
            ! preg_match(
                '/^-?\d+(?:\.\d+)?$/',
                $value
            )
        ) {
            return '';
        }

        return self::trimDecimalZeros(
            $value
        );
    }

    public static function toEnglishDigits(
        string $value
    ): string {
        return strtr(
            $value,
            array(
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            )
        );
    }

    public static function toPersianDigits(
        string $value
    ): string {
        return strtr(
            $value,
            array(
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
            )
        );
    }

    private static function multiplyDecimalByTen(
        string $value
    ): string {
        $negative = false;

        if (
            substr($value, 0, 1) === '-'
        ) {
            $negative = true;
            $value = substr($value, 1);
        }

        $parts = explode(
            '.',
            $value,
            2
        );

        $integer = $parts[0];
        $fraction = $parts[1] ?? '';

        if ($fraction === '') {
            $result = $integer . '0';
        } else {
            $result =
                $integer
              . substr($fraction, 0, 1);

            $rest = substr(
                $fraction,
                1
            );

            if ($rest !== '') {
                $result .= '.' . $rest;
            }
        }

        $result =
            self::trimDecimalZeros(
                $result
            );

        return $negative
        ? '-' . $result
             : $result;
    }

    private static function divideDecimalByTen(
        string $value
    ): string {
        $negative = false;

        if (
            substr($value, 0, 1) === '-'
        ) {
            $negative = true;
            $value = substr($value, 1);
        }

        $parts = explode(
            '.',
            $value,
            2
        );

        $integer = ltrim(
            $parts[0],
            '0'
        );

        if ($integer === '') {
            $integer = '0';
        }

        $fraction = $parts[1] ?? '';

        if (strlen($integer) > 1) {
            $lastDigit = substr(
                $integer,
                -1
            );

            $newInteger = substr(
                $integer,
                0,
                -1
            );
        } else {
            $lastDigit = $integer;
            $newInteger = '0';
        }

        $newFraction =
            $lastDigit
          . $fraction;

        $result =
            $newInteger
          . '.'
          . $newFraction;

        $result =
            self::trimDecimalZeros(
                $result
            );

        return $negative
        ? '-' . $result
             : $result;
    }

    private static function trimDecimalZeros(
        string $value
    ): string {
        if (
            strpos($value, '.')
            === false
        ) {
            return $value;
        }

        $value = rtrim(
            $value,
            '0'
        );

        $value = rtrim(
            $value,
            '.'
        );

        return $value === '-0'
        ? '0'
             : $value;
    }
}
