<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Performance;

use DateTimeInterface;

final class OrderMetricsRepository
{
    private const DATABASE_VERSION = '2';

    private const DATABASE_VERSION_OPTION =
        'hashieban_order_metrics_database_version';

    private const READY_OPTION =
        'hashieban_order_metrics_ready';

    public function registerSchema(): void
    {
        add_action(
            'admin_init',
            array($this, 'maybeInstall')
        );
    }

    public function maybeInstall(): void
    {
        $installedVersion = (string) get_option(
            self::DATABASE_VERSION_OPTION,
            ''
        );

        if ($installedVersion === self::DATABASE_VERSION) {
            return;
        }

        global $wpdb;

        $metricsTable = $this->metricsTable();
        $categoryTable = $this->categoryTable();
        $charsetCollate = $wpdb->get_charset_collate();

        $metricsSql = "
            CREATE TABLE {$metricsTable} (
                order_id BIGINT UNSIGNED NOT NULL,
                order_date_local DATETIME NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT '',
                currency VARCHAR(12) NOT NULL DEFAULT '',
                revenue_minor BIGINT NOT NULL DEFAULT 0,
                cogs_minor BIGINT NOT NULL DEFAULT 0,
                direct_costs_minor BIGINT NOT NULL DEFAULT 0,
                global_order_costs_minor BIGINT NOT NULL DEFAULT 0,
                profit_minor BIGINT NOT NULL DEFAULT 0,
                incomplete TINYINT UNSIGNED NOT NULL DEFAULT 0,
                margin_bps INT NULL,
                snapshot_revision INT UNSIGNED NOT NULL DEFAULT 1,
                channel_key VARCHAR(100) NOT NULL DEFAULT 'unknown',
                channel_name VARCHAR(191) NOT NULL DEFAULT '',
                channel_group VARCHAR(32) NOT NULL DEFAULT 'unknown',
                attribution_known TINYINT UNSIGNED NOT NULL DEFAULT 0,
                attribution_source_type VARCHAR(64) NOT NULL DEFAULT '',
                attribution_source VARCHAR(191) NOT NULL DEFAULT '',
                attribution_medium VARCHAR(191) NOT NULL DEFAULT '',
                attribution_campaign VARCHAR(191) NOT NULL DEFAULT '',
                attribution_referrer_domain VARCHAR(191) NOT NULL DEFAULT '',
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (order_id),
                KEY currency_date (currency, order_date_local),
                KEY order_date_local (order_date_local),
                KEY status (status),
                KEY channel_date (channel_key, order_date_local),
                KEY attribution_campaign (attribution_campaign(100))
            ) {$charsetCollate};
        ";

        $categorySql = "
            CREATE TABLE {$categoryTable} (
                order_id BIGINT UNSIGNED NOT NULL,
                category_id VARCHAR(64) NOT NULL DEFAULT '',
                amount_minor BIGINT NOT NULL DEFAULT 0,
                PRIMARY KEY (order_id, category_id),
                KEY category_id (category_id)
            ) {$charsetCollate};
        ";

        require_once ABSPATH
            . 'wp-admin/includes/upgrade.php';

        dbDelta($metricsSql);
        dbDelta($categorySql);

        update_option(
            self::DATABASE_VERSION_OPTION,
            self::DATABASE_VERSION
        );

        $this->markNotReady();
    }

    public function isReady(): bool
    {
        return (string) get_option(
            self::DATABASE_VERSION_OPTION,
            ''
        ) === self::DATABASE_VERSION
            && (bool) get_option(
                self::READY_OPTION,
                false
            );
    }

    public function markReady(): void
    {
        update_option(
            self::READY_OPTION,
            1,
            false
        );
    }

    public function markNotReady(): void
    {
        update_option(
            self::READY_OPTION,
            0,
            false
        );
    }

    public function clearAll(): void
    {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$this->categoryTable()}"
        );

        $wpdb->query(
            "DELETE FROM {$this->metricsTable()}"
        );

        $this->markNotReady();
    }

    public function replaceOrder(
        array $row,
        array $categoryTotals
    ): bool {
        if (! $this->isSchemaCurrent()) {
            $this->maybeInstall();
        }

        global $wpdb;

        $orderId = (int) ($row['order_id'] ?? 0);

        if ($orderId <= 0) {
            return false;
        }

        $marginBps = array_key_exists('margin_bps', $row)
            && $row['margin_bps'] !== null
        ? (int) $row['margin_bps']
            : null;

        $result = $wpdb->replace(
            $this->metricsTable(),
            array(
                'order_id' => $orderId,
                'order_date_local' =>
                    (string) ($row['order_date_local'] ?? ''),
                'status' =>
                    (string) ($row['status'] ?? ''),
                'currency' =>
                    (string) ($row['currency'] ?? ''),
                'revenue_minor' =>
                    (int) ($row['revenue_minor'] ?? 0),
                'cogs_minor' =>
                    (int) ($row['cogs_minor'] ?? 0),
                'direct_costs_minor' =>
                    (int) ($row['direct_costs_minor'] ?? 0),
                'global_order_costs_minor' =>
                    (int) ($row['global_order_costs_minor'] ?? 0),
                'profit_minor' =>
                    (int) ($row['profit_minor'] ?? 0),
                'incomplete' =>
                    ! empty($row['incomplete']) ? 1 : 0,
                'margin_bps' => $marginBps,
                'snapshot_revision' =>
                    max(1, (int) ($row['snapshot_revision'] ?? 1)),
                'channel_key' =>
                    (string) ($row['channel_key'] ?? 'unknown'),
                'channel_name' =>
                    (string) ($row['channel_name'] ?? ''),
                'channel_group' =>
                    (string) ($row['channel_group'] ?? 'unknown'),
                'attribution_known' =>
                    ! empty($row['attribution_known']) ? 1 : 0,
                'attribution_source_type' =>
                    (string) ($row['attribution_source_type'] ?? ''),
                'attribution_source' =>
                    (string) ($row['attribution_source'] ?? ''),
                'attribution_medium' =>
                    (string) ($row['attribution_medium'] ?? ''),
                'attribution_campaign' =>
                    (string) ($row['attribution_campaign'] ?? ''),
                'attribution_referrer_domain' =>
                    (string) ($row['attribution_referrer_domain'] ?? ''),
                'updated_at' => current_time('mysql'),
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                $marginBps === null ? '%s' : '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if ($result === false) {
            return false;
        }

        $wpdb->delete(
            $this->categoryTable(),
            array('order_id' => $orderId),
            array('%d')
        );

        foreach ($categoryTotals as $categoryId => $amountMinor) {
            $categoryId = sanitize_key((string) $categoryId);
            $amountMinor = (int) $amountMinor;

            if ($categoryId === '' || $amountMinor <= 0) {
                continue;
            }

            $wpdb->insert(
                $this->categoryTable(),
                array(
                    'order_id' => $orderId,
                    'category_id' => $categoryId,
                    'amount_minor' => $amountMinor,
                ),
                array('%d', '%s', '%d')
            );
        }

        return true;
    }

    public function deleteOrder(int $orderId): void
    {
        global $wpdb;

        if ($orderId <= 0) {
            return;
        }

        $wpdb->delete(
            $this->categoryTable(),
            array('order_id' => $orderId),
            array('%d')
        );

        $wpdb->delete(
            $this->metricsTable(),
            array('order_id' => $orderId),
            array('%d')
        );
    }

    public function countIndexed(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->metricsTable()}"
        );
    }

    public function summaryBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT
                COUNT(*) AS order_count,
                COALESCE(SUM(revenue_minor), 0) AS revenue_minor,
                COALESCE(SUM(cogs_minor), 0) AS cogs_minor,
                COALESCE(SUM(direct_costs_minor), 0) AS direct_costs_minor,
                COALESCE(SUM(global_order_costs_minor), 0) AS global_order_costs_minor,
                COALESCE(SUM(profit_minor), 0) AS profit_minor,
                COALESCE(SUM(incomplete), 0) AS incomplete_count
            FROM {$this->metricsTable()}
            WHERE currency = %s
              AND order_date_local >= %s
              AND order_date_local <= %s
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! is_array($row)) {
            return array();
        }

        return array(
            'order_count' => (int) ($row['order_count'] ?? 0),
            'revenue_minor' => (int) ($row['revenue_minor'] ?? 0),
            'cogs_minor' => (int) ($row['cogs_minor'] ?? 0),
            'direct_costs_minor' => (int) ($row['direct_costs_minor'] ?? 0),
            'global_order_costs_minor' => (int) ($row['global_order_costs_minor'] ?? 0),
            'profit_minor' => (int) ($row['profit_minor'] ?? 0),
            'incomplete_count' => (int) ($row['incomplete_count'] ?? 0),
        );
    }

    public function dailyBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT
                DATE(order_date_local) AS day_key,
                COALESCE(SUM(revenue_minor), 0) AS revenue_minor,
                COALESCE(SUM(cogs_minor), 0) AS cogs_minor,
                COALESCE(SUM(direct_costs_minor), 0) AS direct_costs_minor,
                COALESCE(SUM(global_order_costs_minor), 0) AS global_order_costs_minor,
                COALESCE(SUM(profit_minor), 0) AS profit_minor
            FROM {$this->metricsTable()}
            WHERE currency = %s
              AND order_date_local >= %s
              AND order_date_local <= %s
            GROUP BY DATE(order_date_local)
            ORDER BY day_key ASC
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function categoryTotalsBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT
                categories.category_id,
                COALESCE(SUM(categories.amount_minor), 0) AS amount_minor
            FROM {$this->categoryTable()} categories
            INNER JOIN {$this->metricsTable()} metrics
                ON metrics.order_id = categories.order_id
            WHERE metrics.currency = %s
              AND metrics.order_date_local >= %s
              AND metrics.order_date_local <= %s
            GROUP BY categories.category_id
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function recentBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency,
        int $limit = 8
    ): array {
        global $wpdb;

        $limit = max(1, min(20, $limit));

        $sql = $wpdb->prepare(
            "
            SELECT
                order_id,
                revenue_minor,
                profit_minor,
                incomplete,
                margin_bps,
                order_date_local
            FROM {$this->metricsTable()}
            WHERE currency = %s
              AND order_date_local >= %s
              AND order_date_local <= %s
            ORDER BY order_date_local DESC, order_id DESC
            LIMIT %d
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function channelSummaryBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT
                channel_key,
                MAX(channel_name) AS channel_name,
                MAX(channel_group) AS channel_group,
                COUNT(*) AS order_count,
                COALESCE(SUM(revenue_minor), 0) AS revenue_minor,
                COALESCE(SUM(profit_minor), 0) AS profit_minor,
                COALESCE(SUM(incomplete), 0) AS incomplete_count,
                COALESCE(SUM(attribution_known), 0) AS attributed_order_count
            FROM {$this->metricsTable()}
            WHERE currency = %s
              AND order_date_local >= %s
              AND order_date_local <= %s
            GROUP BY channel_key
            ORDER BY revenue_minor DESC, order_count DESC
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function campaignSummaryBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency,
        int $limit = 20
    ): array {
        global $wpdb;

        $limit = max(1, min(100, $limit));

        $sql = $wpdb->prepare(
            "
            SELECT
                attribution_campaign AS campaign,
                attribution_source AS source,
                attribution_medium AS medium,
                channel_key,
                MAX(channel_name) AS channel_name,
                COUNT(*) AS order_count,
                COALESCE(SUM(revenue_minor), 0) AS revenue_minor,
                COALESCE(SUM(profit_minor), 0) AS profit_minor
            FROM {$this->metricsTable()}
            WHERE currency = %s
              AND order_date_local >= %s
              AND order_date_local <= %s
              AND attribution_campaign <> ''
            GROUP BY
                attribution_campaign,
                attribution_source,
                attribution_medium,
                channel_key
            ORDER BY revenue_minor DESC, order_count DESC
            LIMIT %d
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function referrerSummaryBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency,
        int $limit = 20
    ): array {
        global $wpdb;

        $limit = max(1, min(100, $limit));

        $sql = $wpdb->prepare(
            "
            SELECT
                attribution_referrer_domain AS referrer_domain,
                channel_key,
                MAX(channel_name) AS channel_name,
                COUNT(*) AS order_count,
                COALESCE(SUM(revenue_minor), 0) AS revenue_minor,
                COALESCE(SUM(profit_minor), 0) AS profit_minor
            FROM {$this->metricsTable()}
            WHERE currency = %s
              AND order_date_local >= %s
              AND order_date_local <= %s
              AND attribution_referrer_domain <> ''
            GROUP BY attribution_referrer_domain, channel_key
            ORDER BY revenue_minor DESC, order_count DESC
            LIMIT %d
            ",
            $currency,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    private function isSchemaCurrent(): bool
    {
        return (string) get_option(
            self::DATABASE_VERSION_OPTION,
            ''
        ) === self::DATABASE_VERSION;
    }

    private function metricsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix
            . 'hashieban_order_metrics';
    }

    private function categoryTable(): string
    {
        global $wpdb;

        return $wpdb->prefix
            . 'hashieban_order_cost_metrics';
    }
}
