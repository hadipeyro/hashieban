<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Finance\ExpenseBudgetRepository;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Support\Currency;

final class ExpenseIntelligenceService
{
    private AnalyticsService $analytics;
    private StoreExpenseRepository $storeExpenses;
    private ExpenseCategoryRepository $categories;
    private ExpenseBudgetRepository $budgets;
    private MoneyFactory $moneyFactory;

    public function __construct(
        AnalyticsService $analytics,
        StoreExpenseRepository $storeExpenses,
        ExpenseCategoryRepository $categories,
        ExpenseBudgetRepository $budgets,
        MoneyFactory $moneyFactory
    ) {
        $this->analytics = $analytics;
        $this->storeExpenses = $storeExpenses;
        $this->categories = $categories;
        $this->budgets = $budgets;
        $this->moneyFactory = $moneyFactory;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $current = $this->analytics->getDashboardData($start, $end);
        list($previousStart, $previousEnd) = $this->previousRange($start, $end);
        $previous = $this->analytics->getDashboardData($previousStart, $previousEnd);

        $currency = (string) $current['currency'];
        $precision = (int) $current['precision'];

        $categoryRows = $this->buildCategoryRows(
            (array) $current['expense_category_breakdown'],
            (array) $previous['expense_category_breakdown'],
            $start,
            $end,
            $currency,
            $precision
        );

        $trackedCategoryExpenses = 0;
        $periodBudget = 0;
        $overBudgetCount = 0;

        foreach ($categoryRows as $row) {
            $trackedCategoryExpenses += (int) $row['amount_minor'];
            $periodBudget += (int) $row['period_budget_minor'];

            if (! empty($row['over_budget'])) {
                $overBudgetCount++;
            }
        }

        $operatingExpenses =
            (int) $current['direct_costs_minor']
            + (int) $current['global_order_costs_minor']
            + (int) $current['store_expenses_minor'];

        $previousOperatingExpenses =
            (int) $previous['direct_costs_minor']
            + (int) $previous['global_order_costs_minor']
            + (int) $previous['store_expenses_minor'];

        $grossProfit = max(0, (int) $current['gross_profit_minor']);
        $revenue = max(0, (int) $current['revenue_minor']);

        $recurring = $this->storeExpenses->topRecurringTitlesBetween(
            $start,
            $end,
            $currency,
            8
        );

        $trend = $this->buildTrend((array) $current['daily']);
        $spikeDays = $this->buildSpikeDays($trend);

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'start' => $start,
            'end' => $end,
            'revenue_minor' => (int) $current['revenue_minor'],
            'gross_profit_minor' => (int) $current['gross_profit_minor'],
            'net_profit_minor' => (int) $current['net_profit_minor'],
            'cogs_minor' => (int) $current['cogs_minor'],
            'direct_costs_minor' => (int) $current['direct_costs_minor'],
            'global_order_costs_minor' => (int) $current['global_order_costs_minor'],
            'store_expenses_minor' => (int) $current['store_expenses_minor'],
            'operating_expenses_minor' => $operatingExpenses,
            'tracked_category_expenses_minor' => $trackedCategoryExpenses,
            'operating_expense_change_percentage' => $this->changePercentage(
                $operatingExpenses,
                $previousOperatingExpenses
            ),
            'expense_to_revenue_percentage' => $revenue > 0
                ? ($operatingExpenses / $revenue) * 100
                : null,
            'expense_to_gross_profit_percentage' => $grossProfit > 0
                ? ($operatingExpenses / $grossProfit) * 100
                : null,
            'period_budget_minor' => $periodBudget,
            'budget_utilization_percentage' => $periodBudget > 0
                ? ($trackedCategoryExpenses / $periodBudget) * 100
                : null,
            'budget_variance_minor' => $periodBudget - $trackedCategoryExpenses,
            'over_budget_count' => $overBudgetCount,
            'category_rows' => $categoryRows,
            'recurring_expenses' => $recurring,
            'trend' => $trend,
            'spike_days' => $spikeDays,
            'top_category' => $this->topCategory($categoryRows),
        );
    }

    public function saveMonthlyBudgets(array $rawBudgets): void
    {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        foreach ($this->categories->all() as $category) {
            if (! is_array($category)) {
                continue;
            }

            $categoryId = sanitize_key((string) ($category['id'] ?? ''));

            if ($categoryId === '') {
                continue;
            }

            $raw = isset($rawBudgets[$categoryId])
                ? sanitize_text_field((string) $rawBudgets[$categoryId])
                : '';

            if ($raw === '') {
                $this->budgets->save(
                    $categoryId,
                    0,
                    $currency,
                    $precision
                );
                continue;
            }

            $storeDecimal = Currency::displayInputToStoreDecimal(
                $raw,
                $currency,
                $precision
            );

            if ($storeDecimal === '') {
                continue;
            }

            try {
                $money = $this->moneyFactory->fromWooCommerceAmount(
                    $storeDecimal,
                    $currency,
                    $precision
                );
            } catch (\InvalidArgumentException $exception) {
                continue;
            }

            $this->budgets->save(
                $categoryId,
                max(0, $money->minorAmount()),
                $currency,
                $precision
            );
        }
    }

    public function monthlyBudgetMinor(
        string $categoryId,
        string $currency,
        int $precision
    ): int {
        return $this->budgets->monthlyBudgetMinor(
            $categoryId,
            $currency,
            $precision
        );
    }

    private function buildCategoryRows(
        array $currentRows,
        array $previousRows,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $currency,
        int $precision
    ): array {
        $currentById = array();
        $previousById = array();
        $metadata = array();

        foreach ($this->categories->all() as $category) {
            if (! is_array($category)) {
                continue;
            }

            $id = sanitize_key((string) ($category['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $metadata[$id] = array(
                'name' => (string) ($category['name'] ?? 'سایر'),
                'color' => (string) ($category['color'] ?? '#64748b'),
            );
        }

        foreach ($currentRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = sanitize_key((string) ($row['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $currentById[$id] = max(0, (int) ($row['amount_minor'] ?? 0));
            $metadata[$id] = array(
                'name' => (string) ($row['name'] ?? ($metadata[$id]['name'] ?? 'سایر')),
                'color' => (string) ($row['color'] ?? ($metadata[$id]['color'] ?? '#64748b')),
            );
        }

        foreach ($previousRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = sanitize_key((string) ($row['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $previousById[$id] = max(0, (int) ($row['amount_minor'] ?? 0));
            if (! isset($metadata[$id])) {
                $metadata[$id] = array(
                    'name' => (string) ($row['name'] ?? 'سایر'),
                    'color' => (string) ($row['color'] ?? '#64748b'),
                );
            }
        }

        $total = array_sum($currentById);
        $days = max(
            1,
            (int) floor(($end->getTimestamp() - $start->getTimestamp()) / DAY_IN_SECONDS) + 1
        );
        $monthlyFactor = $days / 30.436875;
        $result = array();

        foreach ($metadata as $id => $meta) {
            $amount = (int) ($currentById[$id] ?? 0);
            $previousAmount = (int) ($previousById[$id] ?? 0);
            $monthlyBudget = $this->budgets->monthlyBudgetMinor(
                $id,
                $currency,
                $precision
            );
            $periodBudget = (int) round($monthlyBudget * $monthlyFactor);
            $variance = $periodBudget - $amount;
            $utilization = $periodBudget > 0
                ? ($amount / $periodBudget) * 100
                : null;

            $result[] = array(
                'id' => $id,
                'name' => (string) $meta['name'],
                'color' => (string) $meta['color'],
                'amount_minor' => $amount,
                'share_percentage' => $total > 0
                    ? ($amount / $total) * 100
                    : null,
                'previous_amount_minor' => $previousAmount,
                'change_percentage' => $this->changePercentage($amount, $previousAmount),
                'monthly_budget_minor' => $monthlyBudget,
                'period_budget_minor' => $periodBudget,
                'budget_variance_minor' => $variance,
                'budget_utilization_percentage' => $utilization,
                'over_budget' => $periodBudget > 0 && $amount > $periodBudget,
            );
        }

        usort(
            $result,
            static function (array $left, array $right): int {
                $amountComparison = (int) $right['amount_minor'] <=> (int) $left['amount_minor'];

                if ($amountComparison !== 0) {
                    return $amountComparison;
                }

                return strcmp((string) $left['name'], (string) $right['name']);
            }
        );

        return $result;
    }

    private function topCategory(array $rows): ?array
    {
        foreach ($rows as $row) {
            if (is_array($row) && (int) ($row['amount_minor'] ?? 0) > 0) {
                return $row;
            }
        }

        return null;
    }

    private function buildTrend(array $rows): array
    {
        $result = array();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $direct = (int) ($row['direct_costs_minor'] ?? 0);
            $fixed = (int) ($row['global_order_costs_minor'] ?? 0);
            $store = (int) ($row['store_expenses_minor'] ?? 0);

            $result[] = array(
                'label' => (string) ($row['label'] ?? ''),
                'timestamp' => (int) ($row['timestamp'] ?? 0),
                'revenue_minor' => (int) ($row['revenue_minor'] ?? 0),
                'profit_minor' => (int) ($row['profit_minor'] ?? 0),
                'direct_costs_minor' => $direct,
                'global_order_costs_minor' => $fixed,
                'store_expenses_minor' => $store,
                'operating_expenses_minor' => $direct + $fixed + $store,
            );
        }

        return $result;
    }

    private function buildSpikeDays(array $trend): array
    {
        $rows = $trend;

        usort(
            $rows,
            static function (array $left, array $right): int {
                return (int) $right['operating_expenses_minor']
                    <=> (int) $left['operating_expenses_minor'];
            }
        );

        return array_slice($rows, 0, 5);
    }

    private function previousRange(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $seconds = max(
            DAY_IN_SECONDS,
            $end->getTimestamp() - $start->getTimestamp() + 1
        );

        $previousEnd = $start->modify('-1 second');
        $previousStart = $previousEnd->modify('-' . max(0, $seconds - 1) . ' seconds');

        return array($previousStart, $previousEnd);
    }

    private function changePercentage(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return (($current - $previous) / abs($previous)) * 100;
    }
}
