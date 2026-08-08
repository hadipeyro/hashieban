<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

use Hashieban\Domain\Money\Money;

final class ProfitBreakdown
{
    private Money $revenue;

    private Money $cogs;

    private Money $orderCosts;

    private Money $storeExpenses;

    public function __construct(
        Money $revenue,
        Money $cogs,
        Money $orderCosts,
        Money $storeExpenses
    ) {
        $this->revenue = $revenue;
        $this->cogs = $cogs;
        $this->orderCosts = $orderCosts;
        $this->storeExpenses = $storeExpenses;

        /*
         * Trigger Money compatibility validation
         * immediately.
         */
        $this->revenue
             ->subtract($this->cogs)
             ->subtract($this->orderCosts)
             ->subtract($this->storeExpenses);
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

    public function storeExpenses(): Money
    {
        return $this->storeExpenses;
    }

    public function totalExpenses(): Money
    {
        return $this->cogs
					->add($this->orderCosts)
					->add($this->storeExpenses);
    }

    public function profitBeforeStoreExpenses(): Money
    {
        return $this->revenue
					->subtract($this->cogs)
					->subtract($this->orderCosts);
    }

    public function netProfit(): Money
    {
        return $this->revenue
					->subtract($this->cogs)
					->subtract($this->orderCosts)
					->subtract($this->storeExpenses);
    }
}
