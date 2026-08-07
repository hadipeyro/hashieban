<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Order;

use Hashieban\Domain\Money\Money;

final class OrderFinancialData
{
    private int $orderId;

    private string $orderNumber;

    private string $status;

    private string $currency;

    private Money $productRevenue;

    private Money $shippingRevenue;

    private Money $feeRevenue;

    private Money $refundAmount;

    private Money $cogs;

    /**
     * @var string[]
     */
    private array $missingData;

    /**
     * @param string[] $missingData
     */
    public function __construct(
        int $orderId,
        string $orderNumber,
        string $status,
        string $currency,
        Money $productRevenue,
        Money $shippingRevenue,
        Money $feeRevenue,
        Money $refundAmount,
        Money $cogs,
        array $missingData = []
    ) {
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
        $this->status = $status;
        $this->currency = $currency;
        $this->productRevenue = $productRevenue;
        $this->shippingRevenue = $shippingRevenue;
        $this->feeRevenue = $feeRevenue;
        $this->refundAmount = $refundAmount;
        $this->cogs = $cogs;
        $this->missingData = array_values(
            array_unique($missingData)
        );
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function orderNumber(): string
    {
        return $this->orderNumber;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function productRevenue(): Money
    {
        return $this->productRevenue;
    }

    public function shippingRevenue(): Money
    {
        return $this->shippingRevenue;
    }

    public function feeRevenue(): Money
    {
        return $this->feeRevenue;
    }

    public function refundAmount(): Money
    {
        return $this->refundAmount;
    }

    public function cogs(): Money
    {
        return $this->cogs;
    }

    /**
     * @return string[]
     */
    public function missingData(): array
    {
        return $this->missingData;
    }

    public function hasMissingData(): bool
    {
        return $this->missingData !== [];
    }

    public function revenueBeforeDirectCosts(): Money
    {
        return $this->productRevenue
					->add($this->shippingRevenue)
					->add($this->feeRevenue)
					->subtract($this->refundAmount);
    }
}
