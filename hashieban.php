<?php
/**
 * Plugin Name:       حاشیه‌بان
 * Description:       هوش مالی، تحلیل سودآوری و گزارش‌های مدیریتی برای فروشگاه‌های ووکامرس.
 * Version:           0.8.0
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

define('HASHIEBAN_VERSION', '0.8.0');
define('HASHIEBAN_FILE', __FILE__);
define('HASHIEBAN_PATH', plugin_dir_path(__FILE__));

$hashieban_autoloader = HASHIEBAN_PATH . 'vendor/autoload.php';

if (! is_readable($hashieban_autoloader)) {
    add_action(
        'admin_notices',
        static function (): void {
            ?>
            <div class="notice notice-error">
              <p>
                <?php
                echo esc_html__(
                    'فایل‌های مورد نیاز حاشیه‌بان کامل نیستند. لطفاً افزونه را مجدداً نصب کنید.',
                    'hashieban'
                );
                ?>
              </p>
            </div>
<?php
}
);

return;
}

require_once $hashieban_autoloader;

add_action(
    'plugins_loaded',
    static function (): void {
        $plugin = new \Hashieban\Plugin();
        $plugin->boot();
    }
);
