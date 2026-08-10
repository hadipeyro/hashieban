<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

final class LicenseRepository
{
    private const OPTION_LICENSE_KEY = 'hashieban_license_key';
    private const OPTION_PRODUCT_TOKEN = 'hashieban_license_product_token';
    private const OPTION_STATUS = 'hashieban_license_status';

    public function licenseKey(): string
    {
        return trim(
            (string) get_option(
                self::OPTION_LICENSE_KEY,
                ''
            )
        );
    }

    public function productToken(): string
    {
        return trim(
            (string) get_option(
                self::OPTION_PRODUCT_TOKEN,
                ''
            )
        );
    }

    public function saveCredentials(
        string $licenseKey,
        string $productToken = ''
    ): void {
        update_option(
            self::OPTION_LICENSE_KEY,
            trim($licenseKey),
            false
        );

        if (trim($productToken) !== '') {
            update_option(
                self::OPTION_PRODUCT_TOKEN,
                trim($productToken),
                false
            );
        }
    }

    public function status(): LicenseStatus
    {
        $value = get_option(
            self::OPTION_STATUS,
            array()
        );

        if (! is_array($value)) {
            return LicenseStatus::unconfigured();
        }

        return LicenseStatus::fromArray($value);
    }

    public function saveStatus(LicenseStatus $status): void
    {
        update_option(
            self::OPTION_STATUS,
            $status->toArray(),
            false
        );
    }
}
