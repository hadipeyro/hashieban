<?php

declare(strict_types=1);

namespace Hashieban\Domain\Money;

use InvalidArgumentException;
use LogicException;

final class Money
{
    private int $minorAmount;

    private string $currency;

    private int $precision;

    public function __construct(
        int $minorAmount,
        string $currency,
        int $precision
    ) {
        $currency = strtoupper(trim($currency));

        if ($currency === '') {
            throw new InvalidArgumentException(
                'Currency cannot be empty.'
            );
        }

        if ($precision < 0 || $precision > 6) {
            throw new InvalidArgumentException(
                'Money precision must be between 0 and 6.'
            );
        }

        $this->minorAmount = $minorAmount;
        $this->currency = $currency;
        $this->precision = $precision;
    }

    public static function zero(
        string $currency,
        int $precision
    ): self {
        return new self(
            0,
            $currency,
            $precision
        );
    }

    public function minorAmount(): int
    {
        return $this->minorAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function precision(): int
    {
        return $this->precision;
    }

    public function add(self $money): self
    {
        $this->assertCompatible($money);

        return new self(
            $this->minorAmount + $money->minorAmount,
            $this->currency,
            $this->precision
        );
    }

    public function subtract(self $money): self
    {
        $this->assertCompatible($money);

        return new self(
            $this->minorAmount - $money->minorAmount,
            $this->currency,
            $this->precision
        );
    }

    public function negate(): self
    {
        return new self(
            -$this->minorAmount,
            $this->currency,
            $this->precision
        );
    }

    public function isZero(): bool
    {
        return $this->minorAmount === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorAmount > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorAmount < 0;
    }

    public function equals(self $money): bool
    {
        return $this->minorAmount === $money->minorAmount
            && $this->currency === $money->currency
            && $this->precision === $money->precision;
    }

    public function toDecimalString(): string
    {
        $negative = $this->minorAmount < 0;

        $absoluteAmount = abs($this->minorAmount);

        if ($this->precision === 0) {
            return ($negative ? '-' : '') . (string) $absoluteAmount;
        }

        $multiplier = 10 ** $this->precision;

        $whole = intdiv(
            $absoluteAmount,
            $multiplier
        );

        $fraction = $absoluteAmount % $multiplier;

        return sprintf(
            '%s%d.%0' . $this->precision . 'd',
            $negative ? '-' : '',
            $whole,
            $fraction
        );
    }

    private function assertCompatible(self $money): void
    {
        if ($this->currency !== $money->currency) {
            throw new LogicException(
                'Money values with different currencies cannot be combined.'
            );
        }

        if ($this->precision !== $money->precision) {
            throw new LogicException(
                'Money values with different precision cannot be combined.'
            );
        }
    }
}
