<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Compatibility;

final class AdminMenu
{
    private Compatibility $compatibility;

    private DashboardPage $dashboard;

    private ExpensesPage $expensesPage;

    public function __construct(
        Compatibility $compatibility,
        DashboardPage $dashboard,
        ExpensesPage $expensesPage
    ) {
        $this->compatibility = $compatibility;
        $this->dashboard = $dashboard;
        $this->expensesPage = $expensesPage;
    }

    public function register(): void
    {
        add_action(
            'admin_menu',
            array($this, 'registerMenu')
        );

        add_action(
            'admin_enqueue_scripts',
            array($this, 'enqueueAssets')
        );
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'حاشیه‌بان',
            'حاشیه‌بان',
            'manage_woocommerce',
            'hashieban',
            array(
                $this->dashboard,
                'render'
            ),
            'dashicons-chart-area',
            56
        );

        add_submenu_page(
            'hashieban',
            'پیشخوان',
            'پیشخوان',
            'manage_woocommerce',
            'hashieban',
            array(
                $this->dashboard,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'هزینه‌های فروشگاه',
            'هزینه‌ها',
            'manage_woocommerce',
            'hashieban-expenses',
            array(
                $this->expensesPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'وضعیت سیستم',
            'وضعیت سیستم',
            'manage_woocommerce',
            'hashieban-status',
            array(
                $this,
                'renderStatusPage'
            )
        );
    }

    public function enqueueAssets(
        string $hook
    ): void {
        if (
            strpos(
                $hook,
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
            array(),
            HASHIEBAN_VERSION
        );

        wp_enqueue_style(
            'hashieban-finance',
            plugins_url(
                'assets/admin/css/hashieban-finance.css',
                HASHIEBAN_FILE
            ),
            array(
                'hashieban-admin'
            ),
            HASHIEBAN_VERSION
        );
    }

    public function renderStatusPage(): void
    {
        if (
            ! current_user_can(
                'manage_woocommerce'
            )
        ) {
            return;
        }

        $woocommerceVersion =
            defined('WC_VERSION')
                ? WC_VERSION
                : '—';

        $hposStatus = 'نامشخص';

        if (
            class_exists(
                '\Automattic\WooCommerce\Utilities\OrderUtil'
            )
        ) {
            $hposEnabled =
                \Automattic\WooCommerce\Utilities\OrderUtil
                    ::custom_orders_table_usage_is_enabled();

            $hposStatus =
                $hposEnabled
                    ? 'فعال'
                    : 'غیرفعال';
        }

        ?>
        <div class="wrap">

            <h1>
                وضعیت سیستم حاشیه‌بان
            </h1>

            <table class="widefat striped">
                <tbody>

                    <tr>
                        <th>
                            نسخه حاشیه‌بان
                        </th>

                        <td>
                            <?php
                            echo esc_html(
                                HASHIEBAN_VERSION
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            نسخه WooCommerce
                        </th>

                        <td>
                            <?php
                            echo esc_html(
                                $woocommerceVersion
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            HPOS
                        </th>

                        <td>
                            <?php
                            echo esc_html(
                                $hposStatus
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            واحد مالی
                        </th>

                        <td>
                          <?php
                          echo esc_html(
                              \Hashieban\Support\Currency
                              ::label()
                          );
                          ?>
                        </td>
                    </tr>

                </tbody>
            </table>

        </div>
        <?php
		}
		}
