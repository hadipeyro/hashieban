<?php

declare(strict_types=1);

namespace Hashieban\Domain\Profit;

final class Completeness
{
    public const COMPLETE = 'complete';
    public const INCOMPLETE = 'incomplete';

    private string $status;

    private array $missingData;

    private function __construct(
        string $status,
        array $missingData
    ) {
        $this->status = $status;
        $this->missingData = $missingData;
    }

    public static function complete(): self
    {
        return new self(
            self::COMPLETE,
            array()
        );
    }

    public static function incomplete(
        array $missingData
    ): self {
        $clean = array();

        foreach ($missingData as $item) {
            $item = trim((string) $item);

            if ($item === '') {
                continue;
            }

            $clean[] = $item;
        }

        return new self(
            self::INCOMPLETE,
            array_values(
                array_unique($clean)
            )
        );
    }

    public function status(): string
    {
        return $this->status;
    }

    public function missingData(): array
    {
        return $this->missingData;
    }

    public function isComplete(): bool
    {
        return $this->status
        === self::COMPLETE;
    }

    public function isIncomplete(): bool
    {
        return ! $this->isComplete();
    }
}
