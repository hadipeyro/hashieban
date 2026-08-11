<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
final class AnalyticsHubPage
{
    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(
                esc_html('شما اجازه دسترسی به این بخش را ندارید.')
            );
        }

        $groups = array(
            array(
                'title' => 'تحلیل فروش و سود',
                'description' => 'گزارش‌های اصلی فروش، سود، مشتری، محصول و روند زمانی را از این بخش باز کنید.',
                'items' => array(
                    array(
                        'title' => 'نبض کسب‌وکار',
                        'description' => 'شاخص‌های مدیریتی، رشد، میانگین مبلغ سفارش، تکرار خرید، هزینه و امتیاز عملکرد.',
                        'icon' => 'dashicons-chart-area',
                        'url' => admin_url('admin.php?page=hashieban-kpis'),
                    ),
                    array(
                        'title' => 'مشتری‌ها از کجا می‌آیند؟',
                        'description' => 'ترب، ایمالز، گوگل، شبکه‌های اجتماعی، کمپین‌ها و ورود مستقیم را بر اساس فروش و سود مقایسه کنید.',
                        'icon' => 'dashicons-randomize',
                        'url' => admin_url('admin.php?page=hashieban-channels'),
                    ),
                    array(
                        'title' => 'تخفیف‌ها و کوپن‌ها',
                        'description' => 'ببینید هر کد تخفیف چقدر فروش ساخته، چه مقدار سود باقی گذاشته و کجا سفارش زیان‌ده ایجاد کرده است.',
                        'icon' => 'dashicons-tickets-alt',
                        'url' => admin_url('admin.php?page=hashieban-coupons'),
                    ),
                    array(
                        'title' => 'سودآوری محصولات',
                        'description' => 'فروش، سود، درصد سود و سهم هر محصول.',
                        'icon' => 'dashicons-products',
                        'url' => admin_url('admin.php?page=hashieban-products'),
                    ),
                    array(
                        'title' => 'موجودی و پیشنهاد خرید',
                        'description' => 'موجودی، سرعت فروش، نقطه سفارش و پیشنهاد تقریبی خرید مجدد.',
                        'icon' => 'dashicons-archive',
                        'url' => admin_url('admin.php?page=hashieban-inventory'),
                    ),
                    array(
                        'title' => 'سودآوری مشتریان',
                        'description' => 'مشتریان ارزشمند، تکرار خرید و سهم از سود.',
                        'icon' => 'dashicons-groups',
                        'url' => admin_url('admin.php?page=hashieban-customers'),
                    ),
                    array(
                        'title' => 'روند فروش در زمان',
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
                'title' => 'کنترل و مدیریت',
                'description' => 'هزینه‌ها، سلامت داده و ابزارهای مدیریتی را از یک مسیر مشخص در دسترس داشته باشید.',
                'items' => array(
                    array(
                        'title' => 'شروع سریع و راه‌اندازی',
                        'description' => 'بررسی چند دقیقه‌ای هزینه خرید، واحد پول، نقشه ایران و آمادگی گزارش‌ها.',
                        'icon' => 'dashicons-lightbulb',
                        'url' => admin_url('admin.php?page=hashieban-onboarding'),
                    ),
                    array(
                        'title' => 'تحلیل هزینه‌ها',
                        'description' => 'روند هزینه، بودجه و سهم دسته‌های هزینه.',
                        'icon' => 'dashicons-money-alt',
                        'url' => admin_url('admin.php?page=hashieban-expense-intelligence'),
                    ),
                    array(
                        'title' => 'سلامت داده',
                        'description' => 'هزینه خرید ناقص، داده مشکوک و آمادگی تحلیل.',
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
                    <span class="hb-analytics-hub__eyebrow">حاشیه‌بان · مرکز تحلیل‌ها</span>
                    <h1>گزارش‌ها و تحلیل‌های حاشیه‌بان</h1>
                    <p>
                        گزارش موردنیاز را انتخاب کنید. مسیر بالای هر صفحه کمک می‌کند همیشه بدانید کجا هستید و سریع برگردید.
                    </p>
                </div>

                <a class="hb-analytics-hub__primary" href="<?php echo esc_url(admin_url('admin.php?page=hashieban')); ?>">
                    بازگشت به پیشخوان
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
