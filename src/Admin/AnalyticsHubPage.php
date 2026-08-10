<?php

declare(strict_types=1);

namespace Hashieban\Admin;

final class AnalyticsHubPage
{
    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(
                esc_html('شما اجازه دسترسی به این بخش را ندارید.')
            );
        }

        $groups = array(
            array(
                'title' => 'فروش و سودآوری',
                'description' => 'ببینید چه چیزی می‌فروشد، چه کسی سود می‌سازد و چه زمانی عملکرد بهتر است.',
                'items' => array(
                    array(
                        'title' => 'نبض کسب‌وکار',
                        'description' => 'KPIهای مدیریتی، رشد، AOV، تکرار خرید، هزینه و امتیاز عملکرد.',
                        'icon' => 'dashicons-chart-area',
                        'url' => admin_url('admin.php?page=hashieban-kpis'),
                    ),
                    array(
                        'title' => 'سودآوری محصولات',
                        'description' => 'فروش، سود، حاشیه سود و سهم هر محصول.',
                        'icon' => 'dashicons-products',
                        'url' => admin_url('admin.php?page=hashieban-products'),
                    ),
                    array(
                        'title' => 'سودآوری مشتریان',
                        'description' => 'مشتریان ارزشمند، تکرار خرید و سهم از سود.',
                        'icon' => 'dashicons-groups',
                        'url' => admin_url('admin.php?page=hashieban-customers'),
                    ),
                    array(
                        'title' => 'تحلیل زمانی',
                        'description' => 'رشد و افت، بهترین روزها و مقایسه دوره‌ها.',
                        'icon' => 'dashicons-chart-line',
                        'url' => admin_url('admin.php?page=hashieban-time'),
                    ),
                    array(
                        'title' => 'گزارش‌های مدیریتی',
                        'description' => 'گزارش‌های یکپارچه و خروجی‌های مدیریتی.',
                        'icon' => 'dashicons-media-spreadsheet',
                        'url' => admin_url('admin.php?page=hashieban-reports'),
                    ),
                ),
            ),
            array(
                'title' => 'تصمیم‌گیری و کنترل',
                'description' => 'ریسک‌ها، هزینه‌ها و آمادگی داده را در یک نقطه بررسی کنید.',
                'items' => array(
                    array(
                        'title' => 'هوش هزینه‌ها',
                        'description' => 'روند هزینه، بودجه و سهم دسته‌های هزینه.',
                        'icon' => 'dashicons-money-alt',
                        'url' => admin_url('admin.php?page=hashieban-expense-intelligence'),
                    ),
                    array(
                        'title' => 'سلامت داده',
                        'description' => 'COGS ناقص، داده مشکوک و آمادگی تحلیل.',
                        'icon' => 'dashicons-shield-alt',
                        'url' => admin_url('admin.php?page=hashieban-data-health'),
                    ),
                    array(
                        'title' => 'دسته‌های هزینه',
                        'description' => 'مدیریت دسته‌ها و رنگ‌های مورد استفاده در گزارش‌ها.',
                        'icon' => 'dashicons-tag',
                        'url' => admin_url('admin.php?page=hashieban-expense-categories'),
                    ),
                    array(
                        'title' => 'وضعیت سیستم',
                        'description' => 'نسخه، WooCommerce، HPOS و وضعیت پایه محیط.',
                        'icon' => 'dashicons-admin-tools',
                        'url' => admin_url('admin.php?page=hashieban-status'),
                    ),
                ),
            ),
        );
        ?>
        <div class="wrap hb-analytics-hub">
            <section class="hb-analytics-hub__hero">
                <div>
                    <span class="hb-analytics-hub__eyebrow">Hashieban BI</span>
                    <h1>مرکز تحلیل‌های حاشیه‌بان</h1>
                    <p>
                        گزارش‌های تخصصی را از اینجا باز کنید؛ منوی کناری عمداً ساده نگه داشته شده تا حاشیه‌بان با بزرگ‌تر شدن شلوغ نشود.
                    </p>
                </div>

                <a class="hb-analytics-hub__primary" href="<?php echo esc_url(admin_url('admin.php?page=hashieban')); ?>">
                    بازگشت به پیشخوان حاشیه‌بان
                </a>
            </section>

            <section class="hb-analytics-hub__quick">
                <a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-orders')); ?>">
                    <span class="dashicons dashicons-cart"></span>
                    مرکز سفارش‌ها
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-alerts')); ?>">
                    <span class="dashicons dashicons-warning"></span>
                    هشدارهای مدیریتی
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-geo')); ?>">
                    <span class="dashicons dashicons-location-alt"></span>
                    نقشه فروش ایران
                </a>
            </section>

            <?php foreach ($groups as $group) : ?>
                <section class="hb-analytics-hub__section">
                    <header>
                        <h2><?php echo esc_html((string) $group['title']); ?></h2>
                        <p><?php echo esc_html((string) $group['description']); ?></p>
                    </header>

                    <div class="hb-analytics-hub__grid">
                        <?php foreach ((array) $group['items'] as $item) : ?>
                            <a class="hb-analytics-hub__card" href="<?php echo esc_url((string) $item['url']); ?>">
                                <span class="hb-analytics-hub__icon dashicons <?php echo esc_attr((string) $item['icon']); ?>"></span>
                                <span class="hb-analytics-hub__copy">
                                    <strong><?php echo esc_html((string) $item['title']); ?></strong>
                                    <small><?php echo esc_html((string) $item['description']); ?></small>
                                </span>
                                <span class="dashicons dashicons-arrow-left-alt2 hb-analytics-hub__arrow"></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
