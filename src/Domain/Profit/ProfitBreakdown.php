<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

use Hashieban\Domain\Money\Money;

final class ProfitBreakdown
{
    private Money $revenue;

    private Money $cogs;

    private Money $shippingCost;

    private Money $packagingCost;

    private Money $paymentCost;

    private Money $additionalCost;

    public function __construct(
        Money $revenue,
        Money $cogs,
        Money $shippingCost,
        Money $packagingCost,
        Money $paymentCost,
        Money $additionalCost
    ) {
        $this->revenue = $revenue;
        $this->cogs = $cogs;
        $this->shippingCost = $shippingCost;
        $this->packagingCost = $packagingCost;
        $this->paymentCost = $paymentCost;
        $this->additionalCost = $additionalCost;
    }

    public function revenue(): Money
    {
        return $this->revenue;
    }

    public function cogs(): Money
    {
        return $this->cogs;
    }

    public function shippingCost(): Money
    {
        return $this->shippingCost;
    }

    public function packagingCost(): Money
    {
        return $this->packagingCost;
    }

    public function paymentCost(): Money
    {
        return $this->paymentCost;
    }

    public function additionalCost(): Money
    {
        return $this->additionalCost;
    }

    public function totalDirectCosts(): Money
    {
        return $this->cogs
					->add($this->shippingCost)
					->add($this->packagingCost)
					->add($this->paymentCost)
					->add($this->additionalCost);
    }
}
