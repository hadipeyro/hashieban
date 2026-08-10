<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

final class LicenseStatus
{
    public const UNCONFIGURED = 'unconfigured';
    public const ACTIVE = 'active';
    public const INVALID = 'invalid';
    public const GRACE = 'grace';
    public const DEVELOPMENT = 'development';
    public const ERROR = 'error';

    private string $state;
    private string $message;
    private string $provider;
    private string $domain;
    private int $checkedAt;
    private int $lastSuccessfulAt;

    public function __construct(
        string $state,
        string $message = '',
        string $provider = '',
        string $domain = '',
        int $checkedAt = 0,
        int $lastSuccessfulAt = 0
    ) {
        $allowed = array(
            self::UNCONFIGURED,
            self::ACTIVE,
            self::INVALID,
            self::GRACE,
            self::DEVELOPMENT,
            self::ERROR,
        );

        $this->state = in_array($state, $allowed, true)
            ? $state
            : self::ERROR;
        $this->message = trim($message);
        $this->provider = trim($provider);
        $this->domain = trim($domain);
        $this->checkedAt = max(0, $checkedAt);
        $this->lastSuccessfulAt = max(0, $lastSuccessfulAt);
    }

    public static function unconfigured(): self
    {
        return new self(self::UNCONFIGURED);
    }

    public function state(): string
    {
        return $this->state;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function checkedAt(): int
    {
        return $this->checkedAt;
    }

    public function lastSuccessfulAt(): int
    {
        return $this->lastSuccessfulAt;
    }

    public function isUsable(): bool
    {
        return in_array(
            $this->state,
            array(
                self::ACTIVE,
                self::GRACE,
                self::DEVELOPMENT,
            ),
            true
        );
    }

    public function isActive(): bool
    {
        return $this->state === self::ACTIVE;
    }

    public function isDevelopment(): bool
    {
        return $this->state === self::DEVELOPMENT;
    }

    public function isStale(int $now, int $ttlSeconds): bool
    {
        if ($this->checkedAt <= 0) {
            return true;
        }

        return ($now - $this->checkedAt) >= max(1, $ttlSeconds);
    }

    public function isInsideGracePeriod(
        int $now,
        int $graceSeconds
    ): bool {
        if ($this->lastSuccessfulAt <= 0) {
            return false;
        }

        return ($now - $this->lastSuccessfulAt)
            <= max(1, $graceSeconds);
    }

    public function toArray(): array
    {
        return array(
            'state' => $this->state,
            'message' => $this->message,
            'provider' => $this->provider,
            'domain' => $this->domain,
            'checked_at' => $this->checkedAt,
            'last_successful_at' => $this->lastSuccessfulAt,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['state'] ?? self::UNCONFIGURED),
            (string) ($data['message'] ?? ''),
            (string) ($data['provider'] ?? ''),
            (string) ($data['domain'] ?? ''),
            (int) ($data['checked_at'] ?? 0),
            (int) ($data['last_successful_at'] ?? 0)
        );
    }
}
