<?php

declare(strict_types=1);

namespace Hashieban\Application\Order;

use Hashieban\Domain\Money\Money;

final class OrderFinancialData
{
    private int $orderId;

    private string $status;

    private string $currency;

    private int $precision;

    private Money $productRevenue;

    private Money $shippingRevenue;

    private Money $positiveFees;

    private Money $refundedRevenue;

    private Money $cogs;

    public function __construct(
        int $orderId,
        string $status,
        string $currency,
        int $precision,
        Money $productRevenue,
        Money $shippingRevenue,
        Money $positiveFees,
        Money $refundedRevenue,
        Money $cogs
    ) {
        $this->orderId = $orderId;
        $this->status = $status;
        $this->currency = $currency;
        $this->precision = $precision;
        $this->productRevenue = $productRevenue;
        $this->shippingRevenue = $shippingRevenue;
        $this->positiveFees = $positiveFees;
        $this->refundedRevenue = $refundedRevenue;
        $this->cogs = $cogs;
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function precision(): int
    {
        return $this->precision;
    }

    public function productRevenue(): Money
    {
        return $this->productRevenue;
    }

    public function shippingRevenue(): Money
    {
        return $this->shippingRevenue;
    }

    public function positiveFees(): Money
    {
        return $this->positiveFees;
    }

    public function refundedRevenue(): Money
    {
        return $this->refundedRevenue;
    }

    public function cogs(): Money
    {
        return $this->cogs;
    }

    public function revenue(): Money
    {
        return $this->productRevenue
					->add($this->shippingRevenue)
					->add($this->positiveFees)
					->subtract($this->refundedRevenue);
    }
}
