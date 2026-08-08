<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use DateTimeImmutable;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Support\Currency;

final class ExpensesPage
{
    private StoreExpenseRepository $repository;

    private MoneyFactory $moneyFactory;

    public function __construct(
        StoreExpenseRepository $repository,
        MoneyFactory $moneyFactory
    ) {
        $this->repository = $repository;
        $this->moneyFactory = $moneyFactory;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_add_expense',
            array($this, 'handleAdd')
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
            wp_die(
                esc_html__(
                    'شما اجازه دسترسی به این بخش را ندارید.',
                    'hashieban'
                )
            );
        }

        $currency = Currency::storeCode();
        $precision = Currency::precision();
        $currencyLabel = Currency::label(
            $currency
        );

        $page = isset($_GET['paged'])
            ? max(
                1,
                absint($_GET['paged'])
            )
            : 1;

        $perPage = 30;

        $expenses = $this->repository
            ->paginate(
                $page,
                $perPage
            );

        $totalItems =
            $this->repository->count();

        $totalPages = max(
            1,
            (int) ceil(
                $totalItems / $perPage
            )
        );

        ?>
        <div class="wrap hashieban-finance-page">

            <div class="hashieban-finance-header">
                <div>
                    <h1>هزینه‌های فروشگاه</h1>

                    <p>
                        هزینه‌هایی که در این بخش ثبت می‌شوند
                        مستقیماً از سود خالص فروشگاه کم می‌شوند.
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

            <?php
            if (
                isset($_GET['hb_saved'])
                && $_GET['hb_saved'] === '1'
            ) :
                ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        هزینه ثبت شد و در محاسبه سود خالص اعمال می‌شود.
                    </p>
                </div>

            <?php endif; ?>

            <?php
            if (
                isset($_GET['hb_deleted'])
                && $_GET['hb_deleted'] === '1'
            ) :
                ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        هزینه حذف شد.
                    </p>
                </div>

            <?php endif; ?>

            <?php
            if (
                isset($_GET['hb_error'])
                && $_GET['hb_error'] === '1'
            ) :
                ?>

                <div class="notice notice-error">
                    <p>
                        اطلاعات هزینه معتبر نیست.
                    </p>
                </div>

            <?php endif; ?>

            <div class="hashieban-expense-layout">

                <section class="hashieban-finance-card">

                    <h2>
                        ثبت هزینه جدید
                    </h2>

                    <p class="description">
                        مثال: خرید چسب، تبلیغات، حقوق،
                        اجاره، هزینه انبار، اینترنت،
                        نرم‌افزار یا هر هزینه دیگری.
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
                            value="hashieban_add_expense"
                        >

                        <?php
                        wp_nonce_field(
                            'hashieban_add_expense',
                            'hashieban_expense_nonce'
                        );
                        ?>

                        <div class="hashieban-form-field">
                            <label for="hb-expense-title">
                                عنوان هزینه
                            </label>

                            <input
                                id="hb-expense-title"
                                type="text"
                                name="title"
                                required
                                placeholder="مثلاً خرید چسب بسته‌بندی"
                            >
                        </div>

                        <div class="hashieban-form-field">
                            <label for="hb-expense-category">
                                دسته‌بندی
                            </label>

                            <input
                                id="hb-expense-category"
                                type="text"
                                name="category"
                                list="hb-expense-categories"
                                placeholder="مثلاً مواد مصرفی"
                            >

                            <datalist id="hb-expense-categories">
                                <option value="مواد مصرفی">
                                <option value="بسته‌بندی">
                                <option value="ارسال">
                                <option value="تبلیغات">
                                <option value="حقوق">
                                <option value="اجاره">
                                <option value="انبار">
                                <option value="مالیات">
                                <option value="کارمزد">
                                <option value="هاست و نرم‌افزار">
                                <option value="آب، برق و اینترنت">
                                <option value="سایر">
                            </datalist>
                        </div>

                        <div class="hashieban-form-field">
                            <label for="hb-expense-amount">
                                مبلغ
                                (<?php
                                echo esc_html(
                                    $currencyLabel
                                );
                                ?>)
                            </label>

                            <input
                                id="hb-expense-amount"
                                type="number"
                                name="amount"
                                min="0"
                                step="any"
                                required
                                inputmode="decimal"
                                placeholder="0"
                            >

                            <small>
                                مبلغ را با واحد
                                <strong>
                                    <?php
                                    echo esc_html(
                                        $currencyLabel
                                    );
                                    ?>
                                </strong>
                                وارد کنید.
                            </small>
                        </div>

                        <div class="hashieban-form-field">
                            <label for="hb-expense-date">
                                تاریخ هزینه
                            </label>

                            <input
                                id="hb-expense-date"
                                type="date"
                                name="expense_date"
                                value="<?php
                                echo esc_attr(
                                    current_time(
                                        'Y-m-d'
                                    )
                                );
                                ?>"
                                required
                            >
                        </div>

                        <div class="hashieban-form-field">
                            <label for="hb-expense-note">
                                توضیح
                            </label>

                            <textarea
                                id="hb-expense-note"
                                name="note"
                                rows="3"
                                placeholder="اختیاری"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="button button-primary button-large"
                        >
                            ثبت و اعمال در سود خالص
                        </button>

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

                    <?php if ($expenses === array()) : ?>

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
                                        <th>دسته‌بندی</th>
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

                                        <tr>
                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $expense[
                                                        'expense_date'
                                                    ]
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
                                                <?php
                                                echo esc_html(
                                                    $expense[
                                                        'category'
                                                    ] !== ''
                                                        ? $expense[
                                                            'category'
                                                        ]
                                                        : '—'
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
                                                    $expense[
                                                        'note'
                                                    ] !== ''
                                                        ? $expense[
                                                            'note'
                                                        ]
                                                        : '—'
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
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
                                                    class="hashieban-danger-link"
                                                    href="<?php
                                                    echo esc_url(
                                                        $deleteUrl
                                                    );
                                                    ?>"
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

                    <?php if ($totalPages > 1) : ?>

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
					$_POST['title'] ?? ''
				)
			);

			$category = sanitize_text_field(
				wp_unslash(
					$_POST['category'] ?? ''
				)
			);

			$rawAmount = sanitize_text_field(
				wp_unslash(
					$_POST['amount'] ?? ''
				)
			);

			$expenseDate = sanitize_text_field(
				wp_unslash(
					$_POST['expense_date'] ?? ''
				)
			);

			$note = sanitize_textarea_field(
				wp_unslash(
					$_POST['note'] ?? ''
				)
			);

			if (
				$title === ''
				|| $rawAmount === ''
				|| $expenseDate === ''
			) {
				$this->redirectError();
			}

			$date = DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				$expenseDate,
				wp_timezone()
			);

			if (
				! $date
				|| $date->format('Y-m-d')
                !== $expenseDate
			) {
				$this->redirectError();
			}

			$currency = Currency::storeCode();
			$precision = Currency::precision();

			$money =
				$this->moneyFactory
					 ->fromWooCommerceAmount(
						 $rawAmount,
						 $currency,
						 $precision
					 );

			if (
				$money->isNegative()
				|| $money->isZero()
			) {
				$this->redirectError();
			}

			$this->repository->add(
				$title,
				$category,
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

		public function handleDelete(): void
		{
			if (
				! current_user_can(
					'manage_woocommerce'
				)
			) {
				wp_die('Access denied.');
			}

			$expenseId = isset(
				$_GET['expense_id']
			)
            ? absint($_GET['expense_id'])
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
