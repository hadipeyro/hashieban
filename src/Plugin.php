<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Admin\AdminMenu;
use Hashieban\Admin\OrderInspectorPage;
use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\OrderAdapter;
use Hashieban\Integration\WooCommerce\WooCommerceMoneyFactory;

final class Plugin
{
    public function boot(): void
    {
        $compatibility = new Compatibility();
        $compatibility->register();

        $adminMenu = new AdminMenu(
            $compatibility
        );

        $adminMenu->register();

        $moneyFactory = new WooCommerceMoneyFactory();

        $orderAdapter = new OrderAdapter(
            $moneyFactory
        );

        $orderInspectorPage = new OrderInspectorPage(
            $orderAdapter
        );

        $orderInspectorPage->register();

        do_action(
            'hashieban_loaded',
            $this
        );
    }
}
