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
     * Determine whether the installed WooCommerce version is supported.
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
     * Determine whether WooCommerce COGS is enabled.
     */
    public function isCogsEnabled(): bool
    {
        if (! function_exists('wc_get_container')) {
            return false;
        }

        if (! class_exists(FeaturesController::class)) {
            return false;
        }

        $features = wc_get_container()->get(
            FeaturesController::class
        );

        return $features->feature_is_enabled(
            'cost_of_goods_sold'
        );
    }

    /**
     * Render Hashieban compatibility notices.
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
            $this->renderCogsNotice();
        }
    }

    /**
     * Render instructions for enabling WooCommerce COGS.
     */
    private function renderCogsNotice(): void
    {
        $settingsUrl = admin_url(
            'admin.php?page=wc-settings&tab=advanced&section=features'
        );

        ?>
        <div
            class="notice notice-warning"
            dir="rtl"
        >
            <p>
                <strong>
                    حاشیه‌بان برای محاسبه دقیق سود به اطلاعات بهای تمام‌شده کالا نیاز دارد.
                </strong>
            </p>

            <p>
                قابلیت
                <strong>Cost of Goods Sold (COGS)</strong>
                در ووکامرس شما غیرفعال است.
            </p>

            <p>
                برای فعال‌سازی از مسیر زیر وارد تنظیمات ووکامرس شوید:
            </p>

            <p>
                <strong>
                    ووکامرس ← پیکربندی ← پیشرفته ← امکانات (Features)
                    ← Cost of Goods Sold
                </strong>
            </p>

            <p>
                گزینه
                <strong>Cost of Goods Sold</strong>
                را فعال کنید و سپس در پایین صفحه روی
                <strong>ذخیره تغییرات</strong>
                کلیک کنید.
            </p>

            <p>
                پس از فعال‌سازی، هنگام ویرایش محصولات می‌توانید
                بهای تمام‌شده هر محصول را ثبت کنید.
            </p>

            <p>
              <a
                  href="<?php echo esc_url($settingsUrl); ?>"
                  class="button button-primary"
              >
                رفتن به تنظیمات بهای تمام‌شده
              </a>
            </p>
        </div>
<?php
}

/**
 * Render a WordPress admin notice.
 */
private function renderNotice(
    string $message,
    string $type
): void {
    printf(
        '<div class="notice notice-%1$s" dir="rtl"><p>%2$s</p></div>',
        esc_attr($type),
        esc_html($message)
    );
}
}
