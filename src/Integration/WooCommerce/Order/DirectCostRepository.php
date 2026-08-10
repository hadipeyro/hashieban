<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Support\Currency;
use InvalidArgumentException;
use WC_Order;

final class DirectCostRepository
{
    private const META_KEY =
        '_hashieban_direct_costs';

    private MoneyFactory $moneyFactory;

    private ExpenseCategoryRepository $categories;

    public function __construct(
        MoneyFactory $moneyFactory,
        ExpenseCategoryRepository $categories
    ) {
        $this->moneyFactory =
            $moneyFactory;

        $this->categories =
            $categories;
    }

    public function getCosts(
        WC_Order $order
    ): array {
        $stored = $order->get_meta(
            self::META_KEY,
            true,
            'edit'
        );

        if (! is_array($stored)) {
            return array();
        }

        $costs = array();

        foreach ($stored as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = sanitize_text_field(
                (string) (
                    $row['title']
                    ?? ''
                )
            );

            $amount = wc_format_decimal(
                (string) (
                    $row['amount']
                    ?? ''
                ),
                wc_get_price_decimals()
            );

            if (
                $title === ''
                || $amount === ''
            ) {
                continue;
            }

            $categoryId = sanitize_key(
                (string) (
                    $row['category_id']
                    ?? ''
                )
            );

            if ($categoryId === '') {
                $categoryId =
                    $this->categories
                         ->fallbackId();
            }

            $costs[] = array(
                'id' =>
                    sanitize_key(
                        (string) (
                            $row['id']
                            ?? ''
                        )
                    ),

                'category_id' =>
                    $categoryId,

                'title' =>
                    $title,

                'amount' =>
                    $amount,

                'note' =>
                    sanitize_textarea_field(
                        (string) (
                            $row['note']
                            ?? ''
                        )
                    ),
            );
        }

        return $costs;
    }

    public function saveCosts(
        WC_Order $order,
        array $rows
    ): void {
        $currency =
            $order->get_currency();

        $precision =
            wc_get_price_decimals();

        $existingById = array();

        foreach (
            $this->getCosts($order)
            as $existingCost
        ) {
            $existingId = sanitize_key(
                (string) (
                    $existingCost['id']
                    ?? ''
                )
            );

            if ($existingId === '') {
                continue;
            }

            $existingById[$existingId] =
                $existingCost;
        }

        $costs = array();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = sanitize_text_field(
                (string) (
                    $row['title']
                    ?? ''
                )
            );

            $rawDisplayAmount =
                sanitize_text_field(
                    (string) (
                        $row['amount']
                        ?? ''
                    )
                );

            if (
                $title === ''
                || $rawDisplayAmount === ''
            ) {
                continue;
            }

            $storeAmount =
                Currency::displayInputToStoreDecimal(
                    $rawDisplayAmount,
                    $currency,
                    $precision
                );

            if ($storeAmount === '') {
                continue;
            }

            try {
                $money =
                    $this->moneyFactory
                         ->fromWooCommerceAmount(
                             $storeAmount,
                             $currency,
                             $precision
                         );
            } catch (
                InvalidArgumentException $exception
            ) {
                continue;
            }

            if ($money->isNegative()) {
                continue;
            }

            $id = sanitize_key(
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

            $previousCost =
                $existingById[$id]
            ?? null;

            $categoryId = sanitize_key(
                (string) (
                    $row['category_id']
                    ?? ''
                )
            );

            if (
                $categoryId === ''
                && is_array($previousCost)
            ) {
                $categoryId = sanitize_key(
                    (string) (
                        $previousCost['category_id']
                        ?? ''
                    )
                );
            }

            $category =
                $this->categories
                     ->find($categoryId);

            $isHistoricalCategory =
                is_array($previousCost)
                && $categoryId !== ''
                && $categoryId === sanitize_key(
                    (string) (
                        $previousCost['category_id']
                        ?? ''
                    )
                );

            if (! $category) {
                $categoryId =
                    $this->categories
                         ->fallbackId();
            } elseif (
                empty($category['active'])
                && ! $isHistoricalCategory
            ) {
                $categoryId =
                    $this->categories
                         ->fallbackId();
            }

            $costs[] = array(
                'id' => $id,

                'category_id' =>
                    $categoryId,

                'title' =>
                    $title,

                'amount' =>
                    $storeAmount,

                'note' =>
                    sanitize_textarea_field(
                        (string) (
                            $row['note']
                            ?? ''
                        )
                    ),
            );
        }

        if ($costs === array()) {
            $order->delete_meta_data(
                self::META_KEY
            );
        } else {
            $order->update_meta_data(
                self::META_KEY,
                $costs
            );
        }

        $order->save_meta_data();
    }

    public function total(
        WC_Order $order
    ): Money {
        $currency =
            $order->get_currency();

        $precision =
            wc_get_price_decimals();

        $total = Money::zero(
            $currency,
            $precision
        );

        foreach (
            $this->getCosts($order)
            as $cost
        ) {
            try {
                $money =
                    $this->moneyFactory
                         ->fromWooCommerceAmount(
                             $cost['amount'],
                             $currency,
                             $precision
                         );
            } catch (
                InvalidArgumentException $exception
            ) {
                continue;
            }

            $total = $total->add($money);
        }

        return $total;
    }

    public function totalsByCategory(
        WC_Order $order
    ): array {
        $currency =
            $order->get_currency();

        $precision =
            wc_get_price_decimals();

        $totals = array();

        foreach (
            $this->getCosts($order)
            as $cost
        ) {
            $categoryId =
                $cost['category_id'];

            try {
                $money =
                    $this->moneyFactory
                         ->fromWooCommerceAmount(
                             $cost['amount'],
                             $currency,
                             $precision
                         );
            } catch (
                InvalidArgumentException $exception
            ) {
                continue;
            }

            if (
                ! isset(
                    $totals[$categoryId]
                )
            ) {
                $totals[$categoryId] = 0;
            }

            $totals[$categoryId] +=
                $money->minorAmount();
        }

        return $totals;
    }
}
