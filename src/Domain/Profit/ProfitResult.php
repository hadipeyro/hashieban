<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

use Hashieban\Domain\Money\Money;

final class ProfitResult
{
    private Money $profit;

    private ?float $marginPercentage;

    private ProfitBreakdown $breakdown;

    private Completeness $completeness;

    public function __construct(
        Money $profit,
        ?float $marginPercentage,
        ProfitBreakdown $breakdown,
        Completeness $completeness
    ) {
        $this->profit = $profit;
        $this->marginPercentage = $marginPercentage;
        $this->breakdown = $breakdown;
        $this->completeness = $completeness;
    }

    public function profit(): Money
    {
        return $this->profit;
    }

    public function marginPercentage(): ?float
    {
        return $this->marginPercentage;
    }

    public function breakdown(): ProfitBreakdown
    {
        return $this->breakdown;
    }

    public function completeness(): Completeness
    {
        return $this->completeness;
    }

    public function isProfit(): bool
    {
        return $this->profit->isPositive();
    }

    public function isLoss(): bool
    {
        return $this->profit->isNegative();
    }

    public function isBreakEven(): bool
    {
        return $this->profit->isZero();
    }
}
