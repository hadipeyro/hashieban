<?php

declare(strict_types=1);

namespace Hashieban\Finance;

final class ExpenseBudgetRepository
{
    private const OPTION_KEY =
        'hashieban_expense_budgets';

    public function all(): array
    {
        $stored = get_option(
            self::OPTION_KEY,
            array()
        );

        return is_array($stored)
            ? $stored
            : array();
    }

    public function monthlyBudgetMinor(
        string $categoryId,
        string $currency,
        int $precision
    ): int {
        $categoryId = sanitize_key($categoryId);
        $budgets = $this->all();

        if (
            $categoryId === ''
            || ! isset($budgets[$categoryId])
            || ! is_array($budgets[$categoryId])
        ) {
            return 0;
        }

        $row = $budgets[$categoryId];

        if (
            strtoupper((string) ($row['currency'] ?? ''))
                !== strtoupper($currency)
            || (int) ($row['precision'] ?? -1)
                !== $precision
        ) {
            return 0;
        }

        return max(
            0,
            (int) ($row['amount_minor'] ?? 0)
        );
    }

    public function save(
        string $categoryId,
        int $amountMinor,
        string $currency,
        int $precision
    ): void {
        $categoryId = sanitize_key($categoryId);

        if ($categoryId === '') {
            return;
        }

        $budgets = $this->all();

        if ($amountMinor <= 0) {
            unset($budgets[$categoryId]);
        } else {
            $budgets[$categoryId] = array(
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
                'precision' => $precision,
                'updated_at' => current_time('mysql'),
            );
        }

        update_option(
            self::OPTION_KEY,
            $budgets,
            false
        );
    }
}
