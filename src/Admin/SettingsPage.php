<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Support\Currency;

final class SettingsPage
{
    private GlobalOrderCostRepository $globalCosts;
    private MoneyFactory $moneyFactory;

    public function __construct(
        GlobalOrderCostRepository $globalCosts,
        MoneyFactory $moneyFactory
    ) {
        $this->globalCosts = $globalCosts;
        $this->moneyFactory = $moneyFactory;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_save_display',
            array(
                $this,
                'saveDisplay'
            )
        );

        add_action(
            'admin_post_hashieban_save_global_costs',
            array(
                $this,
                'saveGlobalCosts'
            )
        );
    }

    public function render(): void
    {
        if (
            ! Capabilities::can(Capabilities::MANAGE_SETTINGS)
        ) {
            return;
        }

        $currency =
            Currency::storeCode();

        $precision =
            Currency::precision();

        $rules =
            $this->globalCosts->all();

        ?>
        <div class="wrap hb-settings-page">

            <div class="hb-settings-hero">

                <div>
                    <span class="hb-settings-eyebrow">
                        تنظیمات مالی
                    </span>

                    <h1>
                        تنظیمات حاشیه‌بان
                    </h1>

                    <p>
                        نحوه نمایش پول و هزینه‌هایی که
                        روی تمام سفارش‌ها اعمال می‌شوند.
                    </p>
                </div>

                <div class="hb-settings-unit">
                    واحد فعلی نمایش:
                    <strong>
                        <?php
                        echo esc_html(
                            Currency::label(
                                $currency
                            )
                        );
                        ?>
                    </strong>
                </div>

            </div>

            <?php if (
                isset($_GET['saved'])
            ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        تنظیمات با موفقیت ذخیره شد.
                    </p>
                </div>

            <?php endif; ?>

            <div class="hb-settings-grid">

                <section class="hb-settings-card">

                    <div class="hb-settings-icon">
                        تومان
                    </div>

                    <h2>
                        واحد نمایش مبالغ
                    </h2>

                    <p>
                        واحد داخلی WooCommerce تغییر نمی‌کند.
                        این تنظیم فقط ظاهر حاشیه‌بان و
                        ورودی هزینه‌ها را کنترل می‌کند.
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
                            value="hashieban_save_display"
                        >

                        <?php
                        wp_nonce_field(
                            'hashieban_save_display'
                        );
                        ?>

                        <?php if (
                            Currency::canUseToman(
                                $currency
                            )
                        ) : ?>

                            <label class="hb-radio-card">

                                <input
                                    type="radio"
                                    name="display_unit"
                                    value="toman"
                                    <?php
                                    checked(
                                        Currency::displayMode(
                                            $currency
                                        ),
                                        Currency::MODE_TOMAN
                                    );
                                    ?>
                                >

                                <span>
                                    <strong>
                                        تومان
                                    </strong>

                                    <small>
                                        پیشنهادشده برای کاربران ایرانی
                                    </small>
                                </span>

                            </label>

                        <?php endif; ?>

                        <label class="hb-radio-card">

                            <input
                                type="radio"
                                name="display_unit"
                                value="store"
                                <?php
                                checked(
                                    Currency::displayMode(
                                        $currency
                                    ),
                                    Currency::MODE_STORE
                                );
                                ?>
                            >

                            <span>
                                <strong>
                                    واحد اصلی WooCommerce
                                </strong>

                                <small>
                                    <?php
                                    echo esc_html(
                                        Currency::storeLabel(
                                            $currency
                                        )
                                    );
                                    ?>
                                </small>
                            </span>

                        </label>

                        <button
                            class="button button-primary button-large"
                            type="submit"
                        >
                            ذخیره واحد نمایش
                        </button>

                    </form>

                </section>

                <section class="hb-settings-card hb-global-cost-card">

                    <div class="hb-settings-card-head">

                        <div>
                            <h2>
                                هزینه ثابت همه سفارش‌ها
                            </h2>

                            <p>
                                این هزینه‌ها به‌صورت خودکار
                                روی هر سفارش معتبر اعمال می‌شوند.
                            </p>
                        </div>

                        <span class="hb-settings-badge">
                            <?php
                            echo esc_html(
                                Currency::label(
                                    $currency
                                )
                            );
                            ?>
                        </span>

                    </div>

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
                            value="hashieban_save_global_costs"
                        >

                        <?php
                        wp_nonce_field(
                            'hashieban_save_global_costs'
                        );
                        ?>

                        <div id="hb-global-cost-rows">

                            <?php
                            foreach (
                                $rules as $index => $rule
                            ) :
                                ?>

                                <div class="hb-global-cost-row">

                                    <input
                                        type="hidden"
                                        name="rules[<?php echo esc_attr((string) $index); ?>][id]"
                                        value="<?php
                                        echo esc_attr(
                                            (string) (
                                                $rule['id']
                                                ?? ''
                                            )
                                        );
                                        ?>"
                                    >

                                    <input
                                        type="text"
                                        name="rules[<?php echo esc_attr((string) $index); ?>][title]"
                                        value="<?php
                                        echo esc_attr(
                                            (string) (
                                                $rule['title']
                                                ?? ''
                                            )
                                        );
                                        ?>"
                                        placeholder="مثلاً چاپ لیبل"
                                    >

                                    <div class="hb-money-input">

                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            name="rules[<?php echo esc_attr((string) $index); ?>][amount]"
                                            value="<?php
                                            echo esc_attr(
                                                Currency::minorToDisplayInput(
                                                    (int) (
                                                        $rule[
                                                            'amount_minor'
                                                        ]
                                                        ?? 0
                                                    ),
                                                    (string) (
                                                        $rule[
                                                            'currency'
                                                        ]
                                                        ?? $currency
                                                    ),
                                                    (int) (
                                                        $rule[
                                                            'precision'
                                                        ]
                                                        ?? $precision
                                                    )
                                                )
                                            );
                                            ?>"
                                            placeholder="0"
                                        >

                                        <span>
                                            <?php
                                            echo esc_html(
                                                Currency::label(
                                                    $currency
                                                )
                                            );
                                            ?>
                                        </span>

                                    </div>

                                    <button
                                        type="button"
                                        class="button hb-remove-global-cost"
                                    >
                                        حذف
                                    </button>

                                </div>

                            <?php endforeach; ?>

                        </div>

                        <button
                            type="button"
                            class="button"
                            id="hb-add-global-cost"
                        >
                            + افزودن هزینه ثابت
                        </button>

                        <hr>

                        <button
                            class="button button-primary button-large"
                            type="submit"
                        >
                            ذخیره و اعمال روی سفارش‌ها
                        </button>

                    </form>

                </section>

            </div>

        </div>

        <template id="hb-global-cost-template">

            <div class="hb-global-cost-row">

                <input
                    type="hidden"
                    name="rules[__INDEX__][id]"
                    value=""
                >

                <input
                    type="text"
                    name="rules[__INDEX__][title]"
                    placeholder="مثلاً هزینه آماده‌سازی"
                >

                <div class="hb-money-input">

                    <input
                        type="number"
                        step="any"
                        min="0"
                        name="rules[__INDEX__][amount]"
                        placeholder="0"
                    >

                    <span>
                        <?php
                        echo esc_html(
                            Currency::label(
                                $currency
                            )
                        );
                        ?>
                    </span>

                </div>

                <button
                    type="button"
                    class="button hb-remove-global-cost"
                >
                  حذف
                </button>

            </div>

        </template>
        <?php
		}

		public function saveDisplay(): void
		{
			if (
				! Capabilities::can(Capabilities::MANAGE_SETTINGS)
			) {
				wp_die('Access denied.');
			}

			check_admin_referer(
				'hashieban_save_display'
			);

			$mode =
				sanitize_key(
					wp_unslash(
						$_POST[
							'display_unit'
						]
						?? ''
					)
				);

			Currency::setDisplayMode(
				$mode
			);

			$this->redirect();
		}

		public function saveGlobalCosts(): void
		{
			if (
				! Capabilities::can(Capabilities::MANAGE_SETTINGS)
			) {
				wp_die('Access denied.');
			}

			check_admin_referer(
				'hashieban_save_global_costs'
			);

			$currency =
				Currency::storeCode();

			$precision =
				Currency::precision();

			$posted =
				isset($_POST['rules'])
				&& is_array($_POST['rules'])
            ? wp_unslash(
                $_POST['rules']
            )
                : array();

			$rules = array();

			foreach ($posted as $row) {
				if (! is_array($row)) {
					continue;
				}

				$title =
					sanitize_text_field(
						(string) (
							$row['title']
							?? ''
						)
					);

				$amount =
					Currency::displayInputToStoreDecimal(
						(string) (
							$row['amount']
							?? ''
						),
						$currency,
						$precision
					);

				if (
					$title === ''
					|| $amount === ''
				) {
					continue;
				}

				$money =
					$this->moneyFactory
						 ->fromWooCommerceAmount(
							 $amount,
							 $currency,
							 $precision
						 );

				if (
					$money->isZero()
					|| $money->isNegative()
				) {
					continue;
				}

				$id =
					sanitize_key(
						(string) (
							$row['id']
							?? ''
						)
					);

				if ($id === '') {
					$id = str_replace(
						'-',
						'',
						wp_generate_uuid4()
					);
				}

				$rules[] = array(
					'id' => $id,
					'title' => $title,
					'amount_minor' =>
						$money->minorAmount(),
					'currency' =>
						$currency,
					'precision' =>
						$precision,
				);
			}

			$this->globalCosts->save(
				$rules
			);

			$this->redirect();
		}

		private function redirect(): void
		{
			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-settings&saved=1'
				)
			);

			exit;
		}
		}
