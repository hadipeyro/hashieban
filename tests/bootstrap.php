<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$vendorAutoload = $root . '/vendor/autoload.php';

if (is_readable($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(
        static function (string $class) use ($root): void {
            $prefix = 'Hashieban\\';

            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';

            if (is_readable($file)) {
                require_once $file;
            }
        }
    );
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value, int $flags = 0, int $depth = 512)
    {
        return json_encode($value, $flags, $depth);
    }
}
