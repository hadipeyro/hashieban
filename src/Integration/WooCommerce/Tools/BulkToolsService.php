<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Tools;

use Hashieban\Integration\WooCommerce\Compatibility;
use Hashieban\Integration\WooCommerce\Geo\GeoAddressCapture;
use Hashieban\Integration\WooCommerce\Snapshot\ProfitSnapshotService;
use WC_Order;
use WC_Product;

final class BulkToolsService
{
    private Compatibility $compatibility;
    private GeoAddressCapture $geoCapture;
    private ProfitSnapshotService $profitSnapshots;

    public function __construct(
        Compatibility $compatibility,
        GeoAddressCapture $geoCapture,
        ProfitSnapshotService $profitSnapshots
    ) {
        $this->compatibility = $compatibility;
        $this->geoCapture = $geoCapture;
        $this->profitSnapshots = $profitSnapshots;
    }

    public function isCogsEnabled(): bool
    {
        return $this->compatibility->isCogsEnabled();
    }

    public function exportProductCogsCsv(): void
    {
        $filename = 'hashieban-product-cogs-' . gmdate('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            wp_die(esc_html('ایجاد فایل خروجی ممکن نشد.'));
        }

        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv(
            $stream,
            array(
                'product_id',
                'parent_id',
                'type',
                'sku',
                'name',
                'cogs_store_unit',
                'store_currency',
            )
        );

        $page = 1;
        $maxPages = 1;

        do {
            $result = wc_get_products(
                array(
                    'status' => array('publish', 'private', 'draft', 'pending'),
                    'limit' => 100,
                    'page' => $page,
                    'paginate' => true,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                )
            );

            if (! is_object($result) || ! isset($result->products)) {
                break;
            }

            $maxPages = isset($result->max_num_pages)
                ? max(1, (int) $result->max_num_pages)
                : 1;

            foreach ((array) $result->products as $product) {
                if (! $product instanceof WC_Product) {
                    continue;
                }

                $this->writeProductRow($stream, $product);

                if ($product->is_type('variable')) {
                    foreach ((array) $product->get_children() as $variationId) {
                        $variation = wc_get_product((int) $variationId);

                        if ($variation instanceof WC_Product) {
                            $this->writeProductRow($stream, $variation);
                        }
                    }
                }
            }

            $page++;
        } while ($page <= $maxPages);

        fclose($stream);
        exit;
    }

    public function importProductCogsCsv(string $filePath): array
    {
        $result = array(
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        if (! $this->isCogsEnabled()) {
            $result['errors'][] = 'قابلیت Cost of Goods Sold ووکامرس فعال نیست.';
            return $result;
        }

        if (! is_readable($filePath)) {
            $result['errors'][] = 'فایل CSV قابل خواندن نیست.';
            return $result;
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            $result['errors'][] = 'باز کردن فایل CSV ممکن نشد.';
            return $result;
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);
            $result['errors'][] = 'ردیف عنوان CSV معتبر نیست.';
            return $result;
        }

        $headers = array_map(
            static function ($value): string {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
                return sanitize_key(trim((string) $value));
            },
            $headers
        );

        $required = array('product_id', 'sku', 'cogs_store_unit');

        foreach ($required as $column) {
            if (! in_array($column, $headers, true)) {
                fclose($handle);
                $result['errors'][] = 'ستون ضروری «' . $column . '» در CSV وجود ندارد.';
                return $result;
            }
        }

        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (! is_array($row) || $row === array()) {
                continue;
            }

            $data = array();

            foreach ($headers as $index => $header) {
                $data[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $productId = absint((string) ($data['product_id'] ?? '0'));
            $sku = sanitize_text_field((string) ($data['sku'] ?? ''));
            $rawCogs = (string) ($data['cogs_store_unit'] ?? '');

            if ($productId <= 0 && $sku !== '') {
                $productId = (int) wc_get_product_id_by_sku($sku);
            }

            if ($productId <= 0) {
                $result['skipped']++;
                $this->addError($result, 'ردیف ' . $rowNumber . ': محصول با ID/SKU داده‌شده پیدا نشد.');
                continue;
            }

            $product = wc_get_product($productId);

            if (! $product instanceof WC_Product) {
                $result['skipped']++;
                $this->addError($result, 'ردیف ' . $rowNumber . ': محصول قابل بازیابی نیست.');
                continue;
            }

            $normalized = $this->normalizeDecimal($rawCogs);

            if ($normalized === null || $normalized < 0) {
                $result['skipped']++;
                $this->addError($result, 'ردیف ' . $rowNumber . ': مقدار COGS نامعتبر است.');
                continue;
            }

            if (! method_exists($product, 'set_cogs_value')) {
                $result['skipped']++;
                $this->addError($result, 'ردیف ' . $rowNumber . ': نسخه ووکامرس این محصول API نوشتن COGS را ندارد.');
                continue;
            }

            $current = method_exists($product, 'get_cogs_value')
                ? (float) $product->get_cogs_value()
                : null;

            if ($current !== null && abs($current - $normalized) < 0.000001) {
                $result['unchanged']++;
                continue;
            }

            $product->set_cogs_value($normalized);
            $product->save();
            $result['updated']++;
        }

        fclose($handle);

        return $result;
    }

    public function backfillGeoBatch(int $page, int $batchSize = 100): array
    {
        $page = max(1, $page);
        $batchSize = max(20, min(250, $batchSize));

        $statuses = array_map(
            static function (string $status): string {
                return strpos($status, 'wc-') === 0
                    ? substr($status, 3)
                    : $status;
            },
            array_keys(wc_get_order_statuses())
        );

        $query = wc_get_orders(
            array(
                'status' => $statuses,
                'limit' => $batchSize,
                'page' => $page,
                'paginate' => true,
                'orderby' => 'date',
                'order' => 'ASC',
            )
        );

        $summary = array(
            'page' => $page,
            'processed' => 0,
            'complete' => 0,
            'incomplete' => 0,
            'non_iran' => 0,
            'max_pages' => 1,
            'next_page' => null,
        );

        if (! is_object($query) || ! isset($query->orders)) {
            return $summary;
        }

        $maxPages = isset($query->max_num_pages)
            ? max(1, (int) $query->max_num_pages)
            : 1;

        $summary['max_pages'] = $maxPages;

        foreach ((array) $query->orders as $order) {
            if (! $order instanceof WC_Order) {
                continue;
            }

            $geo = $this->geoCapture->snapshotOrder($order);
            $summary['processed']++;

            if (($geo['country'] ?? '') !== 'IR') {
                $summary['non_iran']++;
                continue;
            }

            if (! empty($geo['complete'])) {
                $summary['complete']++;
            } else {
                $summary['incomplete']++;
            }
        }

        if ($page < $maxPages) {
            $summary['next_page'] = $page + 1;
        }

        return $summary;
    }


    public function backfillProfitSnapshotsBatch(
        int $page,
        int $batchSize = 100
    ): array {
        $page = max(1, $page);
        $batchSize = max(
            20,
            min(250, $batchSize)
        );

        $query = wc_get_orders(
            array(
                'status' => array(
                    'processing',
                    'completed',
                    'refunded',
                ),
                'limit' => $batchSize,
                'page' => $page,
                'paginate' => true,
                'orderby' => 'date',
                'order' => 'ASC',
            )
        );

        $summary = array(
            'page' => $page,
            'processed' => 0,
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'max_pages' => 1,
            'next_page' => null,
        );

        if (
            ! is_object($query)
            || ! isset($query->orders)
        ) {
            return $summary;
        }

        $maxPages =
            isset($query->max_num_pages)
        ? max(
            1,
            (int) $query->max_num_pages
        )
            : 1;

        $summary['max_pages'] =
            $maxPages;

        foreach (
            (array) $query->orders
            as $order
        ) {
            if (! $order instanceof WC_Order) {
                continue;
            }

            $result =
                $this->profitSnapshots
                     ->captureMissing(
                         $order,
                         'legacy-backfill'
                     );

            $summary['processed']++;

            $status =
                (string) (
                    $result['status']
                    ?? 'skipped'
                );

            if (
                ! isset($summary[$status])
            ) {
                $status = 'skipped';
            }

            $summary[$status]++;
        }

        if ($page < $maxPages) {
            $summary['next_page'] =
                $page + 1;
        }

        return $summary;
    }

    private function writeProductRow($stream, WC_Product $product): void
    {
        $cogs = method_exists($product, 'get_cogs_value')
            ? $product->get_cogs_value()
            : null;

        fputcsv(
            $stream,
            array(
                $product->get_id(),
                $product->get_parent_id(),
                $product->get_type(),
                $product->get_sku(),
                wp_strip_all_tags($product->get_name()),
                $cogs === null ? '' : wc_format_decimal((string) $cogs),
                get_woocommerce_currency(),
            )
        );
    }

    private function normalizeDecimal(string $value): ?float
    {
        $value = strtr(
            trim($value),
            array(
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                ',' => '', '٬' => '', ' ' => '',
                '٫' => '.',
            )
        );

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) wc_format_decimal($value);
    }

    private function addError(array &$result, string $message): void
    {
        if (count($result['errors']) < 20) {
            $result['errors'][] = $message;
        }
    }
}
