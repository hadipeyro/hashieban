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

    private Money $feeDiscounts;

    private Money $refundAmount;

    private Money $refundedTax;

    private Money $taxCharged;

    private Money $orderTotal;

    private Money $cogs;

    private Money $directCosts;

    private array $missingData;

    public function __construct(
        int $orderId,
        string $orderNumber,
        string $status,
        string $currency,
        Money $productRevenue,
        Money $shippingRevenue,
        Money $feeRevenue,
        Money $feeDiscounts,
        Money $refundAmount,
        Money $refundedTax,
        Money $taxCharged,
        Money $orderTotal,
        Money $cogs,
        Money $directCosts,
        array $missingData
    ) {
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
        $this->status = $status;
        $this->currency = $currency;

        $this->productRevenue = $productRevenue;
        $this->shippingRevenue = $shippingRevenue;
        $this->feeRevenue = $feeRevenue;
        $this->feeDiscounts = $feeDiscounts;
        $this->refundAmount = $refundAmount;
        $this->refundedTax = $refundedTax;
        $this->taxCharged = $taxCharged;
        $this->orderTotal = $orderTotal;

        $this->cogs = $cogs;
        $this->directCosts = $directCosts;

        $this->missingData = $missingData;
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

    public function feeDiscounts(): Money
    {
        return $this->feeDiscounts;
    }

    /**
     * Refund amount excluding refunded tax.
     *
     * The profit engine works with tax-exclusive revenue, so refunded tax
     * must not be subtracted from that revenue a second time.
     */
    public function refundAmount(): Money
    {
        return $this->refundAmount;
    }

    public function refundedTax(): Money
    {
        return $this->refundedTax;
    }

    public function taxCharged(): Money
    {
        return $this->taxCharged;
    }

    public function netTax(): Money
    {
        return $this->taxCharged->subtract(
            $this->refundedTax
        );
    }

    /**
     * WooCommerce order total including tax.
     *
     * This is exposed for reconciliation and reporting only. It is not the
     * profit revenue because collected tax is not treated as business income.
     */
    public function orderTotal(): Money
    {
        return $this->orderTotal;
    }

    public function cogs(): Money
    {
        return $this->cogs;
    }

    public function directCosts(): Money
    {
        return $this->directCosts;
    }

    public function missingData(): array
    {
        return $this->missingData;
    }

    public function hasMissingData(): bool
    {
        return $this->missingData !== array();
    }

    public function netFeeRevenue(): Money
    {
        return $this->feeRevenue->subtract(
            $this->feeDiscounts
        );
    }

    public function revenueBeforeDirectCosts(): Money
    {
        return $this->productRevenue
            ->add($this->shippingRevenue)
            ->add($this->feeRevenue)
            ->subtract($this->feeDiscounts)
            ->subtract($this->refundAmount);
    }

    public function profitAfterDirectCosts(): Money
    {
        return $this->revenueBeforeDirectCosts()
            ->subtract($this->cogs)
            ->subtract($this->directCosts);
    }
}
