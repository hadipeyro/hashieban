<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

final class Completeness
{
    public const COMPLETE = 'complete';
    public const INCOMPLETE = 'incomplete';

    private string $status;

    /**
     * @var string[]
     */
    private array $missingData;

    /**
     * @param string[] $missingData
     */
    private function __construct(
        string $status,
        array $missingData = []
    ) {
        $this->status = $status;
        $this->missingData = $missingData;
    }

    public static function complete(): self
    {
        return new self(
            self::COMPLETE
        );
    }

    /**
     * @param string[] $missingData
     */
    public static function incomplete(
        array $missingData
    ): self {
        return new self(
            self::INCOMPLETE,
            array_values(
                array_unique($missingData)
            )
        );
    }

    public function isComplete(): bool
    {
        return $this->status === self::COMPLETE;
    }

    public function isIncomplete(): bool
    {
        return ! $this->isComplete();
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return string[]
     */
    public function missingData(): array
    {
        return $this->missingData;
    }
}
