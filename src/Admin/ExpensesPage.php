<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class ExpensesPage
{
    private StoreExpenseRepository $repository;

    private MoneyFactory $moneyFactory;

    private ExpenseCategoryRepository $categories;

    public function __construct(
        StoreExpenseRepository $repository,
        MoneyFactory $moneyFactory,
        ExpenseCategoryRepository $categories
    ) {
        $this->repository =
            $repository;

        $this->moneyFactory =
            $moneyFactory;

        $this->categories =
            $categories;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_add_expense',
            array($this, 'handleAdd')
        );

        add_action(
            'admin_post_hashieban_update_expense',
            array($this, 'handleUpdate')
        );

        add_action(
            'admin_post_hashieban_delete_expense',
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

        $currency =
            Currency::storeCode();

        $precision =
            Currency::precision();

        $currencyLabel =
            Currency::label(
                $currency
            );

        $categories =
            $this->categories
                ->active();

        $page =
            isset($_GET['paged'])
                ? max(
                    1,
                    absint($_GET['paged'])
                )
                : 1;

        $perPage = 30;

        $expenses =
            $this->repository
                ->paginate(
                    $page,
                    $perPage
                );

        $totalItems =
            $this->repository
                ->count();

        $totalPages =
            max(
                1,
                (int) ceil(
                    $totalItems
                    / $perPage
                )
            );

        $today =
            new DateTimeImmutable(
                'now',
                wp_timezone()
            );

        $editExpenseId =
            isset($_GET['edit_expense'])
                ? absint($_GET['edit_expense'])
                : 0;

        $editingExpense =
            $editExpenseId > 0
                ? $this->repository->find(
                    $editExpenseId
                )
                : null;

        $formTitle =
            is_array($editingExpense)
                ? (string) ($editingExpense['title'] ?? '')
                : '';

        $formCategoryId =
            is_array($editingExpense)
                ? (string) ($editingExpense['category_id'] ?? '')
                : '';

        $formAmount =
            is_array($editingExpense)
                ? Currency::minorToDisplayInput(
                    (int) ($editingExpense['amount_minor'] ?? 0),
                    (string) ($editingExpense['currency'] ?? $currency),
                    (int) ($editingExpense['precision_value'] ?? $precision)
                )
                : '';

        $formExpenseDate =
            JalaliDate::numeric(
                $today
            );

        if (
            is_array($editingExpense)
            && ! empty($editingExpense['expense_date'])
        ) {
            $storedDate =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $editingExpense['expense_date'],
                    wp_timezone()
                );

            if ($storedDate instanceof DateTimeImmutable) {
                $formExpenseDate =
                    JalaliDate::numeric(
                        $storedDate
                    );
            }
        }

        $formNote =
            is_array($editingExpense)
                ? (string) ($editingExpense['note'] ?? '')
                : '';

        $activeCategoryIds =
            array_values(
                array_filter(
                    array_map(
                        static function ($category): string {
                            return is_array($category)
                                ? (string) ($category['id'] ?? '')
                                : '';
                        },
                        $categories
                    )
                )
            );

        ?>
        <div class="wrap hashieban-finance-page">

            <div class="hashieban-finance-header">

                <div>
                    <h1>
                        هزینه‌های فروشگاه
                    </h1>

                    <p>
                        هزینه‌های عمومی فروشگاه که
                        در سود خالص بازه زمانی اعمال می‌شوند.
                    </p>
                </div>

                <div class="hashieban-currency-badge">
                    واحد مالی:
                    <strong>
                        <?php
                        echo esc_html(
                            $currencyLabel
                        );
                        ?>
                    </strong>
                </div>

            </div>

            <?php if (
                isset($_GET['hb_saved'])
            ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        هزینه ثبت و در محاسبات مالی اعمال شد.
                    </p>
                </div>

            <?php endif; ?>

            <?php if (
                isset($_GET['hb_updated'])
            ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        هزینه با موفقیت ویرایش شد و محاسبات مالی با مبلغ جدید به‌روزرسانی شدند.
                    </p>
                </div>

            <?php endif; ?>

            <?php if (
                isset($_GET['hb_deleted'])
            ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        هزینه حذف شد.
                    </p>
                </div>

            <?php endif; ?>

            <?php if (
                isset($_GET['hb_error'])
            ) : ?>

                <div class="notice notice-error is-dismissible">
                    <p>
                        اطلاعات هزینه معتبر نیست. لطفاً دوباره بررسی کنید.
                    </p>
                </div>

            <?php endif; ?>

            <div class="hashieban-expense-layout">

                <section
                    class="hashieban-finance-card"
                    id="hashieban-expense-form"
                >

                    <h2>
                        <?php
                        echo is_array($editingExpense)
                            ? 'ویرایش هزینه'
                            : 'ثبت هزینه جدید';
                        ?>
                    </h2>

                    <?php if (is_array($editingExpense)) : ?>
                        <p>
                            تغییرات این هزینه بلافاصله روی سود خالص بازه‌های مرتبط اعمال می‌شود.
                        </p>
                    <?php endif; ?>

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
                            value="<?php
                            echo esc_attr(
                                is_array($editingExpense)
                                    ? 'hashieban_update_expense'
                                    : 'hashieban_add_expense'
                            );
                            ?>"
                        >

                        <?php if (is_array($editingExpense)) : ?>

                            <input
                                type="hidden"
                                name="expense_id"
                                value="<?php echo esc_attr((string) $editExpenseId); ?>"
                            >

                            <?php
                            wp_nonce_field(
                                'hashieban_update_expense_' . $editExpenseId,
                                'hashieban_expense_nonce'
                            );
                            ?>

                        <?php else : ?>

                            <?php
                            wp_nonce_field(
                                'hashieban_add_expense',
                                'hashieban_expense_nonce'
                            );
                            ?>

                        <?php endif; ?>

                        <div class="hashieban-form-field">

                            <label>
                                عنوان هزینه
                            </label>

                            <input
                                type="text"
                                name="title"
                                value="<?php echo esc_attr($formTitle); ?>"
                                required
                                placeholder="مثلاً خرید پاکت پستی"
                            >

                        </div>

                        <div class="hashieban-form-field">

                            <label>
                                دسته هزینه
                            </label>

                            <select
                                name="category_id"
                                required
                            >

                                <?php
                                foreach (
                                    $categories
                                    as $category
                                ) :
                                    ?>

                                    <option
                                        value="<?php echo esc_attr($category['id']); ?>"
                                        <?php
                                        selected(
                                            $formCategoryId,
                                            (string) $category['id']
                                        );
                                        ?>
                                    >
                                        <?php
                                        echo esc_html(
                                            $category['name']
                                        );
                                        ?>
                                    </option>

                                <?php endforeach; ?>

                                <?php
                                if (
                                    is_array($editingExpense)
                                    && $formCategoryId !== ''
                                    && ! in_array(
                                        $formCategoryId,
                                        $activeCategoryIds,
                                        true
                                    )
                                ) :
                                    $historicalCategory =
                                        $this->categories->find(
                                            $formCategoryId
                                        );

                                    $historicalCategoryName =
                                        is_array($historicalCategory)
                                            ? (string) ($historicalCategory['name'] ?? '')
                                            : '';

                                    if ($historicalCategoryName === '') {
                                        $historicalCategoryName =
                                            (string) (
                                                $editingExpense['category']
                                                ?? 'دسته تاریخی'
                                            );
                                    }
                                    ?>

                                    <option
                                        value="<?php echo esc_attr($formCategoryId); ?>"
                                        selected
                                    >
                                        <?php
                                        echo esc_html(
                                            $historicalCategoryName
                                            . ' (غیرفعال)'
                                        );
                                        ?>
                                    </option>

                                <?php endif; ?>

                            </select>

                            <small>
                                <a
                                    href="<?php
                                    echo esc_url(
                                        admin_url(
                                            'admin.php?page=hashieban-expense-categories'
                                        )
                                    );
                                    ?>"
                                >
                                    + ساخت یا ویرایش دسته‌ها
                                </a>
                            </small>

                        </div>

                        <div class="hashieban-form-field">

                            <label>
                                مبلغ
                                (<?php echo esc_html($currencyLabel); ?>)
                            </label>

                            <input
                                type="number"
                                name="amount"
                                value="<?php echo esc_attr($formAmount); ?>"
                                min="0"
                                step="any"
                                required
                                inputmode="decimal"
                                placeholder="0"
                            >

                        </div>

                        <div class="hashieban-form-field">

                            <label>
                                تاریخ هزینه
                            </label>

                            <input
                                type="text"
                                name="expense_date"
                                value="<?php echo esc_attr($formExpenseDate); ?>"
                                required
                                autocomplete="off"
                                data-jdp
                            >

                        </div>

                        <div class="hashieban-form-field">

                            <label>
                                توضیح
                            </label>

                            <textarea
                                name="note"
                                rows="3"
                                placeholder="اختیاری"
                            ><?php echo esc_textarea($formNote); ?></textarea>

                        </div>

                        <button
                            type="submit"
                            class="button button-primary button-large"
                        >
                            <?php
                            echo is_array($editingExpense)
                                ? 'ذخیره تغییرات هزینه'
                                : 'ثبت و اعمال در سود خالص';
                            ?>
                        </button>

                        <?php if (is_array($editingExpense)) : ?>
                            <a
                                class="button button-large"
                                href="<?php
                                echo esc_url(
                                    admin_url(
                                        'admin.php?page=hashieban-expenses'
                                    )
                                );
                                ?>"
                            >
                                انصراف
                            </a>
                        <?php endif; ?>

                    </form>

                </section>

                <section class="hashieban-finance-card hashieban-expense-list-card">

                    <div class="hashieban-card-heading">
                        <div>
                            <h2>
                                دفتر هزینه‌ها
                            </h2>

                            <p>
                                <?php
                                echo esc_html(
                                    number_format_i18n(
                                        $totalItems
                                    )
                                );
                                ?>
                                هزینه ثبت‌شده
                            </p>
                        </div>
                    </div>

                    <?php if (
                        $expenses === array()
                    ) : ?>

                        <div class="hashieban-empty-state">
                            هنوز هزینه‌ای ثبت نشده است.
                        </div>

                    <?php else : ?>

                        <div class="hashieban-table-wrapper">

                            <table class="widefat striped hashieban-finance-table">

                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>عنوان</th>
                                        <th>دسته</th>
                                        <th>مبلغ</th>
                                        <th>توضیح</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php
                                    foreach (
                                        $expenses
                                        as $expense
                                    ) :
                                        ?>

                                        <?php
                                        $categoryId =
                                            (string) (
                                                $expense[
                                                    'category_id'
                                                ]
                                                ?? ''
                                            );

                                        $categoryName =
                                            $categoryId !== ''
                                                ? $this->categories
                                                    ->name(
                                                        $categoryId
                                                    )
                                                : (
                                                    $expense[
                                                        'category'
                                                    ]
                                                    ?: 'سایر'
                                                );

                                        $categoryColor =
                                            $categoryId !== ''
                                                ? $this->categories
                                                    ->color(
                                                        $categoryId
                                                    )
                                                : '#64748b';
                                        ?>

                                        <tr>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    JalaliDate::fromYmd(
                                                        $expense[
                                                            'expense_date'
                                                        ]
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?php
                                                    echo esc_html(
                                                        $expense[
                                                            'title'
                                                        ]
                                                    );
                                                    ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <span
                                                    style="
                                                        display:inline-block;
                                                        width:10px;
                                                        height:10px;
                                                        margin-left:6px;
                                                        border-radius:50%;
                                                        background:<?php echo esc_attr($categoryColor); ?>;
                                                    "
                                                ></span>

                                                <?php
                                                echo esc_html(
                                                    $categoryName
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    Currency::formatMinor(
                                                        (int) $expense[
                                                            'amount_minor'
                                                        ],
                                                        $expense[
                                                            'currency'
                                                        ],
                                                        (int) $expense[
                                                            'precision_value'
                                                        ]
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $expense['note']
                                                        ?: '—'
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                $editUrl =
                                                    add_query_arg(
                                                        array(
                                                            'page' =>
                                                                'hashieban-expenses',

                                                            'edit_expense' =>
                                                                (int) $expense['id'],
                                                        ),
                                                        admin_url(
                                                            'admin.php'
                                                        )
                                                    );

                                                $editUrl .=
                                                    '#hashieban-expense-form';

                                                $deleteUrl =
                                                    wp_nonce_url(
                                                        add_query_arg(
                                                            array(
                                                                'action' =>
                                                                    'hashieban_delete_expense',

                                                                'expense_id' =>
                                                                    (int) $expense[
                                                                        'id'
                                                                    ],
                                                            ),
                                                            admin_url(
                                                                'admin-post.php'
                                                            )
                                                        ),
                                                        'hashieban_delete_expense_'
                                                            . (int) $expense[
                                                                'id'
                                                            ]
                                                    );
                                                ?>

                                                <a
                                                    href="<?php echo esc_url($editUrl); ?>"
                                                    class="button button-small"
                                                    style="margin-left:6px;"
                                                >
                                                    ویرایش
                                                </a>

                                                <a
                                                    href="<?php echo esc_url($deleteUrl); ?>"
                                                    class="hashieban-danger-link"
                                                    onclick="return confirm('این هزینه حذف شود؟');"
                                                >
                                                    حذف
                                                </a>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                    <?php if (
                        $totalPages > 1
                    ) : ?>

                        <div class="tablenav">
                          <div class="tablenav-pages">

                            <?php
                            echo wp_kses_post(
                                paginate_links(
                                    array(
                                        'base' =>
                                            add_query_arg(
                                                'paged',
                                                '%#%',
                                                admin_url(
                                                    'admin.php?page=hashieban-expenses'
                                                )
                                            ),

                                        'current' =>
                                            $page,

                                        'total' =>
                                            $totalPages,
                                    )
                                )
                            );
                            ?>

                          </div>
                        </div>

                    <?php endif; ?>

                </section>

            </div>

        </div>
        <?php
		}

		public function handleAdd(): void
		{
			if (
				! current_user_can(
					'manage_woocommerce'
				)
			) {
				wp_die('Access denied.');
			}

			check_admin_referer(
				'hashieban_add_expense',
				'hashieban_expense_nonce'
			);

			$title = sanitize_text_field(
				wp_unslash(
					$_POST['title']
					?? ''
				)
			);

			$categoryId = sanitize_key(
				wp_unslash(
					$_POST['category_id']
					?? ''
				)
			);

			$category =
				$this->categories
					 ->find($categoryId);

			if (
				! $category
				|| empty($category['active'])
			) {
				$categoryId =
					$this->categories
						 ->fallbackId();

				$category =
					$this->categories
						 ->find($categoryId);
			}

			$rawAmount =
				sanitize_text_field(
					wp_unslash(
						$_POST['amount']
						?? ''
					)
				);

			$dateInput =
				sanitize_text_field(
					wp_unslash(
						$_POST['expense_date']
						?? ''
					)
				);

			$note =
				sanitize_textarea_field(
					wp_unslash(
						$_POST['note']
						?? ''
					)
				);

			$expenseDate =
				JalaliDate::parseInputToGregorianYmd(
					$dateInput
				);

			if (
				$title === ''
				|| $rawAmount === ''
				|| $expenseDate === null
			) {
				$this->redirectError();
			}

			$currency =
				Currency::storeCode();

			$precision =
				Currency::precision();

			$storeAmount =
				Currency::displayInputToStoreDecimal(
					$rawAmount,
					$currency,
					$precision
				);

			if ($storeAmount === '') {
				$this->redirectError();
			}

			$money =
				$this->moneyFactory
					 ->fromWooCommerceAmount(
						 $storeAmount,
						 $currency,
						 $precision
					 );

			if (
				$money->isZero()
				|| $money->isNegative()
			) {
				$this->redirectError();
			}

			$this->repository->add(
				$title,
				$categoryId,
				(string) (
					$category['name']
					?? 'سایر'
				),
				$money,
				$expenseDate,
				$note,
				get_current_user_id()
			);

			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-expenses&hb_saved=1'
				)
			);

			exit;
		}


		public function handleUpdate(): void
		{
			if (
				! current_user_can(
					'manage_woocommerce'
				)
			) {
				wp_die('Access denied.');
			}

			$expenseId =
				isset($_POST['expense_id'])
					? absint($_POST['expense_id'])
					: 0;

			if ($expenseId < 1) {
				$this->redirectError();
			}

			check_admin_referer(
				'hashieban_update_expense_' . $expenseId,
				'hashieban_expense_nonce'
			);

			$existing =
				$this->repository->find(
					$expenseId
				);

			if (! is_array($existing)) {
				$this->redirectError();
			}

			$title = sanitize_text_field(
				wp_unslash(
					$_POST['title']
					?? ''
				)
			);

			$categoryId = sanitize_key(
				wp_unslash(
					$_POST['category_id']
					?? ''
				)
			);

			$category =
				$this->categories
					 ->find($categoryId);

			$existingCategoryId =
				(string) (
					$existing['category_id']
					?? ''
				);

			/*
			 * یک هزینه تاریخی می‌تواند به دسته‌ای غیرفعال متصل باشد.
			 * در زمان ویرایش، همان دسته تاریخی را معتبر نگه می‌داریم؛
			 * اما انتخاب یک دسته غیرفعال دیگر مجاز نیست.
			 */
			if (
				! $category
				|| (
					empty($category['active'])
					&& $categoryId !== $existingCategoryId
				)
			) {
				$categoryId =
					$this->categories
						 ->fallbackId();

				$category =
					$this->categories
						 ->find($categoryId);
			}

			$rawAmount =
				sanitize_text_field(
					wp_unslash(
						$_POST['amount']
						?? ''
					)
				);

			$dateInput =
				sanitize_text_field(
					wp_unslash(
						$_POST['expense_date']
						?? ''
					)
				);

			$note =
				sanitize_textarea_field(
					wp_unslash(
						$_POST['note']
						?? ''
					)
				);

			$expenseDate =
				JalaliDate::parseInputToGregorianYmd(
					$dateInput
				);

			if (
				$title === ''
				|| $rawAmount === ''
				|| $expenseDate === null
			) {
				$this->redirectError();
			}

			$currency =
				Currency::storeCode();

			$precision =
				Currency::precision();

			$storeAmount =
				Currency::displayInputToStoreDecimal(
					$rawAmount,
					$currency,
					$precision
				);

			if ($storeAmount === '') {
				$this->redirectError();
			}

			$money =
				$this->moneyFactory
					 ->fromWooCommerceAmount(
						 $storeAmount,
						 $currency,
						 $precision
					 );

			if (
				$money->isZero()
				|| $money->isNegative()
			) {
				$this->redirectError();
			}

			$this->repository->update(
				$expenseId,
				$title,
				$categoryId,
				(string) (
					$category['name']
					?? (
						$existing['category']
						?? 'سایر'
					)
				),
				$money,
				$expenseDate,
				$note
			);

			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-expenses&hb_updated=1'
				)
			);

			exit;
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

			$expenseId =
				isset($_GET['expense_id'])
            ? absint(
                $_GET['expense_id']
            )
                : 0;

			if ($expenseId < 1) {
				$this->redirectError();
			}

			check_admin_referer(
				'hashieban_delete_expense_'
              . $expenseId
			);

			$this->repository->delete(
				$expenseId
			);

			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-expenses&hb_deleted=1'
				)
			);

			exit;
		}

		private function redirectError(): void
		{
			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-expenses&hb_error=1'
				)
			);

			exit;
		}
		}
