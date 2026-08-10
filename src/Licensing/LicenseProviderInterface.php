<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

interface LicenseProviderInterface
{
    public function key(): string;

    public function label(): string;

    public function activate(
        string $licenseKey,
        string $productToken,
        string $domain
    ): LicenseApiResponse;

    public function validate(
        string $licenseKey,
        string $domain
    ): LicenseApiResponse;
}
