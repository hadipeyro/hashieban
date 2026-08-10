<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Admin\AdminMenu;
use Hashieban\Admin\AnalyticsHubPage;
use Hashieban\Admin\BulkToolsPage;
use Hashieban\Admin\BusinessKpisPage;
use Hashieban\Admin\CustomerProfitabilityPage;
use Hashieban\Admin\MarginGuardPage;
use Hashieban\Admin\DashboardPage;
use Hashieban\Admin\DataHealthPage;
use Hashieban\Admin\ExpenseCategoriesPage;
use Hashieban\Admin\ExpenseIntelligencePage;
use Hashieban\Admin\GeoIntelligencePage;
use Hashieban\Admin\ExpensesPage;
use Hashieban\Admin\OrderCostsMetaBox;
use Hashieban\Admin\OrderProfitCenterPage;
use Hashieban\Admin\ProductProfitabilityPage;
use Hashieban\Admin\ReportsHubPage;
use Hashieban\Admin\SettingsPage;
use Hashieban\Admin\TimeIntelligencePage;
use Hashieban\Admin\WordPressDashboardWidget;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\ExpenseBudgetRepository;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Integration\WooCommerce\Analytics\BusinessKpiService;
use Hashieban\Integration\WooCommerce\Analytics\CustomerProfitabilityService;
use Hashieban\Integration\WooCommerce\Analytics\DataHealthService;
use Hashieban\Integration\WooCommerce\Analytics\ExpenseIntelligenceService;
use Hashieban\Integration\WooCommerce\Analytics\GeoIntelligenceService;
use Hashieban\Integration\WooCommerce\Analytics\MarginGuardService;
use Hashieban\Integration\WooCommerce\Analytics\ProductProfitabilityService;
use Hashieban\Integration\WooCommerce\Analytics\OrderProfitCenterService;
use Hashieban\Integration\WooCommerce\Analytics\ReportsHubService;
use Hashieban\Integration\WooCommerce\Analytics\TimeIntelligenceService;
use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\Geo\GeoAddressCapture;
use Hashieban\Integration\WooCommerce\Geo\GeoAddressResolver;
use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Integration\WooCommerce\Performance\OrderMetricsIndexer;
use Hashieban\Integration\WooCommerce\Performance\OrderMetricsRepository;
use Hashieban\Integration\WooCommerce\Performance\PerformanceIndexScheduler;
use Hashieban\Integration\WooCommerce\Tools\BulkToolsService;
use Hashieban\Integration\WooCommerce\Refund\RefundEngine;
use Hashieban\Integration\WooCommerce\Snapshot\ProfitSnapshotRepository;
use Hashieban\Integration\WooCommerce\Snapshot\ProfitSnapshotService;

final class Plugin
{
    public function boot(): void
    {
        $compatibility =
            new Compatibility();

        $compatibility->register();

        $moneyFactory =
            new MoneyFactory();

        $geoAddressResolver =
            new GeoAddressResolver();

        $geoAddressCapture =
            new GeoAddressCapture(
                $geoAddressResolver
            );

        $geoAddressCapture->register();

        $profitEngine =
            new ProfitEngine();

        $expenseCategories =
            new ExpenseCategoryRepository();

        $expenseCategories
            ->ensureDefaults();

        $directCostRepository =
            new DirectCostRepository(
                $moneyFactory,
                $expenseCategories
            );

        $storeExpenseRepository =
            new StoreExpenseRepository();

        $globalOrderCosts =
            new GlobalOrderCostRepository();

        $storeExpenseRepository
            ->registerSchema();

        $orderCostsMetaBox =
            new OrderCostsMetaBox(
                $directCostRepository,
                $expenseCategories
            );

        $orderCostsMetaBox->register();

        $expensesPage =
            new ExpensesPage(
                $storeExpenseRepository,
                $moneyFactory,
                $expenseCategories
            );

        $expensesPage->register();

        $expenseCategoriesPage =
            new ExpenseCategoriesPage(
                $expenseCategories
            );

        $expenseCategoriesPage->register();

        $settingsPage =
            new SettingsPage(
                $globalOrderCosts,
                $moneyFactory
            );

        $settingsPage->register();

        $refundEngine =
            new RefundEngine(
                $moneyFactory
            );

        $profitSnapshotRepository =
            new ProfitSnapshotRepository();

        $orderMetricsRepository =
            new OrderMetricsRepository();

        $orderMetricsRepository
            ->registerSchema();

        $orderAdapter =
            new OrderAdapter(
                $moneyFactory,
                $directCostRepository,
                $refundEngine,
                $profitSnapshotRepository
            );

        $profitSnapshots =
            new ProfitSnapshotService(
                $profitSnapshotRepository,
                $orderAdapter,
                $globalOrderCosts,
                $profitEngine
            );

        $profitSnapshots->register();

        $orderMetricsIndexer =
            new OrderMetricsIndexer(
                $orderMetricsRepository,
                $profitSnapshotRepository,
                $directCostRepository
            );

        $orderMetricsIndexer->register();

        $analytics =
            new AnalyticsService(
                $orderAdapter,
                $storeExpenseRepository,
                $globalOrderCosts,
                $profitEngine,
                $directCostRepository,
                $expenseCategories,
                $orderMetricsRepository
            );

        $dashboard =
            new DashboardPage(
                $analytics
            );

        $analyticsHubPage =
            new AnalyticsHubPage();

        $wordpressDashboardWidget =
            new WordPressDashboardWidget(
                $analytics
            );

        $wordpressDashboardWidget->register();

        $expenseBudgets =
            new ExpenseBudgetRepository();

        $expenseIntelligence =
            new ExpenseIntelligenceService(
                $analytics,
                $storeExpenseRepository,
                $expenseCategories,
                $expenseBudgets,
                $moneyFactory
            );

        $expenseIntelligencePage =
            new ExpenseIntelligencePage(
                $expenseIntelligence,
                $expenseCategories
            );

        $expenseIntelligencePage->register();

        $productProfitability =
            new ProductProfitabilityService(
                $moneyFactory,
                $refundEngine
            );

        $productProfitabilityPage =
            new ProductProfitabilityPage(
                $productProfitability
            );

        $customerProfitability =
            new CustomerProfitabilityService(
                $orderAdapter,
                $globalOrderCosts,
                $profitEngine
            );

        $customerProfitabilityPage =
            new CustomerProfitabilityPage(
                $customerProfitability
            );

        $businessKpis =
            new BusinessKpiService(
                $analytics,
                $customerProfitability
            );

        $businessKpisPage =
            new BusinessKpisPage(
                $businessKpis
            );

        $timeIntelligence =
            new TimeIntelligenceService(
                $orderAdapter,
                $storeExpenseRepository,
                $globalOrderCosts,
                $profitEngine
            );

        $timeIntelligencePage =
            new TimeIntelligencePage(
                $timeIntelligence
            );

        $orderProfitCenter =
            new OrderProfitCenterService(
                $orderAdapter,
                $globalOrderCosts,
                $profitEngine,
                $moneyFactory,
                $directCostRepository,
                $expenseCategories
            );

        $orderProfitCenterPage =
            new OrderProfitCenterPage(
                $orderProfitCenter
            );

        $marginGuard =
            new MarginGuardService(
                $productProfitability,
                $orderProfitCenter,
                $timeIntelligence
            );

        $marginGuardPage =
            new MarginGuardPage(
                $marginGuard
            );

        $reportsHub =
            new ReportsHubService(
                $productProfitability,
                $customerProfitability,
                $timeIntelligence,
                $orderProfitCenter
            );

        $reportsHubPage =
            new ReportsHubPage(
                $reportsHub
            );

        $reportsHubPage->register();

        $dataHealth =
            new DataHealthService(
                $orderAdapter,
                $productProfitability,
                $geoAddressResolver
            );

        $dataHealthPage =
            new DataHealthPage(
                $dataHealth
            );

        $geoIntelligence =
            new GeoIntelligenceService(
                $orderAdapter,
                $globalOrderCosts,
                $profitEngine,
                $geoAddressResolver,
                $moneyFactory
            );

        $geoIntelligencePage =
            new GeoIntelligencePage(
                $geoIntelligence
            );

        $bulkTools =
            new BulkToolsService(
                $compatibility,
                $geoAddressCapture,
                $profitSnapshots,
                $orderMetricsIndexer
            );

        $performanceIndexScheduler =
            new PerformanceIndexScheduler(
                $orderMetricsIndexer,
                $bulkTools
            );

        $performanceIndexScheduler->register();

        $bulkToolsPage =
            new BulkToolsPage(
                $bulkTools
            );

        $bulkToolsPage->register();

        $adminMenu =
            new AdminMenu(
                $compatibility,
                $dashboard,
                $analyticsHubPage,
                $businessKpisPage,
                $productProfitabilityPage,
                $customerProfitabilityPage,
                $timeIntelligencePage,
                $orderProfitCenterPage,
                $marginGuardPage,
                $reportsHubPage,
                $expenseIntelligencePage,
                $dataHealthPage,
                $geoIntelligencePage,
                $bulkToolsPage,
                $expensesPage,
                $expenseCategoriesPage,
                $settingsPage
            );

        $adminMenu->register();

        do_action(
            'hashieban_loaded',
            $this
        );
    }
}
