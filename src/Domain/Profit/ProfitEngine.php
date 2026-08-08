<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

use Hashieban\Domain\Money\Money;
use Hashieban\Integration\WooCommerce\Order\OrderFinancialData;

final class ProfitEngine
{
    public function calculateOrder(
        OrderFinancialData $financial,
        ?Money $globalOrderCosts = null
    ): ProfitResult {
        $revenue =
            $financial
                ->revenueBeforeDirectCosts();

        $globalOrderCosts =
            $globalOrderCosts
        ?? Money::zero(
            $revenue->currency(),
            $revenue->precision()
        );

        $breakdown =
            new ProfitBreakdown(
                $revenue,
                $financial->cogs(),
                $financial->directCosts(),
                $globalOrderCosts,
                Money::zero(
                    $revenue->currency(),
                    $revenue->precision()
                )
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
        Money $globalOrderCosts,
        Money $storeExpenses,
        int $incompleteOrders = 0
    ): ProfitResult {
        $breakdown =
            new ProfitBreakdown(
                $revenue,
                $cogs,
                $orderCosts,
                $globalOrderCosts,
                $storeExpenses
            );

        $completeness =
            $incompleteOrders > 0
        ? Completeness::incomplete(
            array(
                sprintf(
                    '%d سفارش دارای اطلاعات مالی ناقص است.',
                    $incompleteOrders
                ),
            )
        )
            : Completeness::complete();

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

        $revenue =
            $breakdown
                ->revenue()
                ->minorAmount();

        $margin = null;

        if ($revenue !== 0) {
            $margin =
                (
                    $profit->minorAmount()
                    / $revenue
                )
            * 100;
        }

        return new ProfitResult(
            $profit,
            $margin,
            $breakdown,
            $completeness
        );
    }
}
