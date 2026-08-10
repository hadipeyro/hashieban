<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Snapshot;

use WC_Order;

final class ProfitSnapshotRepository
{
    private const CURRENT_META_KEY =
        '_hashieban_profit_snapshot';

    private const REVISION_META_KEY =
        '_hashieban_profit_snapshot_revision';

    public function current(
        WC_Order $order
    ): ?array {
        $snapshot =
            $order->get_meta(
                self::CURRENT_META_KEY,
                true
            );

        if (! is_array($snapshot)) {
            return null;
        }

        if (
            (int) (
                $snapshot['schema_version']
                ?? 0
            ) !== 1
        ) {
            return null;
        }

        if (
            ! isset($snapshot['financial'])
            || ! is_array(
                $snapshot['financial']
            )
        ) {
            return null;
        }

        return $snapshot;
    }

    public function has(
        WC_Order $order
    ): bool {
        return $this->current($order)
            !== null;
    }

    public function revisionCount(
        WC_Order $order
    ): int {
        $current = $this->current($order);

        if ($current === null) {
            return 0;
        }

        return max(
            1,
            (int) (
                $current['revision']
                ?? 1
            )
        );
    }

    public function save(
        WC_Order $order,
        array $snapshot
    ): void {
        $order->update_meta_data(
            self::CURRENT_META_KEY,
            $snapshot
        );

        $order->add_meta_data(
            self::REVISION_META_KEY,
            $snapshot,
            false
        );

        $order->save_meta_data();
    }
}
