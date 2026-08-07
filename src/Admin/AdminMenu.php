<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Compatibility;

final class AdminMenu
{
    private Compatibility $compatibility;

    public function __construct(
        Compatibility $compatibility
    ) {
        $this->compatibility = $compatibility;
    }

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu']
        );
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'حاشیه‌بان',
            'حاشیه‌بان',
            'manage_woocommerce',
            'hashieban',
            [$this, 'renderPage'],
            'dashicons-chart-area',
            56
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(
                esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'hashieban')
            );
        }

        $woocommerceVersion = defined('WC_VERSION')
            ? WC_VERSION
            : 'نامشخص';

        $woocommerceStatus = $this->compatibility
            ->hasSupportedWooCommerceVersion();

        $cogsStatus = $this->compatibility
            ->isCogsEnabled();

        ?>
        <div
            class="wrap"
            dir="rtl"
        >
            <h1>حاشیه‌بان</h1>

            <p>
                وضعیت اولیه افزونه و سرویس‌های مورد نیاز
            </p>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>نسخه حاشیه‌بان</th>
                        <td>
                            <?php echo esc_html(HASHIEBAN_VERSION); ?>
                        </td>
                    </tr>

                    <tr>
                        <th>نسخه ووکامرس</th>
                        <td>
                            <?php echo esc_html($woocommerceVersion); ?>
                        </td>
                    </tr>

                    <tr>
                        <th>سازگاری نسخه ووکامرس</th>
                        <td>
                            <?php
                            echo $woocommerceStatus
                                ? '✅ سازگار'
                                : '❌ ناسازگار';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>بهای تمام‌شده کالا (COGS)</th>
                        <td>
                            <?php
                            echo $cogsStatus
                                ? '✅ فعال'
                                : '⚠️ غیرفعال';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>HPOS</th>
                        <td>
                            ✅ پشتیبانی اعلام شده
                        </td>
                    </tr>

                    <tr>
                        <th>وضعیت افزونه</th>
                        <td>
                            <?php if ($woocommerceStatus && $cogsStatus) : ?>
                                <strong>
                                    ✅ حاشیه‌بان آماده ادامه پیکربندی است.
                                </strong>
                            <?php else : ?>
                                <strong>
                                  ⚠️ برخی پیش‌نیازها کامل نیستند.
                                </strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
		}
		}
