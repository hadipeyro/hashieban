<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

/**
 * Neutral default provider used by the public/core package.
 *
 * Market-specific distributors can inject their own provider through the
 * hashieban_license_provider filter without changing the customer-facing UI.
 */
final class NullLicenseProvider implements LicenseProviderInterface
{
    public function key(): string
    {
        return 'none';
    }

    public function label(): string
    {
        return 'فعال‌سازی غیرفعال';
    }

    public function activate(
        string $licenseKey,
        string $productToken,
        string $domain
    ): LicenseApiResponse {
        return LicenseApiResponse::transportFailure(
            'سامانه فعال‌سازی برای این بسته تنظیم نشده است.'
        );
    }

    public function validate(
        string $licenseKey,
        string $domain
    ): LicenseApiResponse {
        return LicenseApiResponse::transportFailure(
            'سامانه فعال‌سازی برای این بسته تنظیم نشده است.'
        );
    }
}
