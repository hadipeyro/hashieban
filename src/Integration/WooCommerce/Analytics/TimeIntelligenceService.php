<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use Hashieban\Domain\Money\Money;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Finance\StoreExpenseRepository;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Hashieban\Support\JalaliDate;
use WC_Order;

final class TimeIntelligenceService
{
    private OrderAdapter $orderAdapter;
    private StoreExpenseRepository $expenseRepository;
    private GlobalOrderCostRepository $globalCosts;
    private ProfitEngine $profitEngine;

    public function __construct(
        OrderAdapter $orderAdapter,
        StoreExpenseRepository $expenseRepository,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine
    ) {
        $this->orderAdapter = $orderAdapter;
        $this->expenseRepository = $expenseRepository;
        $this->globalCosts = $globalCosts;
        $this->profitEngine = $profitEngine;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $current = $this->aggregateRange($start, $end);

        $durationSeconds = max(
            1,
            ($end->getTimestamp() - $start->getTimestamp()) + 1
        );

        $previousEnd = (new DateTimeImmutable('@' . ($start->getTimestamp() - 1)))
            ->setTimezone(wp_timezone());

        $previousStart = (new DateTimeImmutable('@' . ($previousEnd->getTimestamp() - $durationSeconds + 1)))
            ->setTimezone(wp_timezone());

        $previous = $this->aggregateRange(
            $previousStart,
            $previousEnd
        );

        $comparison = array(
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'revenue_change_percentage' => $this->percentageChange(
                (int) $current['total_revenue_minor'],
                (int) $previous['total_revenue_minor']
            ),
            'profit_change_percentage' => $this->percentageChange(
                (int) $current['net_profit_minor'],
                (int) $previous['net_profit_minor']
            ),
            'orders_change_percentage' => $this->percentageChange(
                (int) $current['order_count'],
                (int) $previous['order_count']
            ),
            'margin_change_points' => $this->difference(
                $current['margin_percentage'],
                $previous['margin_percentage']
            ),
            'current' => array(
                'revenue_minor' => (int) $current['total_revenue_minor'],
                'profit_minor' => (int) $current['net_profit_minor'],
                'order_count' => (int) $current['order_count'],
                'margin_percentage' => $current['margin_percentage'],
            ),
            'previous' => array(
                'revenue_minor' => (int) $previous['total_revenue_minor'],
                'profit_minor' => (int) $previous['net_profit_minor'],
                'order_count' => (int) $previous['order_count'],
                'margin_percentage' => $previous['margin_percentage'],
            ),
        );

        $current['comparison'] = $comparison;

        return $current;
    }

    private function aggregateRange(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();

        $days = $this->initializeDays($start, $end);

        $totalRevenueMinor = 0;
        $totalCogsMinor = 0;
        $totalDirectCostsMinor = 0;
        $totalGlobalOrderCostsMinor = 0;
        $orderCount = 0;
        $incompleteOrders = 0;
        $ordersWithRefunds = 0;

        $page = 1;
        $maxPages = 1;

        do {
            $result = wc_get_orders(
                array(
                    'status' => array('processing', 'completed', 'refunded'),
                    'currency' => $currency,
                    'limit' => 100,
                    'page' => $page,
                    'paginate' => true,
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'date_created' => $start->format('Y-m-d H:i:s')
                        . '...'
                        . $end->format('Y-m-d H:i:s'),
                )
            );

            if (! is_object($result) || ! isset($result->orders)) {
                break;
            }

            $maxPages = isset($result->max_num_pages)
                ? max(1, (int) $result->max_num_pages)
                : 1;

            foreach ($result->orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $financial = $this->orderAdapter->fromOrder($order);
                $orderGlobalCost = $this->globalCosts->totalForOrder(
                    $order,
                    $currency,
                    $precision
                );
                $profitResult = $this->profitEngine->calculateOrder(
                    $financial,
                    $orderGlobalCost
                );

                $breakdown = $profitResult->breakdown();

                $revenueMinor = $breakdown->revenue()->minorAmount();
                $cogsMinor = $breakdown->cogs()->minorAmount();
                $directCostsMinor = $breakdown->orderCosts()->minorAmount();
                $globalOrderCostsMinor = $breakdown->globalOrderCosts()->minorAmount();
                $profitMinor = $profitResult->profit()->minorAmount();

                $totalRevenueMinor += $revenueMinor;
                $totalCogsMinor += $cogsMinor;
                $totalDirectCostsMinor += $directCostsMinor;
                $totalGlobalOrderCostsMinor += $globalOrderCostsMinor;
                $orderCount++;

                if ($profitResult->completeness()->isIncomplete()) {
                    $incompleteOrders++;
                }

                if ((float) $order->get_total_refunded() > 0) {
                    $ordersWithRefunds++;
                }

                $created = $order->get_date_created();

                if (! $created) {
                    continue;
                }

                $date = (new DateTimeImmutable('@' . $created->getTimestamp()))
                    ->setTimezone(wp_timezone());

                $key = $date->format('Y-m-d');

                if (! isset($days[$key])) {
                    $days[$key] = $this->newDay($date);
                }

                $days[$key]['order_count']++;
                $days[$key]['revenue_minor'] += $revenueMinor;
                $days[$key]['cogs_minor'] += $cogsMinor;
                $days[$key]['direct_costs_minor'] += $directCostsMinor;
                $days[$key]['global_order_costs_minor'] += $globalOrderCostsMinor;
                $days[$key]['profit_minor'] += $profitMinor;

                if ($profitResult->completeness()->isIncomplete()) {
                    $days[$key]['incomplete_orders']++;
                }
            }

            $page++;
        } while ($page <= $maxPages);

        $storeExpenses = $this->expenseRepository->sumBetween(
            $start,
            $end,
            $currency,
            $precision
        );

        $expenseRows = $this->expenseRepository->totalsByDateBetween(
            $start,
            $end,
            $currency
        );

        foreach ($expenseRows as $expenseRow) {
            $dateValue = (string) ($expenseRow['expense_date'] ?? '');

            if ($dateValue === '') {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateValue,
                wp_timezone()
            );

            if (! $date) {
                continue;
            }

            $key = $date->format('Y-m-d');

            if (! isset($days[$key])) {
                $days[$key] = $this->newDay($date);
            }

            $amountMinor = (int) ($expenseRow['amount_minor'] ?? 0);
            $days[$key]['store_expenses_minor'] += $amountMinor;
            $days[$key]['profit_minor'] -= $amountMinor;
        }

        ksort($days);

        foreach ($days as &$day) {
            $day['margin_percentage'] = (int) $day['revenue_minor'] !== 0
                ? ((int) $day['profit_minor'] / (int) $day['revenue_minor']) * 100
                : null;
        }
        unset($day);

        $storeProfit = $this->profitEngine->calculateStore(
            new Money($totalRevenueMinor, $currency, $precision),
            new Money($totalCogsMinor, $currency, $precision),
            new Money($totalDirectCostsMinor, $currency, $precision),
            new Money($totalGlobalOrderCostsMinor, $currency, $precision),
            $storeExpenses,
            $incompleteOrders
        );

        $weekday = $this->buildWeekdayAnalysis($days);
        $seasonality = $this->buildSeasonality($days);
        $timeline = $this->buildTimeline($days, $start, $end);
        $activeDays = $this->activeDays($days);
        $activeWeekdays = array_values(
            array_filter(
                $weekday,
                static function (array $row): bool {
                    return (int) $row['active_days'] > 0;
                }
            )
        );

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'start' => $start,
            'end' => $end,
            'total_revenue_minor' => $totalRevenueMinor,
            'total_cogs_minor' => $totalCogsMinor,
            'total_direct_costs_minor' => $totalDirectCostsMinor,
            'total_global_order_costs_minor' => $totalGlobalOrderCostsMinor,
            'store_expenses_minor' => $storeExpenses->minorAmount(),
            'net_profit_minor' => $storeProfit->profit()->minorAmount(),
            'margin_percentage' => $storeProfit->marginPercentage(),
            'order_count' => $orderCount,
            'incomplete_orders' => $incompleteOrders,
            'orders_with_refunds' => $ordersWithRefunds,
            'active_day_count' => count($activeDays),
            'timeline_mode' => $timeline['mode'],
            'timeline' => $timeline['rows'],
            'weekday' => $weekday,
            'seasonality' => $seasonality,
            'best_revenue_day' => $this->bestRow($activeDays, 'revenue_minor', true),
            'best_profit_day' => $this->bestRow($activeDays, 'profit_minor', true),
            'worst_profit_day' => $this->bestRow($activeDays, 'profit_minor', false),
            'best_weekday' => $this->bestRow($activeWeekdays, 'average_profit_minor', true),
        );
    }

    private function initializeDays(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $days = array();
        $cursor = $start->setTime(0, 0, 0);
        $last = $end->setTime(0, 0, 0);

        while ($cursor <= $last) {
            $days[$cursor->format('Y-m-d')] = $this->newDay($cursor);
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    private function newDay(DateTimeInterface $date): array
    {
        list($jalaliYear, $jalaliMonth) = JalaliDate::parts($date);

        return array(
            'key' => $date->format('Y-m-d'),
            'timestamp' => $date->getTimestamp(),
            'label' => JalaliDate::shortNumeric($date),
            'full_label' => JalaliDate::format($date),
            'weekday_index' => (int) $date->format('w'),
            'jalali_year' => $jalaliYear,
            'jalali_month' => $jalaliMonth,
            'jalali_month_label' => JalaliDate::monthLabel($date),
            'order_count' => 0,
            'revenue_minor' => 0,
            'cogs_minor' => 0,
            'direct_costs_minor' => 0,
            'global_order_costs_minor' => 0,
            'store_expenses_minor' => 0,
            'profit_minor' => 0,
            'incomplete_orders' => 0,
            'margin_percentage' => null,
        );
    }

    private function activeDays(array $days): array
    {
        return array_values(
            array_filter(
                $days,
                static function (array $day): bool {
                    return (int) $day['order_count'] > 0
                        || (int) $day['store_expenses_minor'] > 0;
                }
            )
        );
    }

    private function buildWeekdayAnalysis(array $days): array
    {
        $labels = array(
            6 => 'شنبه',
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنجشنبه',
            5 => 'جمعه',
        );

        $rows = array();

        foreach ($labels as $index => $label) {
            $rows[$index] = array(
                'weekday_index' => $index,
                'label' => $label,
                'calendar_days' => 0,
                'active_days' => 0,
                'order_count' => 0,
                'revenue_minor' => 0,
                'profit_minor' => 0,
                'average_revenue_minor' => 0,
                'average_profit_minor' => 0,
            );
        }

        foreach ($days as $day) {
            $index = (int) $day['weekday_index'];

            if (! isset($rows[$index])) {
                continue;
            }

            $rows[$index]['calendar_days']++;
            $rows[$index]['order_count'] += (int) $day['order_count'];
            $rows[$index]['revenue_minor'] += (int) $day['revenue_minor'];
            $rows[$index]['profit_minor'] += (int) $day['profit_minor'];

            if (
                (int) $day['order_count'] > 0
                || (int) $day['store_expenses_minor'] > 0
            ) {
                $rows[$index]['active_days']++;
            }
        }

        foreach ($rows as &$row) {
            $calendarDays = max(1, (int) $row['calendar_days']);
            $row['average_revenue_minor'] = (int) round(
                (int) $row['revenue_minor'] / $calendarDays
            );
            $row['average_profit_minor'] = (int) round(
                (int) $row['profit_minor'] / $calendarDays
            );
        }
        unset($row);

        $ordered = array();

        foreach (array(6, 0, 1, 2, 3, 4, 5) as $index) {
            $ordered[] = $rows[$index];
        }

        return $ordered;
    }

    private function buildSeasonality(array $days): array
    {
        $periods = array();

        foreach ($days as $day) {
            $key = sprintf(
                '%04d-%02d',
                (int) $day['jalali_year'],
                (int) $day['jalali_month']
            );

            if (! isset($periods[$key])) {
                $periods[$key] = array(
                    'year' => (int) $day['jalali_year'],
                    'month' => (int) $day['jalali_month'],
                    'revenue_minor' => 0,
                    'profit_minor' => 0,
                    'order_count' => 0,
                );
            }

            $periods[$key]['revenue_minor'] += (int) $day['revenue_minor'];
            $periods[$key]['profit_minor'] += (int) $day['profit_minor'];
            $periods[$key]['order_count'] += (int) $day['order_count'];
        }

        $monthNames = array(
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        );

        $rows = array();

        foreach ($monthNames as $month => $label) {
            $rows[$month] = array(
                'month' => $month,
                'label' => $label,
                'period_count' => 0,
                'revenue_minor' => 0,
                'profit_minor' => 0,
                'order_count' => 0,
                'average_revenue_minor' => 0,
                'average_profit_minor' => 0,
            );
        }

        foreach ($periods as $period) {
            $month = (int) $period['month'];

            if (! isset($rows[$month])) {
                continue;
            }

            $rows[$month]['period_count']++;
            $rows[$month]['revenue_minor'] += (int) $period['revenue_minor'];
            $rows[$month]['profit_minor'] += (int) $period['profit_minor'];
            $rows[$month]['order_count'] += (int) $period['order_count'];
        }

        foreach ($rows as &$row) {
            $periodCount = (int) $row['period_count'];

            if ($periodCount > 0) {
                $row['average_revenue_minor'] = (int) round(
                    (int) $row['revenue_minor'] / $periodCount
                );
                $row['average_profit_minor'] = (int) round(
                    (int) $row['profit_minor'] / $periodCount
                );
            }
        }
        unset($row);

        return array_values($rows);
    }

    private function buildTimeline(
        array $days,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $dayCount = max(
            1,
            (int) $start->diff($end)->format('%a') + 1
        );

        if ($dayCount <= 45) {
            return array(
                'mode' => 'day',
                'rows' => array_values($days),
            );
        }

        $mode = $dayCount <= 220 ? 'week' : 'month';
        $buckets = array();

        foreach ($days as $day) {
            $date = (new DateTimeImmutable('@' . (int) $day['timestamp']))
                ->setTimezone(wp_timezone());

            if ($mode === 'week') {
                $bucketStart = $this->weekStart($date);
                $key = $bucketStart->format('Y-m-d');
                $label = JalaliDate::weekRangeLabel($bucketStart);
                $timestamp = $bucketStart->getTimestamp();
            } else {
                $key = sprintf(
                    '%04d-%02d',
                    (int) $day['jalali_year'],
                    (int) $day['jalali_month']
                );
                $label = (string) $day['jalali_month_label'];
                $timestamp = (int) $day['timestamp'];
            }

            if (! isset($buckets[$key])) {
                $buckets[$key] = array(
                    'key' => $key,
                    'timestamp' => $timestamp,
                    'label' => $label,
                    'order_count' => 0,
                    'revenue_minor' => 0,
                    'profit_minor' => 0,
                    'margin_percentage' => null,
                );
            }

            $buckets[$key]['order_count'] += (int) $day['order_count'];
            $buckets[$key]['revenue_minor'] += (int) $day['revenue_minor'];
            $buckets[$key]['profit_minor'] += (int) $day['profit_minor'];
        }

        ksort($buckets);

        foreach ($buckets as &$bucket) {
            $bucket['margin_percentage'] = (int) $bucket['revenue_minor'] !== 0
                ? ((int) $bucket['profit_minor'] / (int) $bucket['revenue_minor']) * 100
                : null;
        }
        unset($bucket);

        return array(
            'mode' => $mode,
            'rows' => array_values($buckets),
        );
    }

    private function weekStart(DateTimeInterface $date): DateTimeImmutable
    {
        $copy = (new DateTimeImmutable('@' . $date->getTimestamp()))
            ->setTimezone(wp_timezone())
            ->setTime(0, 0, 0);

        $weekday = (int) $copy->format('w');
        $offset = ($weekday + 1) % 7;

        if ($offset > 0) {
            $copy = $copy->modify('-' . $offset . ' days');
        }

        return $copy;
    }

    private function bestRow(
        array $rows,
        string $field,
        bool $highest
    ): ?array {
        if ($rows === array()) {
            return null;
        }

        $best = null;

        foreach ($rows as $row) {
            if ($best === null) {
                $best = $row;
                continue;
            }

            $value = (float) ($row[$field] ?? 0);
            $bestValue = (float) ($best[$field] ?? 0);

            if (
                ($highest && $value > $bestValue)
                || (! $highest && $value < $bestValue)
            ) {
                $best = $row;
            }
        }

        return $best;
    }

    private function percentageChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return (($current - $previous) / abs($previous)) * 100;
    }

    private function difference($current, $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return (float) $current - (float) $previous;
    }
}
