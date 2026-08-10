<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Capabilities;
use Hashieban\Domain\Money\Money;
use Hashieban\Integration\WooCommerce\OrderAdapter;
use Throwable;

final class OrderInspectorPage
{
    private OrderAdapter $orderAdapter;

    public function __construct(
        OrderAdapter $orderAdapter
    ) {
        $this->orderAdapter = $orderAdapter;
    }

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerPage']
        );
    }

    public function registerPage(): void
    {
        add_submenu_page(
            'hashieban',
            'بررسی مالی سفارش',
            'بررسی سفارش',
            'manage_woocommerce',
            'hashieban-order-inspector',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(
                esc_html__(
                    'شما اجازه دسترسی به این صفحه را ندارید.',
                    'hashieban'
                )
            );
        }

        $orderId = 0;

        if (isset($_GET['order_id'])) {
            $orderId = absint(
                wp_unslash($_GET['order_id'])
            );
        }

        ?>
        <div
            class="wrap"
            dir="rtl"
        >
            <h1>بررسی مالی سفارش</h1>

            <p>
                شناسه یک سفارش ووکامرس را وارد کنید تا اطلاعات مالی آن بررسی شود.
            </p>

            <form method="get">
                <input
                    type="hidden"
                    name="page"
                    value="hashieban-order-inspector"
                >

                <label for="hashieban-order-id">
                    <strong>شناسه سفارش:</strong>
                </label>

                <input
                    id="hashieban-order-id"
                    type="number"
                    name="order_id"
                    min="1"
                    value="<?php echo esc_attr((string) $orderId); ?>"
                    required
                >

                <?php
                submit_button(
                    'بررسی سفارش',
                    'primary',
                    '',
                    false
                );
                ?>
            </form>

            <?php
            if ($orderId > 0) {
                $this->renderOrder(
                    $orderId
                );
            }
            ?>
        </div>
        <?php
    }

    private function renderOrder(
        int $orderId
    ): void {
        try {
            $data = $this->orderAdapter->get(
                $orderId
            );
        } catch (Throwable $exception) {
            ?>
            <div class="notice notice-error inline">
                <p>
                    سفارش موردنظر قابل خواندن نیست.
                </p>
            </div>
            <?php

            return;
        }

        ?>
        <hr>

        <h2>
            اطلاعات مالی سفارش
            #<?php echo esc_html((string) $data->orderId()); ?>
        </h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <th>شناسه سفارش</th>
                    <td>
                        <?php echo esc_html((string) $data->orderId()); ?>
                    </td>
                </tr>

                <tr>
                    <th>وضعیت سفارش</th>
                    <td>
                        <?php echo esc_html($data->status()); ?>
                    </td>
                </tr>

                <tr>
                    <th>واحد پول</th>
                    <td>
                        <?php echo esc_html($data->currency()); ?>
                    </td>
                </tr>

                <tr>
                    <th>درآمد محصولات</th>
                    <td>
                        <?php
                        echo wp_kses_post(
                            $this->formatMoney(
                                $data->productRevenue()
                            )
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>مبلغ دریافتی بابت ارسال</th>
                    <td>
                        <?php
                        echo wp_kses_post(
                            $this->formatMoney(
                                $data->shippingRevenue()
                            )
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>کارمزدها و مبالغ مثبت سفارش</th>
                    <td>
                        <?php
                        echo wp_kses_post(
                            $this->formatMoney(
                                $data->positiveFees()
                            )
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>مبلغ مرجوع‌شده</th>
                    <td>
                        <?php
                        echo wp_kses_post(
                            $this->formatMoney(
                                $data->refundedRevenue()
                            )
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>بهای تمام‌شده کالاها</th>
                    <td>
                        <?php
                        echo wp_kses_post(
                            $this->formatMoney(
                                $data->cogs()
                            )
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>
                        <strong>درآمد قابل انتساب</strong>
                    </th>

                    <td>
                      <strong>
                        <?php
                        echo wp_kses_post(
                            $this->formatMoney(
                                $data->revenue()
                            )
                        );
                        ?>
                      </strong>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
		}

		private function formatMoney(
			Money $money
		): string {
			return wc_price(
				$money->toDecimalString(),
				[
					'currency' => $money->currency(),
					'decimals' => $money->precision(),
				]
			);
		}
		}
