<?php

declare(strict_types=1);

namespace Hashieban;

final class Plugin
{
    /**
     * Boot Hashieban.
     */
    public function boot(): void
    {
        /**
         * Fires after the base Hashieban bootstrap is complete.
         *
         * @param Plugin $plugin Current plugin instance.
         */
        do_action('hashieban_loaded', $this);
    }
}
