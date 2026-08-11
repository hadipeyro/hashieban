<?php
/**
 * Plugin Name:       حاشیه‌بان
 * Description:       مدیریت مالی، تحلیل سودآوری و گزارش‌های مدیریتی برای فروشگاه‌های ووکامرس.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Hadi Peyro
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hashieban
 * Domain Path:       /languages
 * WC requires at least: 10.3
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('HASHIEBAN_VERSION', '1.0.0');
define('HASHIEBAN_FILE', __FILE__);
define('HASHIEBAN_PATH', plugin_dir_path(__FILE__));

if (! defined('HASHIEBAN_LICENSE_PRODUCT_TOKEN')) {
    define('HASHIEBAN_LICENSE_PRODUCT_TOKEN', '');
}

$hashieban_autoloader = HASHIEBAN_PATH . 'vendor/autoload.php';

if (is_readable($hashieban_autoloader)) {
    require_once $hashieban_autoloader;
} else {
    /*
     * Hashieban has no required third-party PHP runtime package. Keeping a
     * small internal PSR-4 fallback makes the marketplace ZIP installable
     * even when Composer's generated vendor directory is intentionally
     * omitted from the release package.
     */
    spl_autoload_register(
        static function (string $class): void {
            $prefix = 'Hashieban\\';

            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = HASHIEBAN_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';

            if (is_readable($file)) {
                require_once $file;
            }
        }
    );
}

add_action(
    'plugins_loaded',
    static function (): void {
        $plugin = new \Hashieban\Plugin();
        $plugin->boot();
    }
);
