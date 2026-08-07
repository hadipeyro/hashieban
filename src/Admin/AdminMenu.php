<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Compatibility;

final class AdminMenu
{
    private Compatibility $compatibility;

    private DashboardPage $dashboard;

    public function __construct(
        Compatibility $compatibility,
        DashboardPage $dashboard
    ) {
        $this->compatibility = $compatibility;
        $this->dashboard = $dashboard;
    }

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu']
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueueAssets']
        );
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'حاشیه‌بان',
            'حاشیه‌بان',
            'manage_woocommerce',
            'hashieban',
            [$this->dashboard, 'render'],
            'dashicons-chart-area',
            56
        );

        add_submenu_page(
            'hashieban',
            'پیشخوان حاشیه‌بان',
            'پیشخوان',
            'manage_woocommerce',
            'hashieban',
            [$this->dashboard, 'render']
        );

        add_submenu_page(
            'hashieban',
            'وضعیت حاشیه‌بان',
            'وضعیت سیستم',
            'manage_woocommerce',
            'hashieban-status',
            [$this, 'renderStatusPage']
        );
    }

    public function enqueueAssets(
        string $hookSuffix
    ): void {
        if (
            strpos(
                $hookSuffix,
                'hashieban'
            ) === false
        ) {
            return;
        }

        wp_enqueue_style(
            'hashieban-admin',
            plugins_url(
                'assets/admin/css/hashieban-admin.css',
                HASHIEBAN_FILE
            ),
            [],
            HASHIEBAN_VERSION
        );
    }

    public function renderStatusPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(
                esc_html__(
                    'شما اجازه دسترسی به این صفحه را ندارید.',
                    'hashieban'
                )
            );
        }

        $woocommerceVersion = defined('WC_VERSION')
            ? WC_VERSION
            : 'نامشخص';

        $woocommerceStatus =
            $this->compatibility
                ->hasSupportedWooCommerceVersion();

        $cogsStatus =
            $this->compatibility
                ->isCogsEnabled();

        ?>
        <div class="wrap" dir="rtl">

            <h1>وضعیت سیستم حاشیه‌بان</h1>

            <table class="widefat striped">

                <tbody>

                    <tr>
                        <th>نسخه حاشیه‌بان</th>
                        <td>
                            <?php
                            echo esc_html(
                                HASHIEBAN_VERSION
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>نسخه ووکامرس</th>
                        <td>
                            <?php
                            echo esc_html(
                                $woocommerceVersion
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>سازگاری ووکامرس</th>
                        <td>
                            <?php
                            echo $woocommerceStatus
                                ? '✅ سازگار'
                                : '❌ ناسازگار';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>COGS</th>
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

                </tbody>

            </table>

        </div>
        <?php
		}
		}
