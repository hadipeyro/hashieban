<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce;

use Automattic\WooCommerce\Internal\Features\FeaturesController;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

final class Compatibility
{
    private const MINIMUM_WOOCOMMERCE_VERSION = '10.3.0';

    /**
     * Register WooCommerce compatibility hooks.
     */
    public function register(): void
    {
        add_action(
            'before_woocommerce_init',
            [$this, 'declareHposCompatibility']
        );

        add_action(
            'admin_notices',
            [$this, 'renderAdminNotices']
        );
    }

    /**
     * Declare compatibility with High-Performance Order Storage.
     */
    public function declareHposCompatibility(): void
    {
        if (! class_exists(FeaturesUtil::class)) {
            return;
        }

        FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            HASHIEBAN_FILE,
            true
        );
    }

    /**
     * Check whether the installed WooCommerce version is supported.
     */
    public function hasSupportedWooCommerceVersion(): bool
    {
        if (! defined('WC_VERSION')) {
            return false;
        }

        return version_compare(
            WC_VERSION,
            self::MINIMUM_WOOCOMMERCE_VERSION,
            '>='
        );
    }

    /**
     * Check whether WooCommerce COGS is enabled.
     */
    public function isCogsEnabled(): bool
    {
        if (! function_exists('wc_get_container')) {
            return false;
        }

        if (! class_exists(FeaturesController::class)) {
            return false;
        }

        $features = wc_get_container()->get(FeaturesController::class);

        return $features->feature_is_enabled('cost_of_goods_sold');
    }

    /**
     * Display compatibility notices for administrators.
     */
    public function renderAdminNotices(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        if (! $this->hasSupportedWooCommerceVersion()) {
            $this->renderNotice(
                sprintf(
                    'حاشیه‌بان به ووکامرس نسخه %s یا جدیدتر نیاز دارد.',
                    self::MINIMUM_WOOCOMMERCE_VERSION
                ),
                'error'
            );

            return;
        }

        if (! $this->isCogsEnabled()) {
            $this->renderNotice(
                'برای محاسبه سود در حاشیه‌بان، قابلیت «بهای تمام‌شده کالا» (COGS) را از تنظیمات ووکامرس فعال کنید.',
                'warning'
            );
        }
    }

    /**
     * Render a WordPress admin notice.
     */
    private function renderNotice(string $message, string $type): void
    {
        printf(
            '<div class="notice notice-%1$s"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
}
