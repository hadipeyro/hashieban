<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Analytics;

use DateTimeImmutable;
use Hashieban\Integration\WooCommerce\Geo\GeoAddressResolver;
use Hashieban\Integration\WooCommerce\Order\OrderAdapter;
use Throwable;
use WC_Order;
use WC_Order_Item_Product;

final class DataHealthService
{
    private OrderAdapter $orderAdapter;
    private ProductProfitabilityService $products;
    private GeoAddressResolver $geoAddress;

    public function __construct(
        OrderAdapter $orderAdapter,
        ProductProfitabilityService $products,
        GeoAddressResolver $geoAddress
    ) {
        $this->orderAdapter = $orderAdapter;
        $this->products = $products;
        $this->geoAddress = $geoAddress;
    }

    public function getReport(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $storeCurrency = get_woocommerce_currency();
        $precision = wc_get_price_decimals();
        $includedStatuses = array('processing', 'completed', 'refunded');

        $productReport = $this->products->getReport($start, $end);

        $statusBreakdown = array();
        $currencyBreakdown = array();

        $scannedOrders = 0;
        $includedOrders = 0;
        $financiallyCompleteOrders = 0;
        $excludedStatusCount = 0;
        $mixedCurrencyCount = 0;
        $calculationErrorCount = 0;
        $incompleteOrderCount = 0;
        $refundWarningCount = 0;
        $geoIncompleteCount = 0;
        $geoEligibleOrderCount = 0;
        $contactIncompleteCount = 0;
        $orphanProductLineCount = 0;

        $incompleteOrders = array();
        $mixedCurrencyOrders = array();
        $calculationErrors = array();
        $refundWarningOrders = array();
        $geoIncompleteOrders = array();
        $excludedStatusOrders = array();
        $orphanProductOrders = array();

        $page = 1;
        $maxPages = 1;

        do {
            $result = wc_get_orders(
                array(
                    'status' => $this->allWooCommerceStatuses(),
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

            foreach ((array) $result->orders as $order) {
                if (! $order instanceof WC_Order) {
                    continue;
                }

                $scannedOrders++;

                $status = (string) $order->get_status();
                $currency = (string) $order->get_currency();

                if (! isset($statusBreakdown[$status])) {
                    $statusBreakdown[$status] = 0;
                }
                $statusBreakdown[$status]++;

                if (! isset($currencyBreakdown[$currency])) {
                    $currencyBreakdown[$currency] = 0;
                }
                $currencyBreakdown[$currency]++;

                $baseRow = $this->orderBaseRow($order);

                if (! in_array($status, $includedStatuses, true)) {
                    $excludedStatusCount++;

                    if (count($excludedStatusOrders) < 20) {
                        $baseRow['reason'] = 'وضعیت «' . wc_get_order_status_name($status) . '» در تحلیل سود فعلی وارد نمی‌شود.';
                        $excludedStatusOrders[] = $baseRow;
                    }

                    continue;
                }

                if ($currency !== $storeCurrency) {
                    $mixedCurrencyCount++;

                    if (count($mixedCurrencyOrders) < 20) {
                        $baseRow['currency'] = $currency;
                        $baseRow['reason'] = 'ارز سفارش با ارز پایه فروشگاه متفاوت است.';
                        $mixedCurrencyOrders[] = $baseRow;
                    }

                    continue;
                }

                $includedOrders++;

                if ($this->geoAddress->isIranOrder($order)) {
                    $geoEligibleOrderCount++;
                    $geoMissing = $this->geoAddress->missingFields($order);

                    if ($geoMissing !== array()) {
                        $geoIncompleteCount++;

                        if (count($geoIncompleteOrders) < 20) {
                            $resolvedGeo = $this->geoAddress->resolve($order);
                            $baseRow['missing_fields'] = $geoMissing;
                            $baseRow['geo_source'] = (string) ($resolvedGeo['source'] ?? 'billing');
                            $geoIncompleteOrders[] = $baseRow;
                        }
                    }
                }

                if ($this->hasMissingContact($order)) {
                    $contactIncompleteCount++;
                }

                $orphanLines = $this->countOrphanProductLines($order);
                if ($orphanLines > 0) {
                    $orphanProductLineCount += $orphanLines;

                    if (count($orphanProductOrders) < 20) {
                        $baseRow['orphan_lines'] = $orphanLines;
                        $orphanProductOrders[] = $baseRow;
                    }
                }

                try {
                    $financial = $this->orderAdapter->fromOrder($order);
                } catch (Throwable $exception) {
                    $calculationErrorCount++;

                    if (count($calculationErrors) < 20) {
                        $baseRow['reason'] = sanitize_text_field($exception->getMessage());
                        $calculationErrors[] = $baseRow;
                    }

                    continue;
                }

                if ($financial->hasMissingData()) {
                    $incompleteOrderCount++;

                    if (count($incompleteOrders) < 30) {
                        $baseRow['missing_data'] = $financial->missingData();
                        $incompleteOrders[] = $baseRow;
                    }
                } else {
                    $financiallyCompleteOrders++;
                }

                $warnings = $financial->refundWarnings();
                if ($warnings !== array() || $financial->hasUnallocatedRefund()) {
                    $refundWarningCount++;

                    if (count($refundWarningOrders) < 20) {
                        $baseRow['warnings'] = $warnings;
                        $baseRow['has_unallocated_refund'] = $financial->hasUnallocatedRefund();
                        $refundWarningOrders[] = $baseRow;
                    }
                }
            }

            $page++;
        } while ($page <= $maxPages);

        $productsWithMissingCogs = (int) ($productReport['products_with_missing_cogs'] ?? 0);
        $productCount = (int) ($productReport['product_count'] ?? 0);

        $financialReadiness = $includedOrders > 0
            ? ($financiallyCompleteOrders / $includedOrders) * 100
            : 100.0;

        $cogsCoverage = $productCount > 0
            ? (($productCount - $productsWithMissingCogs) / $productCount) * 100
            : 100.0;

        $geoReadiness = $geoEligibleOrderCount > 0
            ? (($geoEligibleOrderCount - $geoIncompleteCount) / $geoEligibleOrderCount) * 100
            : 100.0;

        $contactReadiness = $includedOrders > 0
            ? (($includedOrders - $contactIncompleteCount) / $includedOrders) * 100
            : 100.0;

        $score = $this->calculateScore(
            $includedOrders,
            $incompleteOrderCount,
            $mixedCurrencyCount,
            $calculationErrorCount,
            $refundWarningCount,
            $orphanProductLineCount,
            $productsWithMissingCogs,
            $productCount
        );

        $issues = $this->buildIssues(
            $mixedCurrencyCount,
            $calculationErrorCount,
            $incompleteOrderCount,
            $productsWithMissingCogs,
            $refundWarningCount,
            $orphanProductLineCount,
            $geoIncompleteCount,
            $excludedStatusCount
        );

        return array(
            'currency' => $storeCurrency,
            'precision' => $precision,
            'start' => $start,
            'end' => $end,
            'health_score' => $score,
            'scanned_orders' => $scannedOrders,
            'included_orders' => $includedOrders,
            'financially_complete_orders' => $financiallyCompleteOrders,
            'excluded_status_count' => $excludedStatusCount,
            'mixed_currency_count' => $mixedCurrencyCount,
            'calculation_error_count' => $calculationErrorCount,
            'incomplete_order_count' => $incompleteOrderCount,
            'refund_warning_count' => $refundWarningCount,
            'geo_incomplete_count' => $geoIncompleteCount,
            'geo_eligible_order_count' => $geoEligibleOrderCount,
            'contact_incomplete_count' => $contactIncompleteCount,
            'orphan_product_line_count' => $orphanProductLineCount,
            'products_with_missing_cogs' => $productsWithMissingCogs,
            'product_count' => $productCount,
            'financial_readiness_percentage' => $financialReadiness,
            'cogs_coverage_percentage' => $cogsCoverage,
            'geo_readiness_percentage' => $geoReadiness,
            'contact_readiness_percentage' => $contactReadiness,
            'status_breakdown' => $statusBreakdown,
            'currency_breakdown' => $currencyBreakdown,
            'issues' => $issues,
            'incomplete_orders' => $incompleteOrders,
            'mixed_currency_orders' => $mixedCurrencyOrders,
            'calculation_errors' => $calculationErrors,
            'refund_warning_orders' => $refundWarningOrders,
            'geo_incomplete_orders' => $geoIncompleteOrders,
            'excluded_status_orders' => $excludedStatusOrders,
            'orphan_product_orders' => $orphanProductOrders,
            'missing_cogs_products' => array_slice(
                array_values(
                    array_filter(
                        (array) ($productReport['products'] ?? array()),
                        static function (array $row): bool {
                            return ! (bool) ($row['cogs_complete'] ?? false);
                        }
                    )
                ),
                0,
                20
            ),
        );
    }

    private function allWooCommerceStatuses(): array
    {
        $statuses = array();

        foreach (array_keys(wc_get_order_statuses()) as $status) {
            $statuses[] = strpos($status, 'wc-') === 0
                ? substr($status, 3)
                : $status;
        }

        return array_values(array_unique($statuses));
    }

    private function orderBaseRow(WC_Order $order): array
    {
        $date = $order->get_date_created();

        return array(
            'order_id' => (int) $order->get_id(),
            'order_number' => (string) $order->get_order_number(),
            'status' => (string) $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'created_at' => $date
                ? (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone(wp_timezone())
                : null,
            'customer_name' => $this->customerName($order),
            'edit_url' => $this->orderEditUrl($order),
        );
    }

    private function hasMissingContact(WC_Order $order): bool
    {
        $email = trim((string) $order->get_billing_email());
        $phone = trim((string) $order->get_billing_phone());

        return $email === '' && $phone === '';
    }

    private function countOrphanProductLines(WC_Order $order): int
    {
        $count = 0;

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            if (! $item->get_product()) {
                $count++;
            }
        }

        return $count;
    }

    private function calculateScore(
        int $includedOrders,
        int $incompleteOrders,
        int $mixedCurrencyOrders,
        int $calculationErrors,
        int $refundWarnings,
        int $orphanProductLines,
        int $missingCogsProducts,
        int $productCount
    ): int {
        $score = 100;

        if ($includedOrders > 0) {
            $score -= min(
                30,
                (int) round(($incompleteOrders / $includedOrders) * 35)
            );

            $score -= min(
                10,
                (int) round(($refundWarnings / $includedOrders) * 20)
            );
        }

        if ($productCount > 0) {
            $score -= min(
                20,
                (int) round(($missingCogsProducts / $productCount) * 25)
            );
        }

        $score -= min(20, $calculationErrors * 5);
        $score -= min(15, $mixedCurrencyOrders * 3);
        $score -= min(15, $orphanProductLines * 3);

        return max(0, min(100, $score));
    }

    private function buildIssues(
        int $mixedCurrencyCount,
        int $calculationErrorCount,
        int $incompleteOrderCount,
        int $productsWithMissingCogs,
        int $refundWarningCount,
        int $orphanProductLineCount,
        int $geoIncompleteCount,
        int $excludedStatusCount
    ): array {
        $issues = array();

        if ($calculationErrorCount > 0) {
            $issues[] = array(
                'severity' => 'critical',
                'title' => 'خطا در محاسبه بعضی سفارش‌ها',
                'message' => 'حاشیه‌بان نتوانسته اطلاعات مالی برخی سفارش‌ها را به‌طور کامل بخواند. این موارد باید قبل از اتکا به گزارش‌ها بررسی شوند.',
                'metric' => number_format_i18n($calculationErrorCount) . ' سفارش',
                'action' => 'ردیف‌های خطادار را باز کنید و اطلاعات سفارش و افزونه‌های مالی مرتبط را بررسی کنید.',
            );
        }

        if ($mixedCurrencyCount > 0) {
            $issues[] = array(
                'severity' => 'critical',
                'title' => 'سفارش با ارز متفاوت شناسایی شد',
                'message' => 'نسخه فعلی گزارش‌ها تک‌ارزی است و سفارش‌های با ارز متفاوت عمداً با اعداد فروشگاه جمع نمی‌شوند.',
                'metric' => number_format_i18n($mixedCurrencyCount) . ' سفارش',
                'action' => 'ارز سفارش‌ها را بررسی کنید؛ در صورت نیاز تحلیل چندارزی باید در نسخه پیشرفته فعال شود.',
            );
        }

        if ($incompleteOrderCount > 0) {
            $issues[] = array(
                'severity' => 'warning',
                'title' => 'داده مالی بعضی سفارش‌ها ناقص است',
                'message' => 'حداقل یک مؤلفه لازم برای محاسبه سود قابل اتکا در این سفارش‌ها کامل نیست.',
                'metric' => number_format_i18n($incompleteOrderCount) . ' سفارش',
                'action' => 'جزئیات COGS و اقلام سفارش را بررسی و داده ناقص را تکمیل کنید.',
            );
        }

        if ($productsWithMissingCogs > 0) {
            $issues[] = array(
                'severity' => 'warning',
                'title' => 'پوشش COGS کامل نیست',
                'message' => 'محصولاتی وجود دارند که قیمت خرید آن‌ها برای حداقل یک ردیف فروش قابل تشخیص نیست.',
                'metric' => number_format_i18n($productsWithMissingCogs) . ' محصول',
                'action' => 'از صفحه سودآوری محصولات، محصول‌های ناقص را باز و COGS را تکمیل کنید.',
            );
        }

        if ($refundWarningCount > 0) {
            $issues[] = array(
                'severity' => 'warning',
                'title' => 'Refund نیازمند بازبینی وجود دارد',
                'message' => 'برای بعضی Refundها تخصیص مبلغ یا بازیابی COGS کاملاً قابل تطبیق نبوده است.',
                'metric' => number_format_i18n($refundWarningCount) . ' سفارش',
                'action' => 'جزئیات Refund همان سفارش را در مرکز سفارش‌ها بررسی کنید.',
            );
        }

        if ($orphanProductLineCount > 0) {
            $issues[] = array(
                'severity' => 'warning',
                'title' => 'آیتم سفارش بدون محصول فعال',
                'message' => 'برخی ردیف‌های تاریخی به محصولی اشاره می‌کنند که دیگر در کاتالوگ قابل بازیابی نیست.',
                'metric' => number_format_i18n($orphanProductLineCount) . ' ردیف',
                'action' => 'سفارش تاریخی را نگه دارید؛ حذف محصول نباید تاریخچه مالی را پاک کند، ولی این ردیف‌ها را برای گزارش محصول بررسی کنید.',
            );
        }

        if ($geoIncompleteCount > 0) {
            $issues[] = array(
                'severity' => 'info',
                'title' => 'آمادگی داده جغرافیایی کامل نیست',
                'message' => 'برای بخشی از سفارش‌های ایرانی قدیمی استان یا شهر کامل نیست. حاشیه‌بان از این مرحله به بعد این دو فیلد را در Checkout ایران اجباری می‌کند تا Geo Intelligence داده قابل اتکاتری داشته باشد.',
                'metric' => number_format_i18n($geoIncompleteCount) . ' سفارش',
                'action' => 'سفارش‌های قدیمی ناقص را در صورت نیاز تکمیل کنید؛ سفارش‌های جدید ایران با استان و شهر اجباری ثبت می‌شوند.',
            );
        }

        if ($excludedStatusCount > 0) {
            $issues[] = array(
                'severity' => 'info',
                'title' => 'برخی سفارش‌ها طبق قاعده تحلیل نشده‌اند',
                'message' => 'سفارش‌های Pending، Cancelled، Failed و وضعیت‌های مشابه فعلاً در سود نهایی وارد نمی‌شوند.',
                'metric' => number_format_i18n($excludedStatusCount) . ' سفارش',
                'action' => 'این حذف عمدی است؛ فقط در صورت نیاز قواعد وضعیت سفارش را در مراحل پیشرفته قابل تنظیم می‌کنیم.',
            );
        }

        if ($issues === array()) {
            $issues[] = array(
                'severity' => 'good',
                'title' => 'داده‌های این بازه سالم‌اند',
                'message' => 'مشکل مهمی در COGS، ارز، Refund، قابلیت محاسبه یا آمادگی داده جغرافیایی دیده نشد.',
                'metric' => 'آماده تحلیل',
                'action' => 'نیازی به اقدام فوری نیست.',
            );
        }

        return $issues;
    }

    private function customerName(WC_Order $order): string
    {
        $name = trim(
            (string) $order->get_formatted_billing_full_name()
        );

        if ($name !== '') {
            return $name;
        }

        return 'مهمان / بدون نام';
    }

    private function orderEditUrl(WC_Order $order): string
    {
        if (method_exists($order, 'get_edit_order_url')) {
            return (string) $order->get_edit_order_url();
        }

        return admin_url(
            'post.php?post=' . $order->get_id() . '&action=edit'
        );
    }
}
