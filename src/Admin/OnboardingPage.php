<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\Performance\OrderMetricsIndexer;
use Hashieban\Support\Currency;

final class OnboardingPage
{
    private const DISMISSED_OPTION = 'hashieban_onboarding_dismissed';

    private Compatibility $compatibility;

    private OrderMetricsIndexer $orderMetricsIndexer;

    public function __construct(
        Compatibility $compatibility,
        OrderMetricsIndexer $orderMetricsIndexer
    ) {
        $this->compatibility = $compatibility;
        $this->orderMetricsIndexer = $orderMetricsIndexer;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_dismiss_onboarding',
            array($this, 'dismiss')
        );

        add_action(
            'admin_post_hashieban_show_onboarding',
            array($this, 'showAgain')
        );
    }

    public static function isDismissed(): bool
    {
        return (bool) get_option(
            self::DISMISSED_OPTION,
            false
        );
    }

    public static function dismissUrl(
        string $redirect = ''
    ): string {
        $url = add_query_arg(
            array(
                'action' => 'hashieban_dismiss_onboarding',
                'redirect' => $redirect,
            ),
            admin_url('admin-post.php')
        );

        return wp_nonce_url(
            $url,
            'hashieban_dismiss_onboarding'
        );
    }

    public function dismiss(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(
                esc_html('شما اجازه انجام این کار را ندارید.')
            );
        }

        check_admin_referer(
            'hashieban_dismiss_onboarding'
        );

        update_option(
            self::DISMISSED_OPTION,
            1,
            false
        );

        $redirect = isset($_GET['redirect'])
            ? sanitize_text_field(
                wp_unslash(
                    (string) $_GET['redirect']
                )
            )
            : '';

        if ($redirect === 'dashboard') {
            wp_safe_redirect(
                admin_url('admin.php?page=hashieban')
            );
            exit;
        }

        wp_safe_redirect(
            admin_url('admin.php?page=hashieban-onboarding')
        );
        exit;
    }

    public function showAgain(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(
                esc_html('شما اجازه انجام این کار را ندارید.')
            );
        }

        check_admin_referer(
            'hashieban_show_onboarding'
        );

        delete_option(
            self::DISMISSED_OPTION
        );

        wp_safe_redirect(
            admin_url('admin.php?page=hashieban-onboarding')
        );
        exit;
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(
                esc_html('شما اجازه دسترسی به این بخش را ندارید.')
            );
        }

        $steps = $this->steps();
        $done = 0;

        foreach ($steps as $step) {
            if (! empty($step['done'])) {
                $done++;
            }
        }

        $total = count($steps);
        $progress = $total > 0
            ? (int) round(($done / $total) * 100)
            : 100;
        ?>
        <div class="wrap hb-onboarding-page">
            <section class="hb-onboarding-hero">
                <div class="hb-onboarding-hero__copy">
                    <span class="hb-onboarding-eyebrow">شروع سریع حاشیه‌بان</span>
                    <h1>فروشگاه را برای گزارش دقیق آماده کن</h1>
                    <p>
                        این راهنما فقط موارد ضروری را بررسی می‌کند. سفارش‌های قبلی دست‌کاری یا مسدود نمی‌شوند
                        و هر چیزی که آماده باشد با تیک سبز مشخص می‌شود.
                    </p>
                </div>

                <div class="hb-onboarding-progress-card">
                    <div class="hb-onboarding-progress-card__top">
                        <span>آمادگی اولیه</span>
                        <strong><?php echo esc_html((string) $progress); ?>٪</strong>
                    </div>
                    <div class="hb-onboarding-progress" aria-hidden="true">
                        <span style="width: <?php echo esc_attr((string) $progress); ?>%"></span>
                    </div>
                    <small>
                        <?php echo esc_html(number_format_i18n($done)); ?> از <?php echo esc_html(number_format_i18n($total)); ?> مورد آماده است
                    </small>
                </div>
            </section>

            <section class="hb-onboarding-grid">
                <?php foreach ($steps as $index => $step) : ?>
                    <article class="hb-onboarding-step <?php echo ! empty($step['done']) ? 'is-done' : 'needs-action'; ?>">
                        <div class="hb-onboarding-step__number">
                            <?php if (! empty($step['done'])) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <?php echo esc_html((string) ($index + 1)); ?>
                            <?php endif; ?>
                        </div>

                        <div class="hb-onboarding-step__body">
                            <div class="hb-onboarding-step__head">
                                <h2><?php echo esc_html((string) $step['title']); ?></h2>
                                <span><?php echo ! empty($step['done']) ? 'آماده' : 'نیاز به بررسی'; ?></span>
                            </div>
                            <p><?php echo esc_html((string) $step['description']); ?></p>

                            <?php if (! empty($step['note'])) : ?>
                                <small><?php echo esc_html((string) $step['note']); ?></small>
                            <?php endif; ?>

                            <?php if (! empty($step['url']) && ! empty($step['action'])) : ?>
                                <a class="button <?php echo empty($step['done']) ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url((string) $step['url']); ?>">
                                    <?php echo esc_html((string) $step['action']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="hb-onboarding-grid" aria-label="واژه‌های ساده حاشیه‌بان">
                <article class="hb-onboarding-step is-done">
                    <div class="hb-onboarding-step__number"><span class="dashicons dashicons-cart"></span></div>
                    <div class="hb-onboarding-step__body">
                        <div class="hb-onboarding-step__head"><h2>فروش واقعی</h2><span>ساده</span></div>
                        <p>پولی که از سفارش‌ها می‌ماند بعد از درنظرگرفتن مرجوعی و بازگشت وجه.</p>
                    </div>
                </article>
                <article class="hb-onboarding-step is-done">
                    <div class="hb-onboarding-step__number"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="hb-onboarding-step__body">
                        <div class="hb-onboarding-step__head"><h2>هزینه خرید کالا</h2><span>ساده</span></div>
                        <p>همان مبلغی که برای تهیه یا خرید خودِ کالا پرداخت کرده‌اید.</p>
                    </div>
                </article>
                <article class="hb-onboarding-step is-done">
                    <div class="hb-onboarding-step__number"><span class="dashicons dashicons-chart-line"></span></div>
                    <div class="hb-onboarding-step__body">
                        <div class="hb-onboarding-step__head"><h2>درصد سود</h2><span>ساده</span></div>
                        <p>نشان می‌دهد از هر ۱۰۰ تومان فروش، تقریباً چند تومان سود باقی مانده است.</p>
                    </div>
                </article>
                <article class="hb-onboarding-step is-done">
                    <div class="hb-onboarding-step__number"><span class="dashicons dashicons-image-rotate"></span></div>
                    <div class="hb-onboarding-step__body">
                        <div class="hb-onboarding-step__head"><h2>مرجوعی و بازگشت وجه</h2><span>ساده</span></div>
                        <p>پولی که به مشتری برگردانده شده و در صورت برگشت واقعی کالا، اثر آن روی هزینه خرید هم اصلاح می‌شود.</p>
                    </div>
                </article>
            </section>

            <section class="hb-onboarding-finish">
                <div>
                    <span class="dashicons dashicons-chart-area"></span>
                    <div>
                        <strong><?php echo $progress === 100 ? 'آماده‌ای؛ برو سراغ تحلیل‌ها.' : 'لازم نیست همه‌چیز را یک‌جا انجام بدهی.'; ?></strong>
                        <p>
                            حاشیه‌بان با داده موجود کار می‌کند و هر بخش ناقص را در «سلامت داده» به شما نشان می‌دهد.
                        </p>
                    </div>
                </div>

                <div class="hb-onboarding-finish__actions">
                    <a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('admin.php?page=hashieban')); ?>">رفتن به پیشخوان</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hashieban-data-health')); ?>">بررسی سلامت داده</a>
                    <a class="hb-onboarding-dismiss" href="<?php echo esc_url(self::dismissUrl()); ?>">این راهنما دیگر در پیشخوان نمایش داده نشود</a>
                </div>
            </section>
        </div>
        <?php
    }

    private function steps(): array
    {
        $currency = Currency::storeCode();
        $isIranianCurrency = Currency::canUseToman($currency);
        $displayLabel = Currency::label($currency);

        return array(
            array(
                'title' => 'ووکامرس سازگار و آماده',
                'description' => 'نسخه ووکامرس باید با هسته مالی حاشیه‌بان سازگار باشد.',
                'done' => $this->compatibility->hasSupportedWooCommerceVersion(),
                'note' => defined('WC_VERSION')
                    ? 'نسخه فعلی ووکامرس: ' . WC_VERSION
                    : 'ووکامرس شناسایی نشد.',
                'url' => admin_url('plugins.php'),
                'action' => 'بررسی افزونه‌ها',
            ),
            array(
                'title' => 'هزینه خرید کالا',
                'description' => 'برای اینکه سود واقعی باشد، قابلیت بهای تمام‌شده ووکامرس باید فعال باشد.',
                'done' => $this->compatibility->isCogsEnabled(),
                'note' => 'پس از فعال‌سازی، بهای خرید هر محصول را در ویرایش همان محصول وارد کنید.',
                'url' => admin_url('admin.php?page=wc-settings&tab=advanced&section=features'),
                'action' => 'تنظیم هزینه خرید در ووکامرس',
            ),
            array(
                'title' => 'واحد نمایش پول',
                'description' => $isIranianCurrency
                    ? 'حاشیه‌بان مبالغ را برای کاربر ایرانی به شکل خوانا نمایش می‌دهد و واحد داخلی ووکامرس را تغییر نمی‌دهد.'
                    : 'واحد نمایش حاشیه‌بان با واحد اصلی فروشگاه هماهنگ است.',
                'done' => true,
                'note' => 'واحد فعلی نمایش: ' . $displayLabel,
                'url' => admin_url('admin.php?page=hashieban-settings'),
                'action' => 'تنظیم واحد نمایش',
            ),
            array(
                'title' => 'شهر و استان سفارش‌های جدید',
                'description' => 'برای سفارش‌های ایران، شهر و استان در ثبت سفارش جدید اجباری است تا نقشه فروش دقیق بماند.',
                'done' => true,
                'note' => 'سفارش‌های قدیمی ناقص خطا نمی‌گیرند و فقط در آمادگی جغرافیایی گزارش می‌شوند.',
                'url' => admin_url('admin.php?page=hashieban-geo'),
                'action' => 'دیدن نقشه فروش ایران',
            ),
            array(
                'title' => 'شاخص سریع گزارش‌ها',
                'description' => 'حاشیه‌بان گزارش‌های حجیم را با شاخص مالی سریع‌تر می‌کند و ساخت آن را خودکار انجام می‌دهد.',
                'done' => $this->orderMetricsIndexer->isReady(),
                'note' => $this->orderMetricsIndexer->isReady()
                    ? 'شاخص سریع آماده و فعال است.'
                    : 'در حال آماده‌سازی خودکار؛ تا تکمیل، گزارش‌ها از مسیر امن قبلی محاسبه می‌شوند.',
                'url' => admin_url('admin.php?page=hashieban-bulk-tools'),
                'action' => 'مشاهده وضعیت ابزارها',
            ),
        );
    }
}
