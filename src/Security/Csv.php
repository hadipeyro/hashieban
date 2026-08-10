<?php

declare(strict_types=1);

namespace Hashieban\Security;

final class Csv
{
    public static function protectRow(array $row): array
    {
        return array_map(
            static function ($value) {
                return self::protectCell($value);
            },
            $row
        );
    }

    /**
     * Prevent spreadsheet formula execution when a CSV cell contains
     * user-controlled text such as a product/customer/expense name.
     */
    public static function protectCell($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return $value;
        }

        $first = substr($value, 0, 1);

        if (
            $first === '='
            || $first === '+'
            || $first === '@'
            || $first === "\t"
            || $first === "\r"
            || (
                $first === '-'
                && ! preg_match('/^-\d+(?:[\.,]\d+)?$/u', $value)
            )
        ) {
            return "'" . $value;
        }

        return $value;
    }
}
