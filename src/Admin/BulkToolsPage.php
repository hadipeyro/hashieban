<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Tools\BulkToolsService;
use Hashieban\Support\Currency;

final class BulkToolsPage
{
    private BulkToolsService $tools;

    public function __construct(BulkToolsService $tools)
    {
        $this->tools = $tools;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_export_product_cogs',
            array($this, 'exportProductCogs')
        );

        add_action(
            'admin_post_hashieban_import_product_cogs',
            array($this, 'importProductCogs')
        );

        add_action(
            'admin_post_hashieban_geo_backfill',
            array($this, 'backfillGeo')
        );

        add_action(
            'admin_post_hashieban_profit_snapshot_backfill',
            array($this, 'backfillProfitSnapshots')
        );

        add_action(
            'admin_post_hashieban_performance_index_rebuild',
            array($this, 'rebuildPerformanceIndex')
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        $importResult = $this->pullResult('cogs_import');
        $geoResult = $this->pullResult('geo_backfill');
        $snapshotResult = $this->pullResult('profit_snapshot_backfill');
        $performanceResult = $this->pullResult('performance_index_rebuild');
        $performanceStatus = $this->tools->performanceIndexStatus();
        $currency = get_woocommerce_currency();
        $storeUnit = Currency::storeLabel($currency);
        ?>
        <div class="wrap hb-bulk-tools-page">
            <section class="hb-bulk-tools-hero">
                <div>
                    <span class="hb-bulk-tools-hero__eyebrow">حاشیه‌بان BI · Bulk Tools</span>
                    <h1>ابزارهای گروهی و مهاجرت داده</h1>
                    <p>
                        برای فروشگاه‌های قدیمی و دیتای حجیم، اصلاح تک‌به‌تک منطقی نیست.
                        این مرکز برای ورود و خروج گروهی COGS و آماده‌سازی امن سفارش‌های قدیمی برای تحلیل جغرافیایی ساخته شده است.
                    </p>
                </div>
                <div class="hb-bulk-tools-hero__badge">
                    <strong>Legacy Safe</strong>
                    <span>سفارش قدیمی مسدود نمی‌شود</span>
                </div>
            </section>

            <section class="hb-bulk-tools-notice">
                <strong>قاعده سازگاری با فروشگاه‌های قدیمی:</strong>
                اجباری بودن استان و شهر فقط برای Checkout جدید ایران اعمال می‌شود. سفارش‌های قبلی حتی اگر شهر یا استان نداشته باشند خطای ثبت/ویرایش نمی‌گیرند و فقط به‌صورت «آمادگی جغرافیایی ناقص» گزارش می‌شوند.
            </section>

            <?php $this->renderImportResult($importResult); ?>
            <?php $this->renderGeoResult($geoResult); ?>
            <?php $this->renderSnapshotResult($snapshotResult); ?>
            <?php $this->renderPerformanceResult($performanceResult); ?>

            <section class="hb-bulk-tools-grid">
                <article class="hb-bulk-tools-card hb-bulk-tools-card--accent">
                    <div class="hb-bulk-tools-card__icon">CSV</div>
                    <h2>مدیریت گروهی بهای تمام‌شده</h2>
                    <p>
                        فایل محصولات را دریافت کن، ستون <code>cogs_store_unit</code> را در Excel ویرایش کن و همان فایل را برگردان.
                        مبالغ این فایل بر اساس واحد واقعی ووکامرس هستند: <strong><?php echo esc_html($storeUnit); ?></strong>.
                    </p>

                    <div class="hb-bulk-tools-actions">
                        <a class="button button-secondary" href="<?php echo esc_url($this->exportCogsUrl()); ?>">
                            دریافت CSV محصولات و COGS
                        </a>
                    </div>

                    <form class="hb-bulk-tools-upload" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="hashieban_import_product_cogs">
                        <?php wp_nonce_field('hashieban_import_product_cogs'); ?>

                        <label for="hashieban-cogs-csv">فایل CSV ویرایش‌شده</label>
                        <input id="hashieban-cogs-csv" type="file" name="cogs_csv" accept=".csv,text/csv" required>

                        <button type="submit" class="button button-primary">اعمال گروهی COGS</button>
                    </form>

                    <div class="hb-bulk-tools-help">
                        <strong>امنیت Import:</strong>
                        ID محصول اولویت دارد؛ اگر ID وجود نداشته باشد SKU بررسی می‌شود. ردیف نامعتبر رد می‌شود و محصول دیگری تغییر نمی‌کند.
                    </div>
                </article>

                <article class="hb-bulk-tools-card">
                    <div class="hb-bulk-tools-card__icon">IR</div>
                    <h2>آماده‌سازی سفارش‌های قدیمی برای نقشه ایران</h2>
                    <p>
                        حاشیه‌بان آدرس Shipping را در اولویت و Billing را به‌عنوان fallback می‌خواند و داده موجود سفارش‌های قدیمی را به Snapshot استاندارد Geo تبدیل می‌کند.
                        چیزی که در سفارش قدیمی وجود ندارد ساخته یا حدس زده نمی‌شود.
                    </p>

                    <?php
                    $nextPage = is_array($geoResult) && ! empty($geoResult['next_page'])
                        ? (int) $geoResult['next_page']
                        : 1;
                    ?>

                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="hashieban_geo_backfill">
                        <input type="hidden" name="page_number" value="<?php echo esc_attr((string) $nextPage); ?>">
                        <?php wp_nonce_field('hashieban_geo_backfill'); ?>
                        <button type="submit" class="button button-primary">
                            <?php echo $nextPage > 1 ? 'ادامه پردازش سفارش‌های قدیمی' : 'شروع آماده‌سازی Geo'; ?>
                        </button>
                    </form>

                    <div class="hb-bulk-tools-help">
                        پردازش به‌صورت Batch صدتایی انجام می‌شود تا فروشگاه بزرگ در یک Request همه سفارش‌ها را لود نکند.
                    </div>
                </article>

                <article class="hb-bulk-tools-card hb-bulk-tools-card--accent">
                    <div class="hb-bulk-tools-card__icon">PRO</div>
                    <h2>قفل کردن سود تاریخی سفارش‌ها</h2>
                    <p>
                        برای سفارش‌های قدیمی یک Snapshot نسخه‌دار از درآمد، COGS، هزینه‌های همان سفارش،
                        هزینه ثابت سفارش، Refund و سود ثبت می‌شود تا تغییر تنظیمات یا قیمت خرید آینده
                        گزارش تاریخی را بازنویسی نکند.
                    </p>

                    <?php
                    $snapshotNextPage = is_array($snapshotResult) && ! empty($snapshotResult['next_page'])
                        ? (int) $snapshotResult['next_page']
                        : 1;
                    ?>

                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="hashieban_profit_snapshot_backfill">
                        <input type="hidden" name="page_number" value="<?php echo esc_attr((string) $snapshotNextPage); ?>">
                        <?php wp_nonce_field('hashieban_profit_snapshot_backfill'); ?>
                        <button type="submit" class="button button-primary">
                            <?php echo $snapshotNextPage > 1 ? 'ادامه ساخت Snapshotهای مالی' : 'شروع قفل کردن سود تاریخی'; ?>
                        </button>
                    </form>

                    <div class="hb-bulk-tools-help">
                        فقط سفارش‌های processing/completed/refunded بررسی می‌شوند. Snapshot موجود دوباره ساخته نمی‌شود و پردازش Batch صدتایی است.
                    </div>
                </article>

                <article class="hb-bulk-tools-card">
                    <div class="hb-bulk-tools-card__icon">FAST</div>
                    <h2>شاخص سریع گزارش‌های مالی</h2>
                    <p>
                        این قابلیت از این نسخه به‌صورت خودکار فعال است. حاشیه‌بان شاخص مالی سفارش‌های قدیمی را در پس‌زمینه و به‌صورت Batch می‌سازد
                        و تا زمان تکمیل، گزارش‌ها را با مسیر قبلی و امن محاسبه می‌کند. اطلاعات اصلی سفارش تغییر نمی‌کند.
                    </p>

                    <?php
                    $performanceNextPage = is_array($performanceResult) && ! empty($performanceResult['next_page'])
                        ? (int) $performanceResult['next_page']
                        : 1;
                    $indexReady = ! empty($performanceStatus['ready']);
                    ?>

                    <div class="hb-bulk-tools-help">
                        وضعیت: <strong><?php echo $indexReady ? 'آماده و فعال' : 'در حال آماده‌سازی خودکار'; ?></strong>
                        · <?php echo esc_html(number_format_i18n((int) ($performanceStatus['indexed_total'] ?? 0))); ?> سفارش ایندکس‌شده
                    </div>

                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="hashieban_performance_index_rebuild">
                        <input type="hidden" name="page_number" value="<?php echo esc_attr((string) $performanceNextPage); ?>">
                        <?php wp_nonce_field('hashieban_performance_index_rebuild'); ?>
                        <button type="submit" class="button button-primary">
                            <?php
                            if ($performanceNextPage > 1) {
                                echo 'ادامه ساخت شاخص سریع';
                            } elseif ($indexReady) {
                                echo 'بازسازی دستی شاخص';
                            } else {
                                echo 'اجرای دستی همین حالا';
                            }
                            ?>
                        </button>
                    </form>

                    <div class="hb-bulk-tools-help">
                        معمولاً نیازی به این دکمه نیست؛ ساخت و نگهداری شاخص خودکار است. این دکمه فقط برای بازسازی دستی یا عیب‌یابی باقی مانده است.
                    </div>
                </article>
            </section>

            <section class="hb-bulk-tools-card hb-bulk-tools-card--wide">
                <div class="hb-bulk-tools-card__header">
                    <div>
                        <h2>این ابزار چه چیزی را تغییر نمی‌دهد؟</h2>
                        <p>اصل تاریخچه مالی و سفارش‌ها باید دست‌نخورده بماند.</p>
                    </div>
                    <span class="hb-bulk-tools-chip">Non-destructive migration</span>
                </div>
                <div class="hb-bulk-tools-rules">
                    <div><strong>سفارش قدیمی</strong><span>به‌خاطر نبود شهر یا استان Block نمی‌شود.</span></div>
                    <div><strong>آدرس مشتری</strong><span>Backfill فقط داده موجود را Normalize می‌کند و آدرس را حدس نمی‌زند.</span></div>
                    <div><strong>COGS سفارش تاریخی</strong><span>Import این صفحه COGS محصول فعلی را تغییر می‌دهد؛ تاریخچه سفارش قبلی را بازنویسی نمی‌کند.</span></div>
                    <div><strong>HPOS</strong><span>سفارش‌ها فقط از API/CRUD ووکامرس خوانده و ذخیره می‌شوند.</span></div>
                </div>
            </section>
        </div>
        <?php
    }

    public function exportProductCogs(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه دریافت این فایل را ندارید.'));
        }

        check_admin_referer('hashieban_export_product_cogs');
        $this->tools->exportProductCogsCsv();
    }

    public function importProductCogs(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه تغییر COGS را ندارید.'));
        }

        check_admin_referer('hashieban_import_product_cogs');

        $result = array(
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        if (
            ! isset($_FILES['cogs_csv'])
            || ! is_array($_FILES['cogs_csv'])
            || (int) ($_FILES['cogs_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        ) {
            $result['errors'][] = 'فایل CSV معتبر دریافت نشد.';
        } else {
            $name = sanitize_file_name((string) ($_FILES['cogs_csv']['name'] ?? ''));
            $tmpName = (string) ($_FILES['cogs_csv']['tmp_name'] ?? '');
            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            if ($extension !== 'csv' || $tmpName === '' || ! is_uploaded_file($tmpName)) {
                $result['errors'][] = 'فقط فایل CSV مجاز است.';
            } else {
                $result = $this->tools->importProductCogsCsv($tmpName);
            }
        }

        $this->storeResult('cogs_import', $result);
        $this->redirectToPage();
    }

    public function backfillGeo(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه اجرای مهاجرت داده را ندارید.'));
        }

        check_admin_referer('hashieban_geo_backfill');

        $page = isset($_POST['page_number'])
            ? max(1, absint((string) $_POST['page_number']))
            : 1;

        $result = $this->tools->backfillGeoBatch($page, 100);
        $this->storeResult('geo_backfill', $result);
        $this->redirectToPage();
    }

    public function backfillProfitSnapshots(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه اجرای مهاجرت مالی را ندارید.'));
        }

        check_admin_referer('hashieban_profit_snapshot_backfill');

        $page = isset($_POST['page_number'])
            ? max(1, absint((string) $_POST['page_number']))
            : 1;

        $result = $this->tools->backfillProfitSnapshotsBatch(
            $page,
            100
        );

        $this->storeResult(
            'profit_snapshot_backfill',
            $result
        );

        $this->redirectToPage();
    }

    public function rebuildPerformanceIndex(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html('شما اجازه اجرای بازسازی شاخص را ندارید.'));
        }

        check_admin_referer('hashieban_performance_index_rebuild');

        $page = isset($_POST['page_number'])
            ? max(1, absint((string) $_POST['page_number']))
            : 1;

        $result = $this->tools
            ->rebuildOrderMetricsBatch(
                $page,
                100
            );

        $this->storeResult(
            'performance_index_rebuild',
            $result
        );

        $this->redirectToPage();
    }

    private function exportCogsUrl(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=hashieban_export_product_cogs'),
            'hashieban_export_product_cogs'
        );
    }

    private function redirectToPage(): void
    {
        wp_safe_redirect(
            admin_url('admin.php?page=hashieban-bulk-tools')
        );
        exit;
    }

    private function resultKey(string $type): string
    {
        return 'hashieban_' . $type . '_' . get_current_user_id();
    }

    private function storeResult(string $type, array $result): void
    {
        set_transient(
            $this->resultKey($type),
            $result,
            5 * MINUTE_IN_SECONDS
        );
    }

    private function pullResult(string $type): ?array
    {
        $key = $this->resultKey($type);
        $result = get_transient($key);

        if (! is_array($result)) {
            return null;
        }

        delete_transient($key);

        return $result;
    }

    private function renderImportResult(?array $result): void
    {
        if ($result === null) {
            return;
        }

        $hasErrors = (array) ($result['errors'] ?? array()) !== array();
        ?>
        <section class="hb-bulk-tools-result <?php echo $hasErrors ? 'hb-bulk-tools-result--warning' : 'hb-bulk-tools-result--success'; ?>">
            <h2>نتیجه Import COGS</h2>
            <div class="hb-bulk-tools-result__stats">
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['updated'] ?? 0))); ?></strong> به‌روزرسانی</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['unchanged'] ?? 0))); ?></strong> بدون تغییر</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['skipped'] ?? 0))); ?></strong> ردشده</span>
            </div>
            <?php if ($hasErrors) : ?>
                <ul>
                    <?php foreach ((array) $result['errors'] as $error) : ?>
                        <li><?php echo esc_html((string) $error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php
    }

    private function renderGeoResult(?array $result): void
    {
        if ($result === null) {
            return;
        }

        $nextPage = isset($result['next_page']) && $result['next_page'] !== null
            ? (int) $result['next_page']
            : null;
        ?>
        <section class="hb-bulk-tools-result hb-bulk-tools-result--success">
            <h2>نتیجه Batch جغرافیایی</h2>
            <div class="hb-bulk-tools-result__stats">
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['processed'] ?? 0))); ?></strong> پردازش‌شده</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['complete'] ?? 0))); ?></strong> ایرانِ کامل</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['incomplete'] ?? 0))); ?></strong> ایرانِ ناقص</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['non_iran'] ?? 0))); ?></strong> غیرایران</span>
            </div>
            <p>
                Batch <?php echo esc_html(number_format_i18n((int) ($result['page'] ?? 1))); ?> از
                <?php echo esc_html(number_format_i18n((int) ($result['max_pages'] ?? 1))); ?> انجام شد.
                <?php echo $nextPage !== null ? 'برای ادامه، دوباره دکمه ادامه پردازش را بزن.' : 'پردازش تمام سفارش‌های قابل دسترس تمام شد.'; ?>
            </p>
        </section>
        <?php
    }

    private function renderSnapshotResult(
        ?array $result
    ): void {
        if ($result === null) {
            return;
        }

        $nextPage =
            isset($result['next_page'])
            && $result['next_page'] !== null
        ? (int) $result['next_page']
            : null;
        ?>
        <section class="hb-bulk-tools-result hb-bulk-tools-result--success">
            <h2>نتیجه Batch سود تاریخی</h2>
            <div class="hb-bulk-tools-result__stats">
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['processed'] ?? 0))); ?></strong> بررسی‌شده</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['created'] ?? 0))); ?></strong> Snapshot جدید</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['existing'] ?? 0))); ?></strong> از قبل قفل‌شده</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['skipped'] ?? 0))); ?></strong> ردشده</span>
            </div>
            <p>
                Batch <?php echo esc_html(number_format_i18n((int) ($result['page'] ?? 1))); ?> از
                <?php echo esc_html(number_format_i18n((int) ($result['max_pages'] ?? 1))); ?> انجام شد.
                <?php echo $nextPage !== null ? 'برای ادامه، دوباره دکمه ادامه را بزن.' : 'Snapshot سفارش‌های قابل تحلیل کامل شد.'; ?>
            </p>
        </section>
        <?php
    }


    private function renderPerformanceResult(
        ?array $result
    ): void {
        if ($result === null) {
            return;
        }

        $nextPage = isset($result['next_page'])
            && $result['next_page'] !== null
        ? (int) $result['next_page']
            : null;

        $ready = ! empty($result['ready']);
        ?>
        <section class="hb-bulk-tools-result <?php echo $ready ? 'hb-bulk-tools-result--success' : 'hb-bulk-tools-result--warning'; ?>">
            <h2>نتیجه ساخت شاخص سریع</h2>
            <div class="hb-bulk-tools-result__stats">
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['processed'] ?? 0))); ?></strong> بررسی‌شده</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['indexed'] ?? 0))); ?></strong> ایندکس‌شده</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['indexed_total'] ?? 0))); ?></strong> مجموع شاخص</span>
                <span><strong><?php echo esc_html(number_format_i18n((int) ($result['skipped'] ?? 0))); ?></strong> ردشده</span>
            </div>
            <p>
                Batch <?php echo esc_html(number_format_i18n((int) ($result['page'] ?? 1))); ?> از
                <?php echo esc_html(number_format_i18n((int) ($result['max_pages'] ?? 1))); ?> انجام شد.
                <?php
                if ($nextPage !== null) {
                    echo 'برای ادامه، دوباره دکمه ادامه ساخت شاخص را بزن.';
                } elseif ($ready) {
                    echo 'شاخص کامل شد؛ داشبورد حاشیه‌بان از این لحظه از مسیر سریع استفاده می‌کند.';
                } else {
                    echo 'شاخص هنوز آماده نشده است.';
                }
                ?>
            </p>
        </section>
        <?php
    }

}
