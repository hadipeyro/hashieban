<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Integration\WooCommerce\Order\DirectCostRepository;
use Hashieban\Support\Currency;
use WC_Order;
use WP_Post;

final class OrderCostsMetaBox
{
    private DirectCostRepository $repository;

    public function __construct(
        DirectCostRepository $repository
    ) {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_action(
            'add_meta_boxes',
            array($this, 'addMetaBox')
        );

        add_action(
            'woocommerce_process_shop_order_meta',
            array($this, 'save'),
            20,
            2
        );

        add_action(
            'admin_enqueue_scripts',
            array($this, 'enqueueAssets')
        );
    }

    public function addMetaBox(): void
    {
        $screen = 'shop_order';

        if (
            function_exists(
                'wc_get_page_screen_id'
            )
        ) {
            $wooCommerceScreen =
                wc_get_page_screen_id(
                    'shop-order'
                );

            if ($wooCommerceScreen !== '') {
                $screen = $wooCommerceScreen;
            }
        }

        add_meta_box(
            'hashieban-direct-costs',
            'حاشیه‌بان — هزینه‌های جانبی سفارش',
            array($this, 'render'),
            $screen,
            'normal',
            'default'
        );
    }

    public function render(
        $postOrOrderObject
    ): void {
        $order = $this->resolveOrder(
            $postOrOrderObject
        );

        if (! $order instanceof WC_Order) {
            return;
        }

        $costs = $this->repository
            ->getCosts($order);

        $currencyLabel = Currency::label(
            $order->get_currency()
        );

        wp_nonce_field(
            'hashieban_save_direct_costs',
            'hashieban_direct_costs_nonce'
        );
        ?>

        <div class="hashieban-order-costs">

            <p class="hashieban-order-costs__description">
                هر هزینه‌ای که فقط مربوط به همین سفارش است
                وارد کنید. این مبالغ مستقیماً از سود این سفارش
                و سود فروشگاه کم می‌شوند.
            </p>

            <p>
                واحد مبلغ:
                <strong>
                    <?php
                    echo esc_html(
                        $currencyLabel
                    );
                    ?>
                </strong>
            </p>

            <div class="hashieban-direct-cost-summary">

                <span>
                    مجموع هزینه‌های جانبی سفارش
                </span>

                <strong>
                    <span
                        id="hashieban-direct-cost-total"
                    >
                        0
                    </span>

                    <?php
                    echo esc_html(
                        $currencyLabel
                    );
                    ?>
                </strong>

            </div>

            <div id="hashieban-direct-cost-rows">

                <?php
                foreach (
                    $costs
                    as $index => $cost
                ) :
                    ?>

                    <div class="hashieban-direct-cost-row">

                        <input
                            type="hidden"
                            name="hashieban_direct_costs[<?php echo esc_attr((string) $index); ?>][id]"
                            value="<?php echo esc_attr($cost['id']); ?>"
                        >

                        <div class="hashieban-direct-cost-field">

                            <label>
                                عنوان هزینه
                            </label>

                            <input
                                type="text"
                                name="hashieban_direct_costs[<?php echo esc_attr((string) $index); ?>][title]"
                                value="<?php echo esc_attr($cost['title']); ?>"
                                placeholder="مثلاً هزینه پست"
                            >

                        </div>

                        <div class="hashieban-direct-cost-field">

                            <label>
                                مبلغ
                                (<?php
                                echo esc_html(
                                    $currencyLabel
                                );
                                ?>)
                            </label>

                            <input
                                type="number"
                                class="hashieban-direct-cost-amount"
                                name="hashieban_direct_costs[<?php echo esc_attr((string) $index); ?>][amount]"
                                value="<?php echo esc_attr($cost['amount']); ?>"
                                min="0"
                                step="any"
                                inputmode="decimal"
                                placeholder="0"
                            >

                        </div>

                        <div class="hashieban-direct-cost-field">

                            <label>
                                توضیح
                            </label>

                            <input
                                type="text"
                                name="hashieban_direct_costs[<?php echo esc_attr((string) $index); ?>][note]"
                                value="<?php echo esc_attr($cost['note']); ?>"
                                placeholder="اختیاری"
                            >

                        </div>

                        <div class="hashieban-direct-cost-actions">

                            <button
                                type="button"
                                class="button hashieban-remove-direct-cost"
                            >
                                حذف
                            </button>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <button
                type="button"
                class="button button-secondary"
                id="hashieban-add-direct-cost"
            >
                + افزودن هزینه
            </button>

        </div>

        <template id="hashieban-direct-cost-template">

            <div class="hashieban-direct-cost-row">

                <input
                    type="hidden"
                    name="hashieban_direct_costs[__INDEX__][id]"
                    value=""
                >

                <div class="hashieban-direct-cost-field">

                    <label>
                        عنوان هزینه
                    </label>

                    <input
                        type="text"
                        name="hashieban_direct_costs[__INDEX__][title]"
                        placeholder="مثلاً بسته‌بندی"
                    >

                </div>

                <div class="hashieban-direct-cost-field">

                    <label>
                        مبلغ
                        (<?php
                        echo esc_html(
                            $currencyLabel
                        );
                        ?>)
                    </label>

                    <input
                        type="number"
                        class="hashieban-direct-cost-amount"
                        name="hashieban_direct_costs[__INDEX__][amount]"
                        min="0"
                        step="any"
                        inputmode="decimal"
                        placeholder="0"
                    >

                </div>

                <div class="hashieban-direct-cost-field">

                    <label>
                        توضیح
                    </label>

                    <input
                        type="text"
                        name="hashieban_direct_costs[__INDEX__][note]"
                        placeholder="اختیاری"
                    >

                </div>

                <div class="hashieban-direct-cost-actions">

                  <button
                      type="button"
                      class="button hashieban-remove-direct-cost"
                  >
                    حذف
                  </button>

                </div>

            </div>

        </template>

        <?php
		}

		public function save(
			int $orderId,
			$postOrOrderObject = null
		): void {
			if (
				! current_user_can(
					'manage_woocommerce'
				)
			) {
				return;
			}

			if (
				! isset(
					$_POST[
						'hashieban_direct_costs_nonce'
					]
				)
			) {
				return;
			}

			$nonce = sanitize_text_field(
				wp_unslash(
					$_POST[
						'hashieban_direct_costs_nonce'
					]
				)
			);

			if (
				! wp_verify_nonce(
					$nonce,
					'hashieban_save_direct_costs'
				)
			) {
				return;
			}

			$order = wc_get_order($orderId);

			if (! $order instanceof WC_Order) {
				return;
			}

			$rows = array();

			if (
				isset(
					$_POST[
						'hashieban_direct_costs'
					]
				)
				&& is_array(
					$_POST[
						'hashieban_direct_costs'
					]
				)
			) {
				$rows = wp_unslash(
					$_POST[
						'hashieban_direct_costs'
					]
				);
			}

			$this->repository->saveCosts(
				$order,
				$rows
			);
		}

		public function enqueueAssets(): void
		{
			$screen = get_current_screen();

			if (! $screen) {
				return;
			}

			$validScreens = array(
				'shop_order',
			);

			if (
				function_exists(
					'wc_get_page_screen_id'
				)
			) {
				$wcScreen =
					wc_get_page_screen_id(
						'shop-order'
					);

				if ($wcScreen !== '') {
					$validScreens[] = $wcScreen;
				}
			}

			if (
				! in_array(
					$screen->id,
					$validScreens,
					true
				)
			) {
				return;
			}

			wp_enqueue_style(
				'hashieban-order-costs',
				plugins_url(
					'assets/admin/css/order-costs.css',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION
			);

			wp_enqueue_script(
				'hashieban-order-costs',
				plugins_url(
					'assets/admin/js/order-costs.js',
					HASHIEBAN_FILE
				),
				array(),
				HASHIEBAN_VERSION,
				true
			);
		}

		private function resolveOrder(
			$object
		): ?WC_Order {
			if ($object instanceof WC_Order) {
				return $object;
			}

			if ($object instanceof WP_Post) {
				$order = wc_get_order(
					$object->ID
				);

				if ($order instanceof WC_Order) {
					return $order;
				}
			}

			return null;
		}
		}
