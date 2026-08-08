<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

use Hashieban\Domain\Money\Money;
use Hashieban\Integration\WooCommerce\Order\OrderFinancialData;

final class ProfitEngine
{
    public function calculateOrder(
        OrderFinancialData $financial
    ): ProfitResult {
        $revenue =
            $financial
                ->revenueBeforeDirectCosts();

        $cogs =
            $financial->cogs();

        $orderCosts =
            $financial->directCosts();

        $storeExpenses =
            Money::zero(
                $revenue->currency(),
                $revenue->precision()
            );

        $breakdown =
            new ProfitBreakdown(
                $revenue,
                $cogs,
                $orderCosts,
                $storeExpenses
            );

        $completeness =
            $financial->hasMissingData()
        ? Completeness::incomplete(
            $financial->missingData()
        )
            : Completeness::complete();

        return $this->createResult(
            $breakdown,
            $completeness
        );
    }

    public function calculateStore(
        Money $revenue,
        Money $cogs,
        Money $orderCosts,
        Money $storeExpenses,
        int $incompleteOrders = 0
    ): ProfitResult {
        $breakdown =
            new ProfitBreakdown(
                $revenue,
                $cogs,
                $orderCosts,
                $storeExpenses
            );

        if ($incompleteOrders > 0) {
            $completeness =
                Completeness::incomplete(
                    array(
                        sprintf(
                            '%d سفارش دارای اطلاعات مالی ناقص است.',
                            $incompleteOrders
                        ),
                    )
                );
        } else {
            $completeness =
                Completeness::complete();
        }

        return $this->createResult(
            $breakdown,
            $completeness
        );
    }

    private function createResult(
        ProfitBreakdown $breakdown,
        Completeness $completeness
    ): ProfitResult {
        $profit =
            $breakdown->netProfit();

        $revenueMinor =
            $breakdown
                ->revenue()
                ->minorAmount();

        $margin = null;

        if ($revenueMinor !== 0) {
            $margin =
                (
                    $profit->minorAmount()
                    / $revenueMinor
                ) * 100;
        }

        return new ProfitResult(
            $profit,
            $margin,
            $breakdown,
            $completeness
        );
    }
}
