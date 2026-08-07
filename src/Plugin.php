<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Admin\AdminMenu;
use Hashieban\Admin\DashboardPage;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;

final class Plugin
{
    public function boot(): void
    {
        $compatibility = new Compatibility();

        $compatibility->register();

        $moneyFactory = new MoneyFactory();

        $orderAdapter = new OrderAdapter(
            $moneyFactory
        );

        $analytics = new AnalyticsService(
            $orderAdapter
        );

        $dashboard = new DashboardPage(
            $analytics
        );

        $adminMenu = new AdminMenu(
            $compatibility,
            $dashboard
        );

        $adminMenu->register();

        do_action(
            'hashieban_loaded',
            $this
        );
    }
}
