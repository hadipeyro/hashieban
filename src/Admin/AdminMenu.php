<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Security\Capabilities;
use Hashieban\Support\Currency;

final class AdminMenu
{
    private Compatibility $compatibility;

    private DashboardPage $dashboard;

    private AnalyticsHubPage $analyticsHubPage;

    private BusinessKpisPage $businessKpisPage;

    private SalesChannelIntelligencePage $salesChannelIntelligencePage;

    private CouponDiscountIntelligencePage $couponDiscountIntelligencePage;

    private ProductProfitabilityPage $productProfitabilityPage;

    private InventoryPurchaseInsightPage $inventoryPurchaseInsightPage;

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

    private OnboardingPage $onboardingPage;

    public function __construct(
        Compatibility $compatibility,
        DashboardPage $dashboard,
        AnalyticsHubPage $analyticsHubPage,
        BusinessKpisPage $businessKpisPage,
        SalesChannelIntelligencePage $salesChannelIntelligencePage,
        CouponDiscountIntelligencePage $couponDiscountIntelligencePage,
        ProductProfitabilityPage $productProfitabilityPage,
        InventoryPurchaseInsightPage $inventoryPurchaseInsightPage,
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
        SettingsPage $settingsPage,
        OnboardingPage $onboardingPage
    ) {
        $this->compatibility =
            $compatibility;

        $this->dashboard =
            $dashboard;

        $this->analyticsHubPage =
            $analyticsHubPage;

        $this->businessKpisPage =
            $businessKpisPage;

        $this->salesChannelIntelligencePage =
            $salesChannelIntelligencePage;

        $this->couponDiscountIntelligencePage =
            $couponDiscountIntelligencePage;

        $this->productProfitabilityPage =
            $productProfitabilityPage;

        $this->inventoryPurchaseInsightPage =
            $inventoryPurchaseInsightPage;

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

        $this->onboardingPage =
            $onboardingPage;
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

        add_action(
            'admin_head',
            array($this, 'printNavigationCss'),
            1
        );
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'حاشیه‌بان',
            'حاشیه‌بان',
            Capabilities::VIEW_REPORTS,
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
            Capabilities::VIEW_REPORTS,
            'hashieban',
            array(
                $this->dashboard,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'گزارش‌ها و تحلیل‌های حاشیه‌بان',
            'گزارش‌ها و تحلیل‌ها',
            Capabilities::VIEW_REPORTS,
            'hashieban-analytics',
            array(
                $this->analyticsHubPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'نبض کسب‌وکار و شاخص‌های مدیریتی',
            'نبض کسب‌وکار',
            Capabilities::VIEW_REPORTS,
            'hashieban-kpis',
            array(
                $this->businessKpisPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'کانال‌های فروش و منبع سفارش',
            'کانال‌های فروش',
            Capabilities::VIEW_REPORTS,
            'hashieban-channels',
            array(
                $this->salesChannelIntelligencePage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تحلیل تخفیف و کوپن',
            'تخفیف و کوپن',
            Capabilities::VIEW_REPORTS,
            'hashieban-coupons',
            array(
                $this->couponDiscountIntelligencePage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تحلیل سودآوری محصولات',
            'سودآوری محصولات',
            Capabilities::VIEW_REPORTS,
            'hashieban-products',
            array(
                $this->productProfitabilityPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'موجودی و پیشنهاد خرید',
            'موجودی و خرید',
            Capabilities::VIEW_REPORTS,
            'hashieban-inventory',
            array(
                $this->inventoryPurchaseInsightPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تحلیل سودآوری مشتریان',
            'سودآوری مشتریان',
            Capabilities::VIEW_REPORTS,
            'hashieban-customers',
            array(
                $this->customerProfitabilityPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'روند فروش و سود در زمان',
            'روند زمانی',
            Capabilities::VIEW_REPORTS,
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
            Capabilities::VIEW_REPORTS,
            'hashieban-orders',
            array(
                $this->orderProfitCenterPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'هشدارهای سود و فروش',
            'هشدارهای مدیریتی',
            Capabilities::VIEW_REPORTS,
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
            Capabilities::VIEW_REPORTS,
            'hashieban-reports',
            array(
                $this->reportsHubPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'تحلیل هزینه‌ها و بودجه',
            'تحلیل هزینه‌ها',
            Capabilities::VIEW_REPORTS,
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
            Capabilities::VIEW_REPORTS,
            'hashieban-data-health',
            array(
                $this->dataHealthPage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'نقشه فروش و سود ایران',
            'نقشه فروش ایران',
            Capabilities::VIEW_REPORTS,
            'hashieban-geo',
            array(
                $this->geoIntelligencePage,
                'render'
            )
        );

        add_submenu_page(
            'hashieban',
            'ابزارهای مدیریت داده',
            'ابزارهای گروهی',
            Capabilities::MANAGE_TOOLS,
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
            Capabilities::MANAGE_FINANCE,
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
            Capabilities::MANAGE_FINANCE,
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
            Capabilities::MANAGE_SETTINGS,
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
            Capabilities::VIEW_REPORTS,
            'hashieban-status',
            array(
                $this,
                'renderStatusPage'
            )
        );

        add_submenu_page(
            'hashieban',
            'شروع سریع حاشیه‌بان',
            'شروع سریع',
            Capabilities::VIEW_REPORTS,
            'hashieban-onboarding',
            array(
                $this->onboardingPage,
                'render'
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


    public function printNavigationCss(): void
    {
        ?>
        <style id="hashieban-admin-navigation-css">
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-kpis"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-products"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-channels"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-coupons"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-inventory"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-customers"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-time"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-alerts"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-reports"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-expense-intelligence"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-data-health"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-geo"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-bulk-tools"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-expense-categories"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-status"]),
            #toplevel_page_hashieban .wp-submenu li:has(a[href*="page=hashieban-onboarding"]) {
                display: none !important;
            }
        </style>
        <?php
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
                'hashieban-onboarding'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-onboarding',
                plugins_url(
                    'assets/admin/css/hashieban-onboarding.css',
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
                'hashieban-kpis'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-business-kpis',
                plugins_url(
                    'assets/admin/css/hashieban-business-kpis.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-business-kpis',
                plugins_url(
                    'assets/admin/js/hashieban-business-kpis.js',
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
                'hashieban-channels'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-sales-channels',
                plugins_url(
                    'assets/admin/css/hashieban-sales-channels.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-sales-channels',
                plugins_url(
                    'assets/admin/js/hashieban-sales-channels.js',
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
                'hashieban-coupons'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-coupon-intelligence',
                plugins_url(
                    'assets/admin/css/hashieban-coupon-intelligence.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-coupon-intelligence',
                plugins_url(
                    'assets/admin/js/hashieban-coupon-intelligence.js',
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
                'hashieban-inventory'
            ) !== false
        ) {
            wp_enqueue_style(
                'hashieban-inventory-intelligence',
                plugins_url(
                    'assets/admin/css/hashieban-inventory-intelligence.css',
                    HASHIEBAN_FILE
                ),
                array(
                    'hashieban-admin'
                ),
                HASHIEBAN_VERSION
            );

            wp_enqueue_script(
                'hashieban-inventory-intelligence',
                plugins_url(
                    'assets/admin/js/hashieban-inventory-intelligence.js',
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

        wp_enqueue_style(
            'hashieban-ui-polish',
            plugins_url(
                'assets/admin/css/hashieban-ui-polish.css',
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
            ! Capabilities::can(
                Capabilities::VIEW_REPORTS
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

                    <tr>
                        <th>دسترسی گزارش‌های حاشیه‌بان</th>
                        <td><?php echo Capabilities::can(Capabilities::VIEW_REPORTS) ? 'فعال' : 'غیرفعال'; ?></td>
                    </tr>

                    <tr>
                        <th>دسترسی مدیریت مالی</th>
                        <td><?php echo Capabilities::can(Capabilities::MANAGE_FINANCE) ? 'فعال' : 'غیرفعال'; ?></td>
                    </tr>

                    <tr>
                        <th>دسترسی ابزارهای گروهی</th>
                        <td><?php echo Capabilities::can(Capabilities::MANAGE_TOOLS) ? 'فعال' : 'غیرفعال'; ?></td>
                    </tr>

                    <tr>
                        <th>دسترسی تنظیمات حساس</th>
                        <td><?php echo Capabilities::can(Capabilities::MANAGE_SETTINGS) ? 'فعال' : 'غیرفعال'; ?></td>
                    </tr>

                </tbody>
            </table>

        </div>
        <?php
		}
		}
