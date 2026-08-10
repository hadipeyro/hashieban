<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

final class LicenseApiResponse
{
    private bool $successful;
    private bool $transportSuccessful;
    private string $message;

    public function __construct(
        bool $successful,
        bool $transportSuccessful,
        string $message
    ) {
        $this->successful = $successful;
        $this->transportSuccessful = $transportSuccessful;
        $this->message = trim($message);
    }

    public static function success(string $message = ''): self
    {
        return new self(true, true, $message);
    }

    public static function rejected(string $message): self
    {
        return new self(false, true, $message);
    }

    public static function transportFailure(string $message): self
    {
        return new self(false, false, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function transportWasSuccessful(): bool
    {
        return $this->transportSuccessful;
    }

    public function message(): string
    {
        return $this->message;
    }
}
