<?php

declare(strict_types=1);

namespace Hashieban\Admin;

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
            array($this, 'handleDelete')
        );
    }

    public function render(): void
    {
        if (
            ! current_user_can(
                'manage_woocommerce'
            )
        ) {
            wp_die('Access denied.');
        }

        $categories =
            $this->categories->active();

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
                        استفاده خواهد شد.
                    </p>
                </div>

                <div class="hb-category-count">
                    <strong>
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                count($categories)
                            )
                        );
                        ?>
                    </strong>
                    <span>دسته فعال</span>
                </div>
            </section>

            <?php if (
                isset($_GET['saved'])
            ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        دسته هزینه ذخیره شد.
                    </p>
                </div>

            <?php endif; ?>

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
                                دسته‌های فعلی
                            </h2>

                            <p>
                                اسم و رنگ هر دسته را
                                هر زمان خواستید تغییر دهید.
                            </p>
                        </div>
                    </div>

                    <div class="hb-category-grid">

                        <?php
                        foreach (
                            $categories
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
                                        $deleteUrl =
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
													  $deleteUrl
												  );
												  ?>"
                                            class="hb-category-delete"
                                            onclick="return confirm('این دسته از فهرست فعال حذف شود؟ هزینه‌های قبلی حذف نمی‌شوند.');"
                                        >
                                          حذف
                                        </a>

                                    </div>

                                </form>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            </div>

        </div>
        <?php
		}

		public function handleSave(): void
		{
			if (
				! current_user_can(
					'manage_woocommerce'
				)
			) {
				wp_die('Access denied.');
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
				$this->redirect();
			}

			$this->categories->save(
				$name,
				$color,
				$id
			);

			$this->redirect();
		}

		public function handleDelete(): void
		{
			if (
				! current_user_can(
					'manage_woocommerce'
				)
			) {
				wp_die('Access denied.');
			}

			$id = sanitize_key(
				wp_unslash(
					$_GET['category_id'] ?? ''
				)
			);

			if ($id === '') {
				$this->redirect();
			}

			check_admin_referer(
				'hashieban_delete_expense_category_'
              . $id
			);

			$this->categories->deactivate(
				$id
			);

			$this->redirect();
		}

		private function redirect(): void
		{
			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-expense-categories&saved=1'
				)
			);

			exit;
		}
		}
