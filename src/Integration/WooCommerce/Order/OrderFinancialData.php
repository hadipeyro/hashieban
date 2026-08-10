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
    private Money $originalCogs;
    private Money $recoveredCogs;
    private Money $refundedCogs;
    private Money $unrecoveredRefundedCogs;
    private Money $unallocatedRefund;
    private Money $directCosts;
    private int $refundCount;
    private int $refundedQuantity;
    private int $restockedQuantity;
    private array $refundEvents;
    private array $refundItems;
    private array $refundWarnings;
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
        Money $originalCogs,
        Money $recoveredCogs,
        Money $refundedCogs,
        Money $unrecoveredRefundedCogs,
        Money $unallocatedRefund,
        Money $directCosts,
        int $refundCount,
        int $refundedQuantity,
        int $restockedQuantity,
        array $refundEvents,
        array $refundItems,
        array $refundWarnings,
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
        $this->originalCogs = $originalCogs;
        $this->recoveredCogs = $recoveredCogs;
        $this->refundedCogs = $refundedCogs;
        $this->unrecoveredRefundedCogs = $unrecoveredRefundedCogs;
        $this->unallocatedRefund = $unallocatedRefund;
        $this->directCosts = $directCosts;
        $this->refundCount = $refundCount;
        $this->refundedQuantity = $refundedQuantity;
        $this->restockedQuantity = $restockedQuantity;
        $this->refundEvents = $refundEvents;
        $this->refundItems = $refundItems;
        $this->refundWarnings = $refundWarnings;
        $this->missingData = $missingData;
    }

    public function orderId(): int { return $this->orderId; }
    public function orderNumber(): string { return $this->orderNumber; }
    public function status(): string { return $this->status; }
    public function currency(): string { return $this->currency; }
    public function productRevenue(): Money { return $this->productRevenue; }
    public function shippingRevenue(): Money { return $this->shippingRevenue; }
    public function feeRevenue(): Money { return $this->feeRevenue; }
    public function feeDiscounts(): Money { return $this->feeDiscounts; }

    /** Refund amount excluding refunded tax. */
    public function refundAmount(): Money { return $this->refundAmount; }
    public function refundedTax(): Money { return $this->refundedTax; }
    public function taxCharged(): Money { return $this->taxCharged; }

    public function netTax(): Money
    {
        return $this->taxCharged->subtract($this->refundedTax);
    }

    /** WooCommerce order total including tax, exposed for reconciliation. */
    public function orderTotal(): Money { return $this->orderTotal; }

    /** COGS remaining after returned/restocked inventory has been recovered. */
    public function cogs(): Money { return $this->cogs; }
    public function originalCogs(): Money { return $this->originalCogs; }
    public function recoveredCogs(): Money { return $this->recoveredCogs; }
    public function refundedCogs(): Money { return $this->refundedCogs; }
    public function unrecoveredRefundedCogs(): Money { return $this->unrecoveredRefundedCogs; }
    public function unallocatedRefund(): Money { return $this->unallocatedRefund; }
    public function directCosts(): Money { return $this->directCosts; }
    public function refundCount(): int { return $this->refundCount; }
    public function refundedQuantity(): int { return $this->refundedQuantity; }
    public function restockedQuantity(): int { return $this->restockedQuantity; }
    public function refundEvents(): array { return $this->refundEvents; }
    public function refundItems(): array { return $this->refundItems; }
    public function refundWarnings(): array { return $this->refundWarnings; }
    public function hasRefund(): bool { return $this->refundCount > 0 || $this->refundAmount->isPositive(); }
    public function hasUnallocatedRefund(): bool { return $this->unallocatedRefund->isPositive(); }
    public function missingData(): array { return $this->missingData; }
    public function hasMissingData(): bool { return $this->missingData !== array(); }

    public function netFeeRevenue(): Money
    {
        return $this->feeRevenue->subtract($this->feeDiscounts);
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
