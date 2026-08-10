<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Support\Currency;

final class AdminMenu
{
    private Compatibility $compatibility;

    private DashboardPage $dashboard;

    private AnalyticsHubPage $analyticsHubPage;

    private ProductProfitabilityPage $productProfitabilityPage;

    private CustomerProfitabilityPage $customerProfitabilityPage;

    private TimeIntelligencePage $timeIntelligencePage;

    private OrderProfitCenterPage $orderProfitCenterPage;

    private MarginGuardPage $marginGuardPage;

    private ReportsHubPage $reportsHubPage;

    private ExpenseIntelligencePage $expenseIntelligencePage;

    private DataHealthPage $dataHealthPage;

    private GeoIntelligencePage $geoIntelligencePage;

    private BulkToolsPage $bulkToolsPage;

    private ExpensesPage $expensesPage;

    private ExpenseCategoriesPage $categoriesPage;

    private SettingsPage $settingsPage;

    public function __construct(
        Compatibility $compatibility,
        DashboardPage $dashboard,
        AnalyticsHubPage $analyticsHubPage,
        ProductProfitabilityPage $productProfitabilityPage,
        CustomerProfitabilityPage $customerProfitabilityPage,
        TimeIntelligencePage $timeIntelligencePage,
        OrderProfitCenterPage $orderProfitCenterPage,
        MarginGuardPage $marginGuardPage,
        ReportsHubPage $reportsHubPage,
        ExpenseIntelligencePage $expenseIntelligencePage,
        DataHealthPage $dataHealthPage,
        GeoIntelligencePage $geoIntelligencePage,
        BulkToolsPage $bulkToolsPage,
        ExpensesPage $expensesPage,
        ExpenseCategoriesPage $categoriesPage,
        SettingsPage $settingsPage
    ) {
        $this->compatibility =
            $compatibility;

        $this->dashboard =
            $dashboard;

        $this->analyticsHubPage =
            $analyticsHubPage;

        $this->productProfitabilityPage =
            $productProfitabilityPage;

        $this->customerProfitabilityPage =
            $customerProfitabilityPage;

        $this->timeIntelligencePage =
            $timeIntelligencePage;

        $this->orderProfitCenterPage =
            $orderProfitCenterPage;

        $this->marginGuardPage =
            $marginGuardPage;

        $this->reportsHubPage =
            $reportsHubPage;

        $this->expenseIntelligencePage =
            $expenseIntelligencePage;

        $this->dataHealthPage =
            $dataHealthPage;

        $this->geoIntelligencePage =
            $geoIntelligencePage;

        $this->bulkToolsPage =
            $bulkToolsPage;

        $this->expensesPage =
            $expensesPage;

        $this->categoriesPage =
            $categoriesPage;

        $this->settingsPage =
            $settingsPage;
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
            'مرکز تحلیل‌های حاشیه‌بان',
            'مرکز تحلیل‌ها',
            'manage_woocommerce',
            'hashieban-analytics',
            array(
                $this->analyticsHubPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تحلیل سودآوری محصولات',
            'سودآوری محصولات',
            'manage_woocommerce',
            'hashieban-products',
            array(
                $this->productProfitabilityPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تحلیل سودآوری مشتریان',
            'سودآوری مشتریان',
            'manage_woocommerce',
            'hashieban-customers',
            array(
                $this->customerProfitabilityPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'هوش زمانی فروش و سود',
            'تحلیل زمانی',
            'manage_woocommerce',
            'hashieban-time',
            array(
                $this->timeIntelligencePage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'مرکز سودآوری سفارش‌ها',
            'مرکز سفارش‌ها',
            'manage_woocommerce',
            'hashieban-orders',
            array(
                $this->orderProfitCenterPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'نگهبان سود و هشدارهای مدیریتی',
            'هشدارهای مدیریتی',
            'manage_woocommerce',
            'hashieban-alerts',
            array(
                $this->marginGuardPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'مرکز گزارش‌های مدیریتی',
            'گزارش‌ها',
            'manage_woocommerce',
            'hashieban-reports',
            array(
                $this->reportsHubPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'هوش هزینه‌ها و کنترل بودجه',
            'هوش هزینه‌ها',
            'manage_woocommerce',
            'hashieban-expense-intelligence',
            array(
                $this->expenseIntelligencePage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'سلامت داده و آمادگی تحلیل',
            'سلامت داده',
            'manage_woocommerce',
            'hashieban-data-health',
            array(
                $this->dataHealthPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'نقشه هوشمند فروش و سود ایران',
            'نقشه فروش ایران',
            'manage_woocommerce',
            'hashieban-geo',
            array(
                $this->geoIntelligencePage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'ابزارهای گروهی و مهاجرت داده',
            'ابزارهای گروهی',
            'manage_woocommerce',
            'hashieban-bulk-tools',
            array(
                $this->bulkToolsPage,
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
            'دسته‌بندی هزینه‌ها',
            'دسته‌های هزینه',
            'manage_woocommerce',
            'hashieban-expense-categories',
            array(
                $this->categoriesPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تنظیمات',
            'تنظیمات',
            'manage_woocommerce',
            'hashieban-settings',
            array(
                $this->settingsPage,
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

        /*
         * Specialist screens intentionally remain registered as normal
         * submenu pages so WordPress keeps their routes and capabilities
         * intact. A tiny admin navigation script hides selected links only
         * from the sidebar; the Analytics Hub remains their visible entry
         * point.
         */
    }

    public function enqueueAssets(
        string $hook
    ): void {
        wp_enqueue_script(
            'hashieban-admin-navigation',
            plugins_url(
                'assets/admin/js/hashieban-admin-navigation.js',
                HASHIEBAN_FILE
            ),
            array(),
            HASHIEBAN_VERSION,
            true
        );

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

        wp_enqueue_style(
            'hashieban-jalali-datepicker',
            plugins_url(
                'assets/vendor/jalalidatepicker/jalalidatepicker.min.css',
                HASHIEBAN_FILE
            ),
            array(),
            HASHIEBAN_VERSION
        );

        wp_enqueue_script(
            'hashieban-jalali-datepicker',
            plugins_url(
                'assets/vendor/jalalidatepicker/jalalidatepicker.min.js',
                HASHIEBAN_FILE
            ),
            array(),
            HASHIEBAN_VERSION,
            true
        );

        wp_enqueue_script(
            'hashieban-common',
            plugins_url(
                'assets/admin/js/hashieban-common.js',
                HASHIEBAN_FILE
            ),
            array(
                'hashieban-jalali-datepicker'
            ),
            HASHIEBAN_VERSION,
            true
        );

        wp_enqueue_script(
            'hashieban-chartjs',
            plugins_url(
                'assets/vendor/chartjs/chart.umd.js',
                HASHIEBAN_FILE
            ),
            array(),
            HASHIEBAN_VERSION,
            true
        );

        if (
            strpos(
                $hook,
                'hashieban-analytics'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-analytics-hub',
                plugins_url(
                    'assets/admin/css/hashieban-analytics-hub.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-products'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-product-profitability',
                plugins_url(
                    'assets/admin/css/hashieban-product-profitability.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-product-profitability',
                plugins_url(
                    'assets/admin/js/hashieban-product-profitability.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-customers'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-customer-profitability',
                plugins_url(
                    'assets/admin/css/hashieban-customer-profitability.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-customer-profitability',
                plugins_url(
                    'assets/admin/js/hashieban-customer-profitability.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-time'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-time-intelligence',
                plugins_url(
                    'assets/admin/css/hashieban-time-intelligence.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-time-intelligence',
                plugins_url(
                    'assets/admin/js/hashieban-time-intelligence.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-orders'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-orders',
                plugins_url(
                    'assets/admin/css/hashieban-orders.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-orders',
                plugins_url(
                    'assets/admin/js/hashieban-orders.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-alerts'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-margin-guard',
                plugins_url(
                    'assets/admin/css/hashieban-margin-guard.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-margin-guard',
                plugins_url(
                    'assets/admin/js/hashieban-margin-guard.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-reports'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-reports',
                plugins_url(
                    'assets/admin/css/hashieban-reports.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-reports',
                plugins_url(
                    'assets/admin/js/hashieban-reports.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-expense-intelligence'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-expense-intelligence',
                plugins_url(
                    'assets/admin/css/hashieban-expense-intelligence.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-expense-intelligence',
                plugins_url(
                    'assets/admin/js/hashieban-expense-intelligence.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-data-health'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-data-health',
                plugins_url(
                    'assets/admin/css/hashieban-data-health.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-data-health',
                plugins_url(
                    'assets/admin/js/hashieban-data-health.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-geo'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-geo-intelligence',
                plugins_url(
                    'assets/admin/css/hashieban-geo-intelligence.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-geo-intelligence',
                plugins_url(
                    'assets/admin/js/hashieban-geo-intelligence.js',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-chartjs'
                ),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-bulk-tools'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-bulk-tools',
                plugins_url(
                    'assets/admin/css/hashieban-bulk-tools.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-settings'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-settings',
                plugins_url(
                    'assets/admin/css/hashieban-settings.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-settings',
                plugins_url(
                    'assets/admin/js/hashieban-settings.js',
                    HASHIEBAN_FILE
                ),
                array(),
                HASHIEBAN_VERSION,
                true
            );
        }

        if (
            strpos(
                $hook,
                'hashieban-expense-categories'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-categories',
                plugins_url(
                    'assets/admin/css/hashieban-categories.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );
        }
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
            $enabled =
                \Automattic\WooCommerce\Utilities\OrderUtil
                    ::custom_orders_table_usage_is_enabled();

            $hposStatus =
                $enabled
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
                        <th>WooCommerce</th>
                        <td>
                            <?php
                            echo esc_html(
                                $woocommerceVersion
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>HPOS</th>
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
                            واحد اصلی ووکامرس
                        </th>
                        <td>
                            <?php
                            echo esc_html(
                                Currency::storeLabel()
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            واحد نمایش حاشیه‌بان
                        </th>
                        <td>
                          <?php
                          echo esc_html(
                              Currency::label()
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
