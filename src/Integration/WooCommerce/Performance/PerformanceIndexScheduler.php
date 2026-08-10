<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Performance;

use Hashieban\Integration\WooCommerce\Tools\BulkToolsService;

final class PerformanceIndexScheduler
{
    private const ACTION = 'hashieban_performance_index_auto_batch';

    private const STATE_OPTION = 'hashieban_performance_index_auto_state';

    private const LOCK_TRANSIENT = 'hashieban_performance_index_auto_lock';

    private OrderMetricsIndexer $indexer;

    private BulkToolsService $tools;

    public function __construct(
        OrderMetricsIndexer $indexer,
        BulkToolsService $tools
    ) {
        $this->indexer = $indexer;
        $this->tools = $tools;
    }

    public function register(): void
    {
        add_action(
            'admin_init',
            array($this, 'maybeSchedule'),
            40
        );

        add_action(
            self::ACTION,
            array($this, 'runBatch')
        );
    }

    public function maybeSchedule(): void
    {
        if ($this->indexer->isReady()) {
            delete_option(self::STATE_OPTION);
            return;
        }

        if ($this->hasPendingAction()) {
            return;
        }

        $this->scheduleNext();
    }

    public function runBatch(): void
    {
        if ($this->indexer->isReady()) {
            delete_option(self::STATE_OPTION);
            return;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            $this->scheduleNext(45);
            return;
        }

        set_transient(
            self::LOCK_TRANSIENT,
            1,
            120
        );

        try {
            $state = get_option(
                self::STATE_OPTION,
                array('next_page' => 1)
            );

            $page = is_array($state)
                ? max(1, (int) ($state['next_page'] ?? 1))
                : 1;

            $result = $this->tools
                ->rebuildOrderMetricsBatch(
                    $page,
                    100
                );

            if (! empty($result['ready'])) {
                delete_option(self::STATE_OPTION);
                return;
            }

            $nextPage = isset($result['next_page'])
                && $result['next_page'] !== null
            ? max(1, (int) $result['next_page'])
                : 1;

            update_option(
                self::STATE_OPTION,
                array(
                    'next_page' => $nextPage,
                    'updated_at' => current_time('mysql'),
                ),
                false
            );

            $this->scheduleNext();
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    private function hasPendingAction(): bool
    {
        if (function_exists('as_next_scheduled_action')) {
            return as_next_scheduled_action(
                self::ACTION,
                array(),
                'hashieban'
            ) !== false;
        }

        return wp_next_scheduled(
            self::ACTION
        ) !== false;
    }

    private function scheduleNext(int $delay = 5): void
    {
        if ($this->hasPendingAction()) {
            return;
        }

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time() + max(1, $delay),
                self::ACTION,
                array(),
                'hashieban'
            );
            return;
        }

        wp_schedule_single_event(
            time() + max(1, $delay),
            self::ACTION
        );
    }
}
