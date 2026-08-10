<?php

declare(strict_types=1);

namespace Hashieban\Security;

final class Json
{
    public static function forHtmlScript($value): string
    {
        $encoded = wp_json_encode(
            $value,
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        return is_string($encoded)
            ? $encoded
            : '{}';
    }
}
