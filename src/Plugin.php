<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Admin\AdminMenu;
use Hashieban\Admin\CustomerProfitabilityPage;
use Hashieban\Admin\DashboardPage;
use Hashieban\Admin\ExpenseCategoriesPage;
use Hashieban\Admin\ExpensesPage;
use Hashieban\Admin\OrderCostsMetaBox;
use Hashieban\Admin\OrderProfitCenterPage;
use Hashieban\Admin\ProductProfitabilityPage;
use Hashieban\Admin\SettingsPage;
use Hashieban\Admin\TimeIntelligencePage;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Integration\WooCommerce\Analytics\CustomerProfitabilityService;
use Hashieban\Integration\WooCommerce\Analytics\ProductProfitabilityService;
use Hashieban\Integration\WooCommerce\Analytics\OrderProfitCenterService;
use Hashieban\Integration\WooCommerce\Analytics\TimeIntelligenceService;
use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Integration\WooCommerce\Refund\RefundEngine;

final class Plugin
{
    public function boot(): void
    {
        $compatibility =
            new Compatibility();

        $compatibility->register();

        $moneyFactory =
            new MoneyFactory();

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

        $orderAdapter =
            new OrderAdapter(
                $moneyFactory,
                $directCostRepository,
                $refundEngine
            );

        $analytics =
            new AnalyticsService(
                $orderAdapter,
                $storeExpenseRepository,
                $globalOrderCosts,
                $profitEngine,
                $directCostRepository,
                $expenseCategories
            );

        $dashboard =
            new DashboardPage(
                $analytics
            );

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

        $adminMenu =
            new AdminMenu(
                $compatibility,
                $dashboard,
                $productProfitabilityPage,
                $customerProfitabilityPage,
                $timeIntelligencePage,
                $orderProfitCenterPage,
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
