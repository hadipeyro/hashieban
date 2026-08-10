<?php

declare(strict_types=1);

namespace Hashieban\Security;

final class Capabilities
{
    public const VIEW_REPORTS = 'hashieban_view_reports';
    public const MANAGE_FINANCE = 'hashieban_manage_finance';
    public const MANAGE_SETTINGS = 'hashieban_manage_settings';
    public const MANAGE_TOOLS = 'hashieban_manage_tools';

    public static function all(): array
    {
        return array(
            self::VIEW_REPORTS,
            self::MANAGE_FINANCE,
            self::MANAGE_SETTINGS,
            self::MANAGE_TOOLS,
        );
    }

    public static function can(string $capability): bool
    {
        return current_user_can($capability)
            || current_user_can('manage_woocommerce');
    }

    public static function require(string $capability, string $message = ''): void
    {
        if (self::can($capability)) {
            return;
        }

        wp_die(
            esc_html(
                $message !== ''
                    ? $message
                    : 'شما اجازه دسترسی به این بخش از حاشیه‌بان را ندارید.'
            )
        );
    }
}
