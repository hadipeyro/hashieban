<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Support\Currency;

final class ReportsHubService
{
    private ProductProfitabilityService $products;
    private CustomerProfitabilityService $customers;
    private TimeIntelligenceService $time;
    private OrderProfitCenterService $orders;

    public function __construct(
        ProductProfitabilityService $products,
        CustomerProfitabilityService $customers,
        TimeIntelligenceService $time,
        OrderProfitCenterService $orders
    ) {
        $this->products = $products;
        $this->customers = $customers;
        $this->time = $time;
        $this->orders = $orders;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $time = $this->time->getReport($start, $end);
        $products = $this->products->getReport($start, $end);
        $customers = $this->customers->getReport($start, $end);
        $orders = $this->orders->getReport($start, $end, array());

        $currency = (string) $time['currency'];
        $precision = (int) $time['precision'];

        $revenueMinor = (int) $time['total_revenue_minor'];
        $profitMinor = (int) $time['net_profit_minor'];
        $cogsMinor = (int) $time['total_cogs_minor'];
        $directCostsMinor = (int) $time['total_direct_costs_minor'];
        $globalCostsMinor = (int) $time['total_global_order_costs_minor'];
        $storeExpensesMinor = (int) $time['store_expenses_minor'];

        $comparison = isset($time['comparison']) && is_array($time['comparison'])
            ? $time['comparison']
            : array();

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'start' => $start,
            'end' => $end,
            'revenue_minor' => $revenueMinor,
            'profit_minor' => $profitMinor,
            'margin_percentage' => $time['margin_percentage'],
            'order_count' => (int) $time['order_count'],
            'customer_count' => (int) $customers['customer_count'],
            'product_count' => (int) $products['product_count'],
            'refund_order_count' => (int) $orders['refund_order_count'],
            'incomplete_order_count' => (int) $orders['incomplete_count'],
            'loss_order_count' => (int) $orders['loss_count'],
            'products_missing_cogs' => (int) $products['products_with_missing_cogs'],
            'cogs_minor' => $cogsMinor,
            'direct_costs_minor' => $directCostsMinor,
            'global_order_costs_minor' => $globalCostsMinor,
            'store_expenses_minor' => $storeExpensesMinor,
            'comparison' => $comparison,
            'top_product' => $this->firstRow($products['top_by_profit'] ?? array()),
            'top_customer' => $this->firstRow($customers['top_by_profit'] ?? array()),
            'best_day' => is_array($time['best_profit_day'] ?? null)
                ? $time['best_profit_day']
                : null,
            'worst_order' => is_array($orders['worst_order'] ?? null)
                ? $orders['worst_order']
                : null,
            'profit_bridge' => array(
                'labels' => array(
                    'فروش خالص',
                    'قیمت خرید کالا',
                    'هزینه سفارش',
                    'هزینه ثابت سفارش',
                    'هزینه‌های فروشگاه',
                    'سود خالص',
                ),
                'values_minor' => array(
                    $revenueMinor,
                    -1 * $cogsMinor,
                    -1 * $directCostsMinor,
                    -1 * $globalCostsMinor,
                    -1 * $storeExpensesMinor,
                    $profitMinor,
                ),
            ),
            'top_product_profit' => $this->topProductProfitRows(
                (array) ($products['top_by_profit'] ?? array()),
                (int) $products['total_profit_minor']
            ),
            'timeline' => (array) ($time['timeline'] ?? array()),
            'exports' => array(
                'products' => (array) ($products['products'] ?? array()),
                'customers' => (array) ($customers['customers'] ?? array()),
                'orders' => (array) ($orders['orders'] ?? array()),
                'timeline' => (array) ($time['timeline'] ?? array()),
            ),
        );
    }

    public function exportRows(
        string $type,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        switch ($type) {
            case 'products':
                $report = $this->products->getReport($start, $end);
                $currency = (string) $report['currency'];
                $precision = (int) $report['precision'];
                $unit = Currency::label($currency);
                return array(
                    'filename' => 'hashieban-products-report.csv',
                    'headers' => array(
                        'محصول', 'SKU', 'تعداد فروش', 'تعداد سفارش', 'فروش (' . $unit . ')', 'COGS (' . $unit . ')',
                        'سود (' . $unit . ')', 'Margin %', 'سهم فروش %', 'سهم سود %', 'مرجوعی', 'نرخ مرجوعی %',
                    ),
                    'rows' => array_map(
                        static function (array $row) use ($currency, $precision): array {
                            return array(
                                (string) ($row['name'] ?? ''),
                                (string) ($row['sku'] ?? ''),
                                (int) ($row['quantity'] ?? 0),
                                (int) ($row['order_count'] ?? 0),
                                Currency::minorToDisplayInput((int) ($row['revenue_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['cogs_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['profit_minor'] ?? 0), $currency, $precision),
                                $row['margin_percentage'] ?? '',
                                $row['sales_share_percentage'] ?? '',
                                $row['profit_share_percentage'] ?? '',
                                (int) ($row['refunded_quantity'] ?? 0),
                                $row['return_rate_percentage'] ?? '',
                            );
                        },
                        (array) $report['products']
                    ),
                );

            case 'customers':
                $report = $this->customers->getReport($start, $end);
                $currency = (string) $report['currency'];
                $precision = (int) $report['precision'];
                $unit = Currency::label($currency);
                return array(
                    'filename' => 'hashieban-customers-report.csv',
                    'headers' => array(
                        'مشتری', 'ایمیل', 'تلفن', 'تعداد سفارش', 'فروش (' . $unit . ')', 'سود (' . $unit . ')', 'AOV (' . $unit . ')',
                        'Margin %', 'سهم فروش %', 'سهم سود %', 'سفارش مرجوعی',
                    ),
                    'rows' => array_map(
                        static function (array $row) use ($currency, $precision): array {
                            return array(
                                (string) ($row['name'] ?? ''),
                                (string) ($row['email'] ?? ''),
                                (string) ($row['phone'] ?? ''),
                                (int) ($row['order_count'] ?? 0),
                                Currency::minorToDisplayInput((int) ($row['revenue_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['profit_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['average_order_value_minor'] ?? 0), $currency, $precision),
                                $row['margin_percentage'] ?? '',
                                $row['sales_share_percentage'] ?? '',
                                $row['profit_share_percentage'] ?? '',
                                (int) ($row['refund_orders'] ?? 0),
                            );
                        },
                        (array) $report['customers']
                    ),
                );

            case 'orders':
                $report = $this->orders->getReport($start, $end, array());
                $currency = (string) $report['currency'];
                $precision = (int) $report['precision'];
                $unit = Currency::label($currency);
                return array(
                    'filename' => 'hashieban-orders-report.csv',
                    'headers' => array(
                        'شماره سفارش', 'مشتری', 'وضعیت', 'فروش (' . $unit . ')', 'COGS (' . $unit . ')', 'هزینه سفارش (' . $unit . ')',
                        'هزینه ثابت سفارش (' . $unit . ')', 'سود (' . $unit . ')', 'Margin %', 'Refund (' . $unit . ')', 'مالیات خالص (' . $unit . ')',
                    ),
                    'rows' => array_map(
                        static function (array $row) use ($currency, $precision): array {
                            return array(
                                (string) ($row['order_number'] ?? ''),
                                (string) ($row['customer_name'] ?? ''),
                                (string) ($row['status_label'] ?? ''),
                                Currency::minorToDisplayInput((int) ($row['revenue_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['cogs_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['direct_costs_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['global_order_costs_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['profit_minor'] ?? 0), $currency, $precision),
                                $row['margin_percentage'] ?? '',
                                Currency::minorToDisplayInput((int) ($row['refund_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['net_tax_minor'] ?? 0), $currency, $precision),
                            );
                        },
                        (array) $report['orders']
                    ),
                );

            case 'timeline':
            default:
                $report = $this->time->getReport($start, $end);
                $currency = (string) $report['currency'];
                $precision = (int) $report['precision'];
                $unit = Currency::label($currency);
                return array(
                    'filename' => 'hashieban-time-report.csv',
                    'headers' => array(
                        'بازه', 'تعداد سفارش', 'فروش (' . $unit . ')', 'سود (' . $unit . ')',
                    ),
                    'rows' => array_map(
                        static function (array $row) use ($currency, $precision): array {
                            return array(
                                (string) ($row['label'] ?? ''),
                                (int) ($row['order_count'] ?? 0),
                                Currency::minorToDisplayInput((int) ($row['revenue_minor'] ?? 0), $currency, $precision),
                                Currency::minorToDisplayInput((int) ($row['profit_minor'] ?? 0), $currency, $precision),
                            );
                        },
                        (array) $report['timeline']
                    ),
                );
        }
    }

    private function firstRow(array $rows): ?array
    {
        if ($rows === array()) {
            return null;
        }

        $first = reset($rows);

        return is_array($first) ? $first : null;
    }

    private function topProductProfitRows(
        array $rows,
        int $totalProfitMinor
    ): array {
        $result = array();
        $usedProfit = 0;

        foreach (array_slice($rows, 0, 6) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $profit = (int) ($row['profit_minor'] ?? 0);

            if ($profit <= 0) {
                continue;
            }

            $usedProfit += $profit;
            $result[] = array(
                'name' => (string) ($row['name'] ?? 'محصول'),
                'profit_minor' => $profit,
                'share_percentage' => $totalProfitMinor > 0
                    ? ($profit / $totalProfitMinor) * 100
                    : null,
                'edit_url' => (string) ($row['edit_url'] ?? ''),
            );
        }

        $other = max(0, $totalProfitMinor - $usedProfit);

        if ($other > 0) {
            $result[] = array(
                'name' => 'سایر محصولات',
                'profit_minor' => $other,
                'share_percentage' => $totalProfitMinor > 0
                    ? ($other / $totalProfitMinor) * 100
                    : null,
                'edit_url' => '',
            );
        }

        return $result;
    }
}
