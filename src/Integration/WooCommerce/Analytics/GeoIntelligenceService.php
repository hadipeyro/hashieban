<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Domain\Profit\ProfitEngine;
use Hashieban\Finance\GlobalOrderCostRepository;
use Hashieban\Integration\WooCommerce\Geo\GeoAddressResolver;
use Hashieban\Integration\WooCommerce\Order\MoneyFactory;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Throwable;
use WC_Order;
use WC_Order_Item_Product;

final class GeoIntelligenceService
{
    private OrderAdapter $orderAdapter;
    private GlobalOrderCostRepository $globalCosts;
    private ProfitEngine $profitEngine;
    private GeoAddressResolver $geoAddress;
    private MoneyFactory $moneyFactory;

    public function __construct(
        OrderAdapter $orderAdapter,
        GlobalOrderCostRepository $globalCosts,
        ProfitEngine $profitEngine,
        GeoAddressResolver $geoAddress,
        MoneyFactory $moneyFactory
    ) {
        $this->orderAdapter = $orderAdapter;
        $this->globalCosts = $globalCosts;
        $this->profitEngine = $profitEngine;
        $this->geoAddress = $geoAddress;
        $this->moneyFactory = $moneyFactory;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $currency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();
        $provinces = array();
        $cities = array();
        $allCustomers = array();
        $iranOrderCount = 0;
        $provinceMappedOrders = 0;
        $cityMappedOrders = 0;
        $calculationErrors = 0;
        $mappedRevenueMinor = 0;
        $mappedProfitMinor = 0;

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
                    'order' => 'DESC',
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

                $geo = $this->geoAddress->resolve($order);

                if ((string) ($geo['country'] ?? '') !== GeoAddressResolver::COUNTRY_IRAN) {
                    continue;
                }

                $iranOrderCount++;

                $provinceIdentity = $this->provinceIdentity(
                    (string) ($geo['state_code'] ?? ''),
                    (string) ($geo['state_name'] ?? '')
                );
                $provinceName = (string) $provinceIdentity['name'];
                $provinceMapName = (string) $provinceIdentity['map_name'];
                $cityName = $this->normalizeCityName(
                    (string) ($geo['city_name'] ?? '')
                );

                if ($provinceName === '' || $provinceMapName === '') {
                    continue;
                }

                try {
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
                } catch (Throwable $exception) {
                    $calculationErrors++;
                    continue;
                }

                $breakdown = $profitResult->breakdown();
                $revenueMinor = $breakdown->revenue()->minorAmount();
                $profitMinor = $profitResult->profit()->minorAmount();
                $provinceMappedOrders++;
                $mappedRevenueMinor += $revenueMinor;
                $mappedProfitMinor += $profitMinor;

                $customer = $this->customerIdentity($order);
                $allCustomers[$customer['key']] = true;

                if (! isset($provinces[$provinceName])) {
                    $provinces[$provinceName] = $this->newProvince(
                        $provinceName,
                        $provinceMapName
                    );
                }

                $provinces[$provinceName]['order_count']++;
                $provinces[$provinceName]['revenue_minor'] += $revenueMinor;
                $provinces[$provinceName]['profit_minor'] += $profitMinor;
                $provinces[$provinceName]['customers'][$customer['key']] = true;

                if (! isset($provinces[$provinceName]['customer_rows'][$customer['key']])) {
                    $provinces[$provinceName]['customer_rows'][$customer['key']] = array(
                        'name' => $customer['name'],
                        'revenue_minor' => 0,
                        'profit_minor' => 0,
                        'order_count' => 0,
                    );
                }

                $provinces[$provinceName]['customer_rows'][$customer['key']]['revenue_minor'] += $revenueMinor;
                $provinces[$provinceName]['customer_rows'][$customer['key']]['profit_minor'] += $profitMinor;
                $provinces[$provinceName]['customer_rows'][$customer['key']]['order_count']++;

                $this->aggregateProducts(
                    $provinces[$provinceName]['products'],
                    $order,
                    $financial->refundItems(),
                    $currency,
                    $precision
                );

                if ($cityName !== '') {
                    $cityMappedOrders++;
                    $cityKey = $provinceName . '|' . $this->key($cityName);

                    if (! isset($cities[$cityKey])) {
                        $cities[$cityKey] = array(
                            'key' => $cityKey,
                            'name' => $cityName,
                            'province' => $provinceName,
                            'order_count' => 0,
                            'revenue_minor' => 0,
                            'profit_minor' => 0,
                            'customers' => array(),
                        );
                    }

                    $cities[$cityKey]['order_count']++;
                    $cities[$cityKey]['revenue_minor'] += $revenueMinor;
                    $cities[$cityKey]['profit_minor'] += $profitMinor;
                    $cities[$cityKey]['customers'][$customer['key']] = true;
                }
            }

            $page++;
        } while ($page <= $maxPages);

        $provinceRows = $this->finalizeProvinces(
            $provinces,
            $cities,
            $mappedRevenueMinor,
            $mappedProfitMinor,
            $provinceMappedOrders
        );

        $cityRows = $this->finalizeCities(
            $cities,
            $mappedRevenueMinor,
            $mappedProfitMinor,
            $provinceMappedOrders
        );

        $uniqueCustomers = count($allCustomers);
        $provinceCustomerAssociations = array_sum(
            array_map(
                static function (array $row): int {
                    return (int) $row['customer_count'];
                },
                $provinceRows
            )
        );
        $cityCustomerAssociations = array_sum(
            array_map(
                static function (array $row): int {
                    return (int) $row['customer_count'];
                },
                $cityRows
            )
        );

        foreach ($provinceRows as &$row) {
            $row['customer_share_percentage'] = $provinceCustomerAssociations > 0
                ? ((int) $row['customer_count'] / $provinceCustomerAssociations) * 100
                : null;
        }
        unset($row);

        foreach ($cityRows as &$row) {
            $row['customer_share_percentage'] = $cityCustomerAssociations > 0
                ? ((int) $row['customer_count'] / $cityCustomerAssociations) * 100
                : null;
        }
        unset($row);

        usort(
            $provinceRows,
            static function (array $a, array $b): int {
                return (int) $b['revenue_minor'] <=> (int) $a['revenue_minor'];
            }
        );

        usort(
            $cityRows,
            static function (array $a, array $b): int {
                return (int) $b['revenue_minor'] <=> (int) $a['revenue_minor'];
            }
        );

        return array(
            'currency' => $currency,
            'precision' => $precision,
            'start' => $start,
            'end' => $end,
            'iran_order_count' => $iranOrderCount,
            'province_mapped_orders' => $provinceMappedOrders,
            'city_mapped_orders' => $cityMappedOrders,
            'province_readiness_percentage' => $iranOrderCount > 0
                ? ($provinceMappedOrders / $iranOrderCount) * 100
                : 0.0,
            'city_readiness_percentage' => $iranOrderCount > 0
                ? ($cityMappedOrders / $iranOrderCount) * 100
                : 0.0,
            'unique_customer_count' => $uniqueCustomers,
            'mapped_revenue_minor' => $mappedRevenueMinor,
            'mapped_profit_minor' => $mappedProfitMinor,
            'province_count' => count($provinceRows),
            'city_count' => count($cityRows),
            'calculation_errors' => $calculationErrors,
            'provinces' => $provinceRows,
            'cities' => $cityRows,
            'top_sales_province' => $this->topRow($provinceRows, 'revenue_minor'),
            'top_profit_province' => $this->topRow($provinceRows, 'profit_minor'),
            'top_customer_province' => $this->topRow($provinceRows, 'customer_count'),
            'top_order_province' => $this->topRow($provinceRows, 'order_count'),
            'top_city' => isset($cityRows[0]) ? $cityRows[0] : null,
        );
    }

    private function newProvince(string $name, string $mapName): array
    {
        return array(
            'name' => $name,
            'map_name' => $mapName,
            'order_count' => 0,
            'revenue_minor' => 0,
            'profit_minor' => 0,
            'customers' => array(),
            'customer_rows' => array(),
            'products' => array(),
        );
    }

    private function finalizeProvinces(
        array $provinces,
        array $cities,
        int $totalRevenueMinor,
        int $totalProfitMinor,
        int $totalOrders
    ): array {
        $rows = array();

        foreach ($provinces as $province) {
            $revenueMinor = (int) $province['revenue_minor'];
            $profitMinor = (int) $province['profit_minor'];
            $orderCount = max(1, (int) $province['order_count']);
            $customerCount = count((array) $province['customers']);

            $provinceCities = array_values(
                array_filter(
                    $cities,
                    static function (array $city) use ($province): bool {
                        return (string) $city['province'] === (string) $province['name'];
                    }
                )
            );

            $rows[] = array(
                'name' => (string) $province['name'],
                'map_name' => (string) $province['map_name'],
                'order_count' => (int) $province['order_count'],
                'customer_count' => $customerCount,
                'revenue_minor' => $revenueMinor,
                'profit_minor' => $profitMinor,
                'average_order_minor' => (int) round($revenueMinor / $orderCount),
                'margin_percentage' => $revenueMinor !== 0
                    ? ($profitMinor / $revenueMinor) * 100
                    : null,
                'sales_share_percentage' => $totalRevenueMinor > 0
                    ? ($revenueMinor / $totalRevenueMinor) * 100
                    : null,
                'profit_share_percentage' => $totalProfitMinor > 0
                    ? ($profitMinor / $totalProfitMinor) * 100
                    : null,
                'order_share_percentage' => $totalOrders > 0
                    ? ((int) $province['order_count'] / $totalOrders) * 100
                    : null,
                'customer_share_percentage' => null,
                'top_city' => $this->topRow($provinceCities, 'revenue_minor'),
                'top_product' => $this->topRow(
                    array_values((array) $province['products']),
                    'revenue_minor'
                ),
                'top_customer' => $this->topRow(
                    array_values((array) $province['customer_rows']),
                    'revenue_minor'
                ),
            );
        }

        return $rows;
    }

    private function finalizeCities(
        array $cities,
        int $totalRevenueMinor,
        int $totalProfitMinor,
        int $totalOrders
    ): array {
        $rows = array();

        foreach ($cities as $city) {
            $revenueMinor = (int) $city['revenue_minor'];
            $profitMinor = (int) $city['profit_minor'];
            $orderCount = max(1, (int) $city['order_count']);

            $rows[] = array(
                'key' => (string) $city['key'],
                'name' => (string) $city['name'],
                'province' => (string) $city['province'],
                'order_count' => (int) $city['order_count'],
                'customer_count' => count((array) $city['customers']),
                'revenue_minor' => $revenueMinor,
                'profit_minor' => $profitMinor,
                'average_order_minor' => (int) round($revenueMinor / $orderCount),
                'margin_percentage' => $revenueMinor !== 0
                    ? ($profitMinor / $revenueMinor) * 100
                    : null,
                'sales_share_percentage' => $totalRevenueMinor > 0
                    ? ($revenueMinor / $totalRevenueMinor) * 100
                    : null,
                'profit_share_percentage' => $totalProfitMinor > 0
                    ? ($profitMinor / $totalProfitMinor) * 100
                    : null,
                'order_share_percentage' => $totalOrders > 0
                    ? ((int) $city['order_count'] / $totalOrders) * 100
                    : null,
            );
        }

        return $rows;
    }

    private function aggregateProducts(
        array &$products,
        WC_Order $order,
        array $refundItems,
        string $currency,
        int $precision
    ): void {
        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId = (int) $item->get_id();
            $variationId = (int) $item->get_variation_id();
            $productId = (int) $item->get_product_id();
            $productKey = $variationId > 0
                ? 'variation:' . $variationId
                : 'product:' . $productId;

            if ($productId <= 0) {
                $productKey = 'item:' . $itemId;
            }

            $refund = isset($refundItems[$itemId])
                ? (array) $refundItems[$itemId]
                : array();

            $lineRevenue = $this->moneyFactory->fromWooCommerceAmount(
                $item->get_total(),
                $currency,
                $precision
            )->minorAmount();

            $refundRevenue = max(
                0,
                (int) ($refund['refund_revenue_minor'] ?? 0)
            );

            $quantity = max(
                0,
                (int) $item->get_quantity()
                - (int) ($refund['refunded_quantity'] ?? 0)
            );

            if (! isset($products[$productKey])) {
                $products[$productKey] = array(
                    'name' => (string) $item->get_name(),
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'revenue_minor' => 0,
                    'quantity' => 0,
                );
            }

            $products[$productKey]['revenue_minor'] += max(0, $lineRevenue - $refundRevenue);
            $products[$productKey]['quantity'] += $quantity;
        }
    }

    private function customerIdentity(WC_Order $order): array
    {
        $customerId = (int) $order->get_customer_id();
        $first = trim((string) $order->get_billing_first_name());
        $last = trim((string) $order->get_billing_last_name());
        $name = trim($first . ' ' . $last);
        $email = strtolower(trim((string) $order->get_billing_email()));
        $phone = trim((string) $order->get_billing_phone());

        if ($name === '') {
            $name = $email !== '' ? $email : ($phone !== '' ? $phone : 'مشتری مهمان');
        }

        if ($customerId > 0) {
            $key = 'user:' . $customerId;
        } elseif ($email !== '') {
            $key = 'email:' . $email;
        } elseif ($phone !== '') {
            $key = 'phone:' . $phone;
        } else {
            $key = 'order:' . $order->get_id();
        }

        return array(
            'key' => $key,
            'name' => $name,
        );
    }

    private function topRow(array $rows, string $field): ?array
    {
        if ($rows === array()) {
            return null;
        }

        usort(
            $rows,
            static function (array $a, array $b) use ($field): int {
                return ($b[$field] ?? 0) <=> ($a[$field] ?? 0);
            }
        );

        return isset($rows[0]) ? $rows[0] : null;
    }

    private function provinceIdentity(string $stateCode, string $stateName): array
    {
        $catalog = array(
            'KHZ' => array('name' => 'خوزستان', 'map_name' => 'Khuzestan'),
            'THR' => array('name' => 'تهران', 'map_name' => 'Tehran'),
            'ILM' => array('name' => 'ایلام', 'map_name' => 'Ilam'),
            'BHR' => array('name' => 'بوشهر', 'map_name' => 'Bushehr'),
            'ADL' => array('name' => 'اردبیل', 'map_name' => 'Ardebil'),
            'ESF' => array('name' => 'اصفهان', 'map_name' => 'Esfahan'),
            'YZD' => array('name' => 'یزد', 'map_name' => 'Yazd'),
            'KRH' => array('name' => 'کرمانشاه', 'map_name' => 'Kermanshah'),
            'KRN' => array('name' => 'کرمان', 'map_name' => 'Kerman'),
            'HDN' => array('name' => 'همدان', 'map_name' => 'Hamadan'),
            'GZN' => array('name' => 'قزوین', 'map_name' => 'Qazvin'),
            'ZJN' => array('name' => 'زنجان', 'map_name' => 'Zanjan'),
            'LRS' => array('name' => 'لرستان', 'map_name' => 'Lorestan'),
            'ABZ' => array('name' => 'البرز', 'map_name' => 'Alborz'),
            'EAZ' => array('name' => 'آذربایجان شرقی', 'map_name' => 'East Azarbaijan'),
            'WAZ' => array('name' => 'آذربایجان غربی', 'map_name' => 'West Azarbaijan'),
            'CHB' => array('name' => 'چهارمحال و بختیاری', 'map_name' => 'Chahar Mahall and Bakhtiari'),
            'SKH' => array('name' => 'خراسان جنوبی', 'map_name' => 'South Khorasan'),
            'RKH' => array('name' => 'خراسان رضوی', 'map_name' => 'Razavi Khorasan'),
            'NKH' => array('name' => 'خراسان شمالی', 'map_name' => 'North Khorasan'),
            'SMN' => array('name' => 'سمنان', 'map_name' => 'Semnan'),
            'FRS' => array('name' => 'فارس', 'map_name' => 'Fars'),
            'QHM' => array('name' => 'قم', 'map_name' => 'Qom'),
            'KRD' => array('name' => 'کردستان', 'map_name' => 'Kordestan'),
            'KBD' => array('name' => 'کهگیلویه و بویراحمد', 'map_name' => 'Kohgiluyeh and Buyer Ahmad'),
            'GLS' => array('name' => 'گلستان', 'map_name' => 'Golestan'),
            'GIL' => array('name' => 'گیلان', 'map_name' => 'Gilan'),
            'MZN' => array('name' => 'مازندران', 'map_name' => 'Mazandaran'),
            'MKZ' => array('name' => 'مرکزی', 'map_name' => 'Markazi'),
            'HRZ' => array('name' => 'هرمزگان', 'map_name' => 'Hormozgan'),
            'SBN' => array('name' => 'سیستان و بلوچستان', 'map_name' => 'Sistan and Baluchestan'),
        );

        $code = strtoupper(trim($stateCode));
        if (strpos($code, 'IR-') === 0) {
            $code = substr($code, 3);
        }

        if (isset($catalog[$code])) {
            return $catalog[$code];
        }

        $value = $this->normalizePersian($stateName);
        $value = preg_replace('/^استان\s+/u', '', $value);
        $value = is_string($value) ? trim($value) : '';

        if (preg_match('/\(([^()]*)\)/u', $value, $matches) === 1) {
            $inside = $this->normalizePersian((string) ($matches[1] ?? ''));
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $inside) === 1) {
                $value = $inside;
            }
        }

        $aliases = array(
            'خوزستان' => 'KHZ',
            'Khuzestan' => 'KHZ',
            'تهران' => 'THR',
            'Tehran' => 'THR',
            'ایلام' => 'ILM',
            'Ilam' => 'ILM',
            'Ilaam' => 'ILM',
            'بوشهر' => 'BHR',
            'Bushehr' => 'BHR',
            'اردبیل' => 'ADL',
            'Ardabil' => 'ADL',
            'Ardebil' => 'ADL',
            'اصفهان' => 'ESF',
            'Isfahan' => 'ESF',
            'Esfahan' => 'ESF',
            'یزد' => 'YZD',
            'Yazd' => 'YZD',
            'کرمانشاه' => 'KRH',
            'Kermanshah' => 'KRH',
            'کرمان' => 'KRN',
            'Kerman' => 'KRN',
            'همدان' => 'HDN',
            'Hamadan' => 'HDN',
            'قزوین' => 'GZN',
            'Ghazvin' => 'GZN',
            'Qazvin' => 'GZN',
            'زنجان' => 'ZJN',
            'Zanjan' => 'ZJN',
            'لرستان' => 'LRS',
            'Luristan' => 'LRS',
            'Lorestan' => 'LRS',
            'البرز' => 'ABZ',
            'Alborz' => 'ABZ',
            'آذربایجان شرقی' => 'EAZ',
            'East Azarbaijan' => 'EAZ',
            'آذربایجان غربی' => 'WAZ',
            'West Azarbaijan' => 'WAZ',
            'چهارمحال و بختیاری' => 'CHB',
            'چهارمحال وبختیاری' => 'CHB',
            'Chaharmahal and Bakhtiari' => 'CHB',
            'Chahar Mahall and Bakhtiari' => 'CHB',
            'خراسان جنوبی' => 'SKH',
            'South Khorasan' => 'SKH',
            'خراسان رضوی' => 'RKH',
            'Razavi Khorasan' => 'RKH',
            'خراسان شمالی' => 'NKH',
            'North Khorasan' => 'NKH',
            'سمنان' => 'SMN',
            'Semnan' => 'SMN',
            'فارس' => 'FRS',
            'Fars' => 'FRS',
            'قم' => 'QHM',
            'Qom' => 'QHM',
            'کردستان' => 'KRD',
            'Kurdistan' => 'KRD',
            'Kordestan' => 'KRD',
            'کهگیلویه و بویراحمد' => 'KBD',
            'کهگیلوییه و بویراحمد' => 'KBD',
            'Kohgiluyeh and BoyerAhmad' => 'KBD',
            'Kohgiluyeh and Buyer Ahmad' => 'KBD',
            'گلستان' => 'GLS',
            'Golestan' => 'GLS',
            'گیلان' => 'GIL',
            'Gilan' => 'GIL',
            'مازندران' => 'MZN',
            'Mazandaran' => 'MZN',
            'مرکزی' => 'MKZ',
            'Markazi' => 'MKZ',
            'هرمزگان' => 'HRZ',
            'Hormozgan' => 'HRZ',
            'سیستان و بلوچستان' => 'SBN',
            'Sistan and Baluchestan' => 'SBN',
        );

        if (isset($aliases[$value]) && isset($catalog[$aliases[$value]])) {
            return $catalog[$aliases[$value]];
        }

        $latinKey = strtolower($value);
        foreach ($aliases as $alias => $aliasCode) {
            if (strtolower($alias) === $latinKey && isset($catalog[$aliasCode])) {
                return $catalog[$aliasCode];
            }
        }

        return array(
            'name' => $value,
            'map_name' => '',
        );
    }

    private function normalizeCityName(string $value): string
    {
        $value = $this->normalizePersian($value);
        $value = preg_replace('/^(شهرستان|شهر)\s+/u', '', $value);

        return is_string($value) ? trim($value) : '';
    }

    private function normalizePersian(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = strtr(
            $value,
            array(
                'ي' => 'ی',
                'ى' => 'ی',
                'ك' => 'ک',
                'ۀ' => 'ه',
                'ة' => 'ه',
            )
        );
        $value = preg_replace('/[\x{200c}\x{200e}\x{200f}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($value) ? $value : '';
    }

    private function key(string $value): string
    {
        $value = $this->normalizePersian($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);

        return is_string($value) ? trim($value, '-') : '';
    }


}
