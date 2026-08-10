<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Licensing\LicenseManager;
use Hashieban\Licensing\LicenseStatus;
use Hashieban\Support\Currency;

final class SettingsPage
{
    private GlobalOrderCostRepository $globalCosts;
    private MoneyFactory $moneyFactory;
    private LicenseManager $licenseManager;

    public function __construct(
        GlobalOrderCostRepository $globalCosts,
        MoneyFactory $moneyFactory,
        LicenseManager $licenseManager
    ) {
        $this->globalCosts = $globalCosts;
        $this->moneyFactory = $moneyFactory;
        $this->licenseManager = $licenseManager;
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

        add_action(
            'admin_post_hashieban_activate_license',
            array(
                $this,
                'activateLicense'
            )
        );

        add_action(
            'admin_post_hashieban_recheck_license',
            array(
                $this,
                'recheckLicense'
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

        $licenseStatus =
            $this->licenseManager->status();

        $licenseState =
            $licenseStatus->state();

        $licenseLabels = array(
            LicenseStatus::ACTIVE => 'فعال و معتبر',
            LicenseStatus::GRACE => 'فعال موقت',
            LicenseStatus::DEVELOPMENT => 'حالت توسعه',
            LicenseStatus::INVALID => 'نامعتبر',
            LicenseStatus::ERROR => 'نیازمند بررسی',
            LicenseStatus::UNCONFIGURED => 'فعال نشده',
        );

        $licenseLabel =
            $licenseLabels[$licenseState]
            ?? 'نامشخص';

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

            <?php if (
                isset($_GET['license_checked'])
            ) : ?>

                <div class="notice <?php echo esc_attr($licenseStatus->isUsable() ? 'notice-success' : 'notice-warning'); ?> is-dismissible">
                    <p>
                        <?php
                        echo esc_html(
                            $licenseStatus->message() !== ''
                                ? $licenseStatus->message()
                                : 'وضعیت مجوز بررسی شد.'
                        );
                        ?>
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

                <section
                    class="hb-settings-card hb-license-card"
                    id="hb-license"
                >

                    <div class="hb-settings-card-head">

                        <div>
                            <h2>
                                مجوز و بروزرسانی
                            </h2>

                            <p>
                                مجوز خرید حاشیه‌بان برای همین دامنه ثبت می‌شود
                                و وضعیت آن به‌صورت دوره‌ای بررسی خواهد شد.
                            </p>
                        </div>

                        <span
                            class="hb-license-status hb-license-status-<?php echo esc_attr($licenseState); ?>"
                        >
                            <?php echo esc_html($licenseLabel); ?>
                        </span>

                    </div>

                    <div class="hb-license-summary">

                        <div>
                            <span>فروشگاه نرم‌افزاری</span>
                            <strong>
                                <?php
                                echo esc_html(
                                    $this->licenseManager->providerLabel()
                                );
                                ?>
                            </strong>
                        </div>

                        <div>
                            <span>دامنه این نصب</span>
                            <strong>
                                <?php
                                echo esc_html(
                                    $licenseStatus->domain() !== ''
                                        ? $licenseStatus->domain()
                                        : (string) wp_parse_url(
                                            home_url('/'),
                                            PHP_URL_HOST
                                        )
                                );
                                ?>
                            </strong>
                        </div>

                        <div>
                            <span>کد ذخیره‌شده</span>
                            <strong>
                                <?php
                                echo esc_html(
                                    $this->licenseManager->hasLicenseKey()
                                        ? $this->licenseManager->maskedLicenseKey()
                                        : 'هنوز ثبت نشده'
                                );
                                ?>
                            </strong>
                        </div>

                    </div>

                    <?php if (
                        $licenseStatus->message() !== ''
                    ) : ?>

                        <div class="hb-license-message">
                            <?php
                            echo esc_html(
                                $licenseStatus->message()
                            );
                            ?>
                        </div>

                    <?php endif; ?>

                    <?php if (
                        $this->licenseManager->isDevelopmentEnvironment()
                    ) : ?>

                        <div class="hb-license-dev-note">
                            <strong>محیط توسعه شناسایی شد.</strong>
                            روی localhost لایسنس اجباری نیست تا توسعه و تست حاشیه‌بان متوقف نشود.
                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                        class="hb-license-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="hashieban_activate_license"
                        >

                        <?php
                        wp_nonce_field(
                            'hashieban_activate_license'
                        );
                        ?>

                        <label>
                            <span>کد مجوز / کد خرید</span>
                            <input
                                type="text"
                                name="license_key"
                                value=""
                                autocomplete="off"
                                placeholder="<?php echo esc_attr($this->licenseManager->hasLicenseKey() ? 'برای جایگزینی، کد جدید را وارد کنید' : 'کد مجوز را وارد کنید'); ?>"
                            >
                        </label>

                        <?php if (
                            ! $this->licenseManager->marketplaceIsConfigured()
                        ) : ?>

                            <label>
                                <span>
                                    توکن محصول ژاکت
                                    <small>فقط برای آماده‌سازی نسخه فروش</small>
                                </span>
                                <input
                                    type="text"
                                    name="product_token"
                                    value=""
                                    autocomplete="off"
                                    placeholder="توکن محصول بعد از ساخت محصول در ژاکت"
                                >
                            </label>

                        <?php endif; ?>

                        <div class="hb-license-actions">
                            <button
                                class="button button-primary button-large"
                                type="submit"
                            >
                                فعال‌سازی مجوز
                            </button>
                        </div>

                    </form>

                    <?php if (
                        $this->licenseManager->hasLicenseKey()
                    ) : ?>

                        <form
                            method="post"
                            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                            class="hb-license-recheck-form"
                        >
                            <input
                                type="hidden"
                                name="action"
                                value="hashieban_recheck_license"
                            >

                            <?php
                            wp_nonce_field(
                                'hashieban_recheck_license'
                            );
                            ?>

                            <button
                                class="button"
                                type="submit"
                            >
                                بررسی دوباره وضعیت مجوز
                            </button>
                        </form>

                    <?php endif; ?>

                    <p class="hb-license-help">
                        حاشیه‌بان وضعیت مجوز را روزانه بررسی می‌کند.
                        اگر سرویس مجوز موقتاً در دسترس نباشد، آخرین وضعیت معتبر
                        تا ۷ روز حفظ می‌شود تا فروشگاه به خاطر قطعی شبکه دچار مشکل نشود.
                    </p>

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

		public function activateLicense(): void
		{
			if (
				! Capabilities::can(Capabilities::MANAGE_SETTINGS)
			) {
				wp_die('Access denied.');
			}

			check_admin_referer(
				'hashieban_activate_license'
			);

			$licenseKey =
				isset($_POST['license_key'])
					? (string) $_POST['license_key']
					: '';

			$productToken =
				isset($_POST['product_token'])
					? (string) $_POST['product_token']
					: '';

			$this->licenseManager->activate(
				$licenseKey,
				$productToken
			);

			$this->redirectLicense();
		}

		public function recheckLicense(): void
		{
			if (
				! Capabilities::can(Capabilities::MANAGE_SETTINGS)
			) {
				wp_die('Access denied.');
			}

			check_admin_referer(
				'hashieban_recheck_license'
			);

			$this->licenseManager->validateNow();

			$this->redirectLicense();
		}

		private function redirectLicense(): void
		{
			wp_safe_redirect(
				admin_url(
					'admin.php?page=hashieban-settings&license_checked=1#hb-license'
				)
			);

			exit;
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
