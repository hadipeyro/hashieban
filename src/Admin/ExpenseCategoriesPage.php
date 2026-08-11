<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use Hashieban\Finance\ExpenseCategoryRepository;

final class ExpenseCategoriesPage
{
    private ExpenseCategoryRepository $categories;

    public function __construct(
        ExpenseCategoryRepository $categories
    ) {
        $this->categories = $categories;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_save_expense_category',
            array($this, 'handleSave')
        );

        add_action(
            'admin_post_hashieban_delete_expense_category',
            array($this, 'handleDeactivate')
        );

        add_action(
            'admin_post_hashieban_activate_expense_category',
            array($this, 'handleActivate')
        );
    }

    public function render(): void
    {
        if (
            ! Capabilities::can(Capabilities::MANAGE_FINANCE)
        ) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        $activeCategories =
            $this->categories->active();

        $inactiveCategories =
            $this->categories->inactive();

        ?>
        <div class="wrap hb-category-page">

            <section class="hb-category-hero">
                <div>
                    <span class="hb-category-eyebrow">
                        مدیریت هزینه‌ها
                    </span>

                    <h1>
                        دسته‌بندی هزینه‌ها
                    </h1>

                    <p>
                        هر دسته یک نام و رنگ اختصاصی دارد.
                        همین رنگ در نمودارها و گزارش‌های حاشیه‌بان
                        استفاده می‌شود. غیرفعال‌سازی دسته،
                        هزینه‌های قبلی و تاریخچه آن را حذف نمی‌کند.
                    </p>
                </div>

                <div class="hb-category-count">
                    <strong>
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                count($activeCategories)
                            )
                        );
                        ?>
                    </strong>
                    <span>دسته فعال</span>
                </div>
            </section>

            <?php $this->renderNotice(); ?>

            <div class="hb-category-layout">

                <section class="hb-category-card">

                    <h2>
                        ساخت دسته جدید
                    </h2>

                    <p>
                        مثلاً پست، بسته‌بندی،
                        بیمه ارسال یا هر عنوان دلخواه.
                    </p>

                    <form
                        method="post"
                        action="<?php
                        echo esc_url(
                            admin_url(
                                'admin-post.php'
                            )
                        );
                        ?>"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="hashieban_save_expense_category"
                        >

                        <?php
                        wp_nonce_field(
                            'hashieban_save_expense_category'
                        );
                        ?>

                        <div class="hb-category-field">
                            <label>
                                نام دسته
                            </label>

                            <input
                                type="text"
                                name="name"
                                required
                                placeholder="مثلاً بیمه ارسال"
                            >
                        </div>

                        <div class="hb-category-field">

                            <label>
                                رنگ دسته
                            </label>

                            <div class="hb-color-picker-row">

                                <input
                                    type="color"
                                    name="color"
                                    value="#2563eb"
                                >

                                <span>
                                    این رنگ در نمودارها
                                    و گزارش‌ها نمایش داده می‌شود.
                                </span>

                            </div>

                        </div>

                        <button
                            type="submit"
                            class="button button-primary button-large"
                        >
                            ساخت دسته
                        </button>

                    </form>

                </section>

                <section class="hb-category-card hb-category-card--list">

                    <div class="hb-category-heading">
                        <div>
                            <h2>
                                دسته‌های فعال
                            </h2>

                            <p>
                                اسم و رنگ هر دسته را
                                هر زمان خواستید تغییر دهید.
                            </p>
                        </div>
                    </div>

                    <?php if ($activeCategories === array()) : ?>

                        <div class="hb-category-empty">
                            هنوز دسته فعالی وجود ندارد.
                            یک دسته جدید بسازید یا یکی از دسته‌های
                            غیرفعال را دوباره فعال کنید.
                        </div>

                    <?php else : ?>

                        <div class="hb-category-grid">

                            <?php
                            foreach (
                                $activeCategories
                                as $category
                            ) :
                                ?>

                                <div class="hb-category-item">

                                    <form
                                        method="post"
                                        action="<?php
                                        echo esc_url(
                                            admin_url(
                                                'admin-post.php'
                                            )
                                        );
                                        ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="hashieban_save_expense_category"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php
                                            echo esc_attr(
                                                $category['id']
                                            );
                                            ?>"
                                        >

                                        <?php
                                        wp_nonce_field(
                                            'hashieban_save_expense_category'
                                        );
                                        ?>

                                        <div class="hb-category-item-top">

                                            <span
                                                class="hb-category-preview"
                                                style="background: <?php
                                                echo esc_attr(
                                                    $category['color']
                                                );
                                                ?>;"
                                            ></span>

                                            <input
                                                type="text"
                                                name="name"
                                                value="<?php
                                                echo esc_attr(
                                                    $category['name']
                                                );
                                                ?>"
                                                required
                                            >

                                            <input
                                                type="color"
                                                name="color"
                                                value="<?php
                                                echo esc_attr(
                                                    $category['color']
                                                );
                                                ?>"
                                            >

                                        </div>

                                        <div class="hb-category-actions">

                                            <button
                                                type="submit"
                                                class="button"
                                            >
                                                ذخیره تغییرات
                                            </button>

                                            <?php
                                            $deactivateUrl =
                                                wp_nonce_url(
                                                    add_query_arg(
                                                        array(
                                                            'action' =>
                                                                'hashieban_delete_expense_category',

                                                            'category_id' =>
                                                                $category['id'],
                                                        ),
                                                        admin_url(
                                                            'admin-post.php'
                                                        )
                                                    ),
                                                    'hashieban_delete_expense_category_'
                                                        . $category['id']
                                                );
                                            ?>

                                            <a
                                                href="<?php
                                                echo esc_url(
                                                    $deactivateUrl
                                                );
                                                ?>"
                                                class="hb-category-delete"
                                                onclick="return confirm('این دسته غیرفعال شود؟ هزینه‌های قبلی، رنگ و تاریخچه آن حفظ می‌شوند.');"
                                            >
                                                غیرفعال‌سازی
                                            </a>

                                        </div>

                                    </form>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>

            </div>

            <?php if ($inactiveCategories !== array()) : ?>

                <section class="hb-category-card hb-category-card--inactive">

                    <div class="hb-category-heading">
                        <div>
                            <h2>
                                دسته‌های غیرفعال
                            </h2>

                            <p>
                                این دسته‌ها برای هزینه‌های جدید نمایش داده نمی‌شوند،
                                اما اطلاعات تاریخی و رنگ آن‌ها در گزارش‌های قبلی حفظ می‌شود.
                            </p>
                        </div>
                    </div>

                    <div class="hb-category-grid hb-category-grid--inactive">

                        <?php
                        foreach (
                            $inactiveCategories
                            as $category
                        ) :
                            ?>

                            <div class="hb-category-item hb-category-item--inactive">

                                <div class="hb-category-item-top hb-category-item-top--inactive">

                                    <span
                                        class="hb-category-preview"
                                        style="background: <?php
                                        echo esc_attr(
                                            $category['color']
                                        );
                                        ?>;"
                                    ></span>

                                    <div class="hb-category-inactive-name">
                                        <strong>
                                            <?php
                                            echo esc_html(
                                                $category['name']
                                            );
                                            ?>
                                        </strong>

                                        <span>
                                            غیرفعال — داده‌های قبلی محفوظ است
                                        </span>
                                    </div>

                                    <?php
                                    $activateUrl =
                                        wp_nonce_url(
                                            add_query_arg(
                                                array(
                                                    'action' =>
                                                        'hashieban_activate_expense_category',

                                                    'category_id' =>
                                                        $category['id'],
                                                ),
                                                admin_url(
                                                    'admin-post.php'
                                                )
                                            ),
                                            'hashieban_activate_expense_category_'
                                                . $category['id']
                                        );
                                    ?>

                                    <a
                                        href="<?php
                                        echo esc_url(
                                            $activateUrl
                                        );
                                        ?>"
                                        class="button hb-category-restore"
                                    >
                                        فعال‌سازی مجدد
                                    </a>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

        </div>
        <?php
    }

    public function handleSave(): void
    {
        if (
            ! Capabilities::can(Capabilities::MANAGE_FINANCE)
        ) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        check_admin_referer(
            'hashieban_save_expense_category'
        );

        $id = sanitize_key(
            wp_unslash(
                $_POST['id'] ?? ''
            )
        );

        $name = sanitize_text_field(
            wp_unslash(
                $_POST['name'] ?? ''
            )
        );

        $color = sanitize_text_field(
            wp_unslash(
                $_POST['color'] ?? ''
            )
        );

        if ($name === '') {
            $this->redirect('invalid');
        }

        $this->categories->save(
            $name,
            $color,
            $id
        );

        $this->redirect('saved');
    }

    public function handleDeactivate(): void
    {
        if (
            ! Capabilities::can(Capabilities::MANAGE_FINANCE)
        ) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        $id = sanitize_key(
            wp_unslash(
                $_GET['category_id'] ?? ''
            )
        );

        if ($id === '') {
            $this->redirect('invalid');
        }

        check_admin_referer(
            'hashieban_delete_expense_category_'
            . $id
        );

        $this->categories->deactivate(
            $id
        );

        $this->redirect('deactivated');
    }

    public function handleActivate(): void
    {
        if (
            ! Capabilities::can(Capabilities::MANAGE_FINANCE)
        ) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        $id = sanitize_key(
            wp_unslash(
                $_GET['category_id'] ?? ''
            )
        );

        if ($id === '') {
            $this->redirect('invalid');
        }

        check_admin_referer(
            'hashieban_activate_expense_category_'
            . $id
        );

        $this->categories->activate(
            $id
        );

        $this->redirect('activated');
    }

    private function renderNotice(): void
    {
        $status = sanitize_key(
            wp_unslash(
                $_GET['hb_status'] ?? ''
            )
        );

        $messages = array(
            'saved' => 'دسته هزینه ذخیره شد.',
            'deactivated' => 'دسته غیرفعال شد. اطلاعات تاریخی آن حفظ می‌شود.',
            'activated' => 'دسته دوباره فعال شد و برای هزینه‌های جدید قابل انتخاب است.',
            'invalid' => 'اطلاعات دسته کامل یا معتبر نبود.',
        );

        if (! isset($messages[$status])) {
            return;
        }

        $noticeClass =
            $status === 'invalid'
                ? 'notice notice-error is-dismissible'
                : 'notice notice-success is-dismissible';

        ?>
        <div class="<?php echo esc_attr($noticeClass); ?>">
            <p>
                <?php echo esc_html($messages[$status]); ?>
            </p>
        </div>
        <?php
    }

    private function redirect(
        string $status
    ): void {
        wp_safe_redirect(
            add_query_arg(
                'hb_status',
                $status,
                admin_url(
                    'admin.php?page=hashieban-expense-categories'
                )
            )
        );

        exit;
    }
}
