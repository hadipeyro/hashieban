<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

final class ZhaketLicenseClient implements LicenseProviderInterface
{
    private const PRIMARY_API = 'https://guard.zhaket.com/api/';
    private const FALLBACK_API = 'https://guard.zhaket.org/api/';

    public function key(): string
    {
        return 'zhaket';
    }

    public function label(): string
    {
        return 'ژاکت';
    }

    public function activate(
        string $licenseKey,
        string $productToken,
        string $domain
    ): LicenseApiResponse {
        return $this->request(
            'install-license',
            array(
                'product_token' => $productToken,
                'token' => $licenseKey,
                'domain' => $domain,
            )
        );
    }

    public function validate(
        string $licenseKey,
        string $domain
    ): LicenseApiResponse {
        return $this->request(
            'validation-license',
            array(
                'token' => $licenseKey,
                'domain' => $domain,
            )
        );
    }

    private function request(
        string $method,
        array $parameters
    ): LicenseApiResponse {
        $primary = $this->requestServer(
            self::PRIMARY_API,
            $method,
            $parameters
        );

        if ($primary->transportWasSuccessful()) {
            return $primary;
        }

        return $this->requestServer(
            self::FALLBACK_API,
            $method,
            $parameters
        );
    }

    private function requestServer(
        string $baseUrl,
        string $method,
        array $parameters
    ): LicenseApiResponse {
        $url = add_query_arg(
            $parameters,
            $baseUrl . ltrim($method, '/')
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 12,
                'redirection' => 2,
                'sslverify' => true,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => sprintf(
                        'Hashieban/%s; %s',
                        defined('HASHIEBAN_VERSION')
                            ? (string) HASHIEBAN_VERSION
                            : 'dev',
                        home_url('/')
                    ),
                ),
            )
        );

        if (is_wp_error($response)) {
            return LicenseApiResponse::transportFailure(
                'ارتباط با سرویس مجوز برقرار نشد.'
            );
        }

        $httpCode = (int) wp_remote_retrieve_response_code(
            $response
        );

        if ($httpCode < 200 || $httpCode >= 500) {
            return LicenseApiResponse::transportFailure(
                'سرویس مجوز موقتاً پاسخ مناسبی نداد.'
            );
        }

        $body = (string) wp_remote_retrieve_body(
            $response
        );

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return LicenseApiResponse::transportFailure(
                'پاسخ سرویس مجوز قابل خواندن نبود.'
            );
        }

        $message = $this->normalizeMessage(
            $decoded['message'] ?? ''
        );

        if (
            isset($decoded['status'])
            && (string) $decoded['status'] === 'successful'
        ) {
            return LicenseApiResponse::success(
                $message !== ''
                    ? $message
                    : 'مجوز با موفقیت تأیید شد.'
            );
        }

        return LicenseApiResponse::rejected(
            $message !== ''
                ? $message
                : 'کد مجوز معتبر نیست.'
        );
    }

    private function normalizeMessage($message): string
    {
        $messages = array();
        $this->collectMessages(
            $message,
            $messages
        );

        $messages = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function (string $value): string {
                            return trim(
                                wp_strip_all_tags($value)
                            );
                        },
                        $messages
                    )
                )
            )
        );

        return implode(' ', $messages);
    }

    private function collectMessages(
        $value,
        array &$messages
    ): void {
        if (is_string($value) || is_numeric($value)) {
            $messages[] = (string) $value;
            return;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->collectMessages(
                $item,
                $messages
            );
        }
    }
}
