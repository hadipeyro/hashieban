<?php

declare(strict_types=1);

namespace Hashieban;

use Hashieban\Integration\WooCommerce\Compatibility;

final class Plugin
{
    /**
     * Boot Hashieban.
     */
    public function boot(): void
    {
        $compatibility = new Compatibility();
        $compatibility->register();

        /**
         * Fires after the base Hashieban bootstrap is complete.
         *
         * @param Plugin $plugin Current plugin instance.
         */
        do_action('hashieban_loaded', $this);
    }
}
