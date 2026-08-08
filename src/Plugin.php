<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Admin\AdminMenu;
use Hashieban\Admin\DashboardPage;
use Hashieban\Admin\ExpensesPage;
use Hashieban\Admin\OrderCostsMetaBox;
use Hashieban\Admin\SettingsPage;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;

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

        $directCostRepository =
            new DirectCostRepository(
                $moneyFactory
            );

        $storeExpenseRepository =
            new StoreExpenseRepository();

        $globalOrderCosts =
            new GlobalOrderCostRepository();

        $storeExpenseRepository
            ->registerSchema();

        $orderCostsMetaBox =
            new OrderCostsMetaBox(
                $directCostRepository
            );

        $orderCostsMetaBox
            ->register();

        $expensesPage =
            new ExpensesPage(
                $storeExpenseRepository,
                $moneyFactory
            );

        $expensesPage->register();

        $settingsPage =
            new SettingsPage(
                $globalOrderCosts,
                $moneyFactory
            );

        $settingsPage->register();

        $orderAdapter =
            new OrderAdapter(
                $moneyFactory,
                $directCostRepository
            );

        $analytics =
            new AnalyticsService(
                $orderAdapter,
                $storeExpenseRepository,
                $globalOrderCosts,
                $profitEngine
            );

        $dashboard =
            new DashboardPage(
                $analytics
            );

        $adminMenu =
            new AdminMenu(
                $compatibility,
                $dashboard,
                $expensesPage,
                $settingsPage
            );

        $adminMenu->register();

        do_action(
            'hashieban_loaded',
            $this
        );
    }
}
