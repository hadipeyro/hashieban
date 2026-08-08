<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

use Hashieban\Domain\Money\Money;

final class ProfitBreakdown
{
    private Money $revenue;
    private Money $cogs;
    private Money $orderCosts;
    private Money $globalOrderCosts;
    private Money $storeExpenses;

    public function __construct(
        Money $revenue,
        Money $cogs,
        Money $orderCosts,
        Money $globalOrderCosts,
        Money $storeExpenses
    ) {
        $this->revenue = $revenue;
        $this->cogs = $cogs;
        $this->orderCosts = $orderCosts;
        $this->globalOrderCosts =
            $globalOrderCosts;
        $this->storeExpenses =
            $storeExpenses;

        $this->netProfit();
    }

    public function revenue(): Money
    {
        return $this->revenue;
    }

    public function cogs(): Money
    {
        return $this->cogs;
    }

    public function orderCosts(): Money
    {
        return $this->orderCosts;
    }

    public function globalOrderCosts(): Money
    {
        return $this->globalOrderCosts;
    }

    public function storeExpenses(): Money
    {
        return $this->storeExpenses;
    }

    public function totalExpenses(): Money
    {
        return $this->cogs
					->add($this->orderCosts)
					->add(
						$this->globalOrderCosts
					)
					->add($this->storeExpenses);
    }

    public function profitBeforeStoreExpenses(): Money
    {
        return $this->revenue
					->subtract($this->cogs)
					->subtract($this->orderCosts)
					->subtract(
						$this->globalOrderCosts
					);
    }

    public function netProfit(): Money
    {
        return $this
            ->profitBeforeStoreExpenses()
            ->subtract(
                $this->storeExpenses
            );
    }
}
