<?php

declare(strict_types=1);

namespace Hashieban\Finance;

use DateTimeInterface;
use Hashieban\Domain\Money\Money;

final class StoreExpenseRepository
{
    private const DATABASE_VERSION = '2';

    private const DATABASE_VERSION_OPTION =
        'hashieban_expenses_database_version';

    public function registerSchema(): void
    {
        add_action(
            'admin_init',
            array($this, 'maybeInstall')
        );
    }

    public function maybeInstall(): void
    {
        $installedVersion = get_option(
            self::DATABASE_VERSION_OPTION,
            ''
        );

        if (
            $installedVersion
            === self::DATABASE_VERSION
        ) {
            return;
        }

        global $wpdb;

        $tableName = $this->tableName();

        $charsetCollate =
            $wpdb->get_charset_collate();

        $sql = "
            CREATE TABLE {$tableName} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(190) NOT NULL,
                category_id VARCHAR(64) NOT NULL DEFAULT '',
                category VARCHAR(190) NOT NULL DEFAULT '',
                amount_minor BIGINT NOT NULL DEFAULT 0,
                currency VARCHAR(12) NOT NULL,
                precision_value TINYINT UNSIGNED NOT NULL DEFAULT 0,
                expense_date DATE NOT NULL,
                note TEXT NULL,
                created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY expense_date (expense_date),
                KEY category_id (category_id),
                KEY currency_date (currency, expense_date)
            ) {$charsetCollate};
        ";

        require_once ABSPATH
      . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);

        update_option(
            self::DATABASE_VERSION_OPTION,
            self::DATABASE_VERSION
        );
    }

    public function add(
        string $title,
        string $categoryId,
        string $categorySnapshot,
        Money $amount,
        string $expenseDate,
        string $note,
        int $createdBy
    ): int {
        global $wpdb;

        $wpdb->insert(
            $this->tableName(),
            array(
                'title' =>
                    $title,

                'category_id' =>
                    $categoryId,

                'category' =>
                    $categorySnapshot,

                'amount_minor' =>
                    $amount->minorAmount(),

                'currency' =>
                    $amount->currency(),

                'precision_value' =>
                    $amount->precision(),

                'expense_date' =>
                    $expenseDate,

                'note' =>
                    $note,

                'created_by' =>
                    $createdBy,

                'created_at' =>
                    current_time('mysql'),
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
            )
        );

        return (int) $wpdb->insert_id;
    }

    public function delete(
        int $id
    ): void {
        global $wpdb;

        $wpdb->delete(
            $this->tableName(),
            array(
                'id' => $id,
            ),
            array(
                '%d',
            )
        );
    }

    public function sumBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency,
        int $precision
    ): Money {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT COALESCE(
                SUM(amount_minor),
                0
            )
            FROM {$this->tableName()}
            WHERE currency = %s
              AND expense_date >= %s
              AND expense_date <= %s
            ",
            $currency,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        return new Money(
            (int) $wpdb->get_var($sql),
            $currency,
            $precision
        );
    }

    public function totalsByDateBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT
                expense_date,
                SUM(amount_minor) AS amount_minor
            FROM {$this->tableName()}
            WHERE currency = %s
              AND expense_date >= %s
              AND expense_date <= %s
            GROUP BY expense_date
            ORDER BY expense_date ASC
            ",
            $currency,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        $rows = $wpdb->get_results(
            $sql,
            ARRAY_A
        );

        return is_array($rows)
        ? $rows
             : array();
    }

    public function totalsByCategoryBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $currency
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "
            SELECT
                category_id,
                category,
                SUM(amount_minor) AS amount_minor
            FROM {$this->tableName()}
            WHERE currency = %s
              AND expense_date >= %s
              AND expense_date <= %s
            GROUP BY category_id, category
            ORDER BY amount_minor DESC
            ",
            $currency,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        $rows = $wpdb->get_results(
            $sql,
            ARRAY_A
        );

        return is_array($rows)
        ? $rows
             : array();
    }

    public function paginate(
        int $page,
        int $perPage
    ): array {
        global $wpdb;

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $offset =
            ($page - 1) * $perPage;

        $sql = $wpdb->prepare(
            "
            SELECT *
            FROM {$this->tableName()}
            ORDER BY expense_date DESC, id DESC
            LIMIT %d OFFSET %d
            ",
            $perPage,
            $offset
        );

        $rows = $wpdb->get_results(
            $sql,
            ARRAY_A
        );

        return is_array($rows)
        ? $rows
             : array();
    }

    public function count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "
            SELECT COUNT(*)
            FROM {$this->tableName()}
            "
        );
    }

    private function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix
             . 'hashieban_expenses';
    }
}
