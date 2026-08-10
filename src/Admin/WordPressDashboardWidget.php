<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Analytics\AnalyticsService;
use Hashieban\Support\Currency;

final class WordPressDashboardWidget
{
    private const CACHE_KEY = 'hashieban_wp_dashboard_widget_v1';

    private AnalyticsService $analytics;

    public function __construct(
        AnalyticsService $analytics
    ) {
        $this->analytics = $analytics;
    }

    public function register(): void
    {
        add_action(
            'wp_dashboard_setup',
            array($this, 'registerWidget')
        );

        add_action(
            'admin_enqueue_scripts',
            array($this, 'enqueueAssets')
        );
    }

    public function registerWidget(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            return;
        }

        wp_add_dashboard_widget(
            'hashieban_store_pulse',
            'حاشیه‌بان — نبض مالی ۳۰ روز اخیر',
            array($this, 'render')
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }

        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            return;
        }

        wp_enqueue_style(
            'hashieban-wp-dashboard',
            plugins_url(
                'assets/admin/css/hashieban-wp-dashboard.css',
                HASHIEBAN_FILE
            ),
            array(),
            HASHIEBAN_VERSION
        );
    }

    public function render(): void
    {
        $data = get_transient(self::CACHE_KEY);

        if (! is_array($data)) {
            $end = new DateTimeImmutable(
                'now',
                wp_timezone()
            );

            $start = $end
                ->modify('-29 days')
                ->setTime(0, 0, 0);

            $data = $this->analytics->getSummary(
                $start,
                $end
            );

            set_transient(
                self::CACHE_KEY,
                $data,
                5 * MINUTE_IN_SECONDS
            );
        }

        $currency = (string) ($data['currency'] ?? get_woocommerce_currency());
        $precision = (int) ($data['precision'] ?? wc_get_price_decimals());
        $revenue = (int) ($data['revenue_minor'] ?? 0);
        $profit = (int) ($data['net_profit_minor'] ?? 0);
        $orders = (int) ($data['order_count'] ?? 0);
        $margin = $data['margin_percentage'] ?? null;
        $daily = array_slice((array) ($data['daily'] ?? array()), -12);

        $maxBar = 1;
        foreach ($daily as $bucket) {
            $maxBar = max(
                $maxBar,
                abs((int) ($bucket['profit_minor'] ?? 0))
            );
        }
        ?>
        <div class="hb-wp-widget">
            <div class="hb-wp-widget__profit <?php echo $profit < 0 ? 'is-loss' : 'is-profit'; ?>">
                <span>سود خالص</span>
                <strong>
                    <?php
                    echo esc_html(
                        Currency::formatMinor(
                            $profit,
                            $currency,
                            $precision
                        )
                    );
                    ?>
                </strong>
            </div>

            <div class="hb-wp-widget__stats">
                <div>
                    <span>فروش</span>
                    <strong><?php echo esc_html(Currency::formatMinor($revenue, $currency, $precision)); ?></strong>
                </div>
                <div>
                    <span>سفارش</span>
                    <strong><?php echo esc_html(number_format_i18n($orders)); ?></strong>
                </div>
                <div>
                    <span>حاشیه سود</span>
                    <strong>
                        <?php
                        echo $margin === null
                            ? '—'
                            : esc_html(number_format_i18n((float) $margin, 1) . '٪');
                        ?>
                    </strong>
                </div>
            </div>

            <?php if ($daily !== array()) : ?>
                <div class="hb-wp-widget__trend" aria-label="روند سود دوره‌های اخیر">
                    <?php foreach ($daily as $bucket) :
                        $value = (int) ($bucket['profit_minor'] ?? 0);
                        $height = max(8, (int) round((abs($value) / $maxBar) * 100));
                        ?>
                        <span
                            class="<?php echo $value < 0 ? 'is-negative' : 'is-positive'; ?>"
                            style="height: <?php echo esc_attr((string) $height); ?>%"
                            title="<?php echo esc_attr((string) ($bucket['label'] ?? '')); ?>"
                        ></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="hb-wp-widget__links">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=hashieban')); ?>">باز کردن حاشیه‌بان</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-alerts')); ?>">هشدارها</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-geo')); ?>">نقشه فروش</a>
            </div>

            <p class="hb-wp-widget__note">برای سبک ماندن پیشخوان، این خلاصه هر ۵ دقیقه به‌روزرسانی می‌شود.</p>
        </div>
        <?php
    }
}
