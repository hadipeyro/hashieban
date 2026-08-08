<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;
use InvalidArgumentException;
use WC_Order;

final class DirectCostRepository
{
    private const META_KEY = '_hashieban_direct_costs';

    private MoneyFactory $moneyFactory;

    public function __construct(MoneyFactory $moneyFactory)
    {
        $this->moneyFactory = $moneyFactory;
    }

    public function getCosts(WC_Order $order): array
    {
        $stored = $order->get_meta(self::META_KEY, true, 'edit');

        if (! is_array($stored)) {
            return array();
        }

        $costs = array();

        foreach ($stored as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id'])
            ? sanitize_key((string) $row['id'])
                : '';

            $title = isset($row['title'])
            ? sanitize_text_field((string) $row['title'])
                   : '';

            $amount = isset($row['amount'])
            ? wc_format_decimal(
                (string) $row['amount'],
                wc_get_price_decimals()
            )
					: '';

            $note = isset($row['note'])
            ? sanitize_textarea_field((string) $row['note'])
                  : '';

            if ($title === '' || $amount === '') {
                continue;
            }

            $costs[] = array(
                'id' => $id,
                'title' => $title,
                'amount' => $amount,
                'note' => $note,
            );
        }

        return $costs;
    }

    public function saveCosts(WC_Order $order, array $rows): void
    {
        $currency = $order->get_currency();
        $precision = wc_get_price_decimals();

        $costs = array();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = sanitize_text_field(
                (string) ($row['title'] ?? '')
            );

            $rawAmount = (string) ($row['amount'] ?? '');

            $note = sanitize_textarea_field(
                (string) ($row['note'] ?? '')
            );

            if ($title === '' || $rawAmount === '') {
                continue;
            }

            $amount = wc_format_decimal(
                $rawAmount,
                $precision
            );

            if ($amount === '') {
                continue;
            }

            try {
                $money = $this->moneyFactory->fromWooCommerceAmount(
                    $amount,
                    $currency,
                    $precision
                );
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            if ($money->isNegative()) {
                continue;
            }

            $id = sanitize_key(
                (string) ($row['id'] ?? '')
            );

            if ($id === '') {
                $id = str_replace(
                    '-',
                    '',
                    wp_generate_uuid4()
                );
            }

            $costs[] = array(
                'id' => $id,
                'title' => $title,
                'amount' => $amount,
                'note' => $note,
            );
        }

        if ($costs === array()) {
            $order->delete_meta_data(self::META_KEY);
        } else {
            $order->update_meta_data(
                self::META_KEY,
                $costs
            );
        }

        $order->save_meta_data();
    }

    public function total(WC_Order $order): Money
    {
        $currency = $order->get_currency();
        $precision = wc_get_price_decimals();

        $total = Money::zero(
            $currency,
            $precision
        );

        foreach ($this->getCosts($order) as $cost) {
            try {
                $money = $this->moneyFactory->fromWooCommerceAmount(
                    $cost['amount'],
                    $currency,
                    $precision
                );
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            $total = $total->add($money);
        }

        return $total;
    }
}
