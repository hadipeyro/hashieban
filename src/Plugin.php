<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Admin\AdminMenu;
use Hashieban\Integration\WooCommerce\Compatibility;

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

        do_action(
            'hashieban_loaded',
            $this
        );
    }
}
