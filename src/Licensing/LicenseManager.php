<?php

declare(strict_types=1);

namespace Hashieban\Licensing;

use Hashieban\Security\Capabilities;

final class LicenseManager
{
    public const DAILY_CHECK_HOOK = 'hashieban_license_daily_check';
    private const CHECK_TTL = 86400;
    private const GRACE_SECONDS = 604800;

    private LicenseRepository $repository;
    private LicenseProviderInterface $provider;

    public function __construct(
        LicenseRepository $repository,
        LicenseProviderInterface $provider
    ) {
        $this->repository = $repository;
        $this->provider = $provider;
    }

    public function register(): void
    {
        add_action(
            'admin_init',
            array(
                $this,
                'ensureSchedule'
            )
        );

        add_action(
            self::DAILY_CHECK_HOOK,
            array(
                $this,
                'scheduledValidation'
            )
        );

        add_action(
            'admin_notices',
            array(
                $this,
                'renderAdminNotice'
            )
        );

        if (defined('HASHIEBAN_FILE')) {
            add_filter(
                'plugin_action_links_' . plugin_basename(
                    (string) HASHIEBAN_FILE
                ),
                array(
                    $this,
                    'pluginActionLinks'
                )
            );
        }
    }

    public function ensureSchedule(): void
    {
        if (wp_next_scheduled(self::DAILY_CHECK_HOOK)) {
            return;
        }

        wp_schedule_event(
            time() + 300,
            'daily',
            self::DAILY_CHECK_HOOK
        );
    }

    public function scheduledValidation(): void
    {
        if (! $this->hasLicenseKey()) {
            return;
        }

        $this->validateNow();
    }

    public function activate(
        string $licenseKey,
        string $productToken = ''
    ): LicenseStatus {
        $licenseKey = $this->sanitizeToken(
            $licenseKey
        );
        $productToken = $this->sanitizeToken(
            $productToken
        );

        if ($licenseKey === '') {
            return $this->storeStatus(
                new LicenseStatus(
                    LicenseStatus::INVALID,
                    'کد مجوز را وارد کنید.',
                    $this->provider->key(),
                    $this->domain(),
                    time(),
                    $this->repository->status()->lastSuccessfulAt()
                )
            );
        }

        if ($productToken !== '') {
            $this->repository->saveCredentials(
                $licenseKey,
                $productToken
            );
        } else {
            $this->repository->saveCredentials(
                $licenseKey
            );
        }

        if ($this->isDevelopmentEnvironment()) {
            return $this->developmentStatus();
        }

        $resolvedProductToken = $this->productToken();

        if ($resolvedProductToken === '') {
            return $this->storeStatus(
                new LicenseStatus(
                    LicenseStatus::ERROR,
                    'توکن محصول فروشگاه نرم‌افزاری هنوز برای نسخه نهایی تنظیم نشده است.',
                    $this->provider->key(),
                    $this->domain(),
                    time(),
                    $this->repository->status()->lastSuccessfulAt()
                )
            );
        }

        $response = $this->provider->activate(
            $licenseKey,
            $resolvedProductToken,
            $this->domain()
        );

        return $this->statusFromResponse(
            $response,
            true
        );
    }

    public function validateNow(): LicenseStatus
    {
        if ($this->isDevelopmentEnvironment()) {
            return $this->developmentStatus();
        }

        $licenseKey = $this->repository->licenseKey();

        if ($licenseKey === '') {
            return $this->storeStatus(
                LicenseStatus::unconfigured()
            );
        }

        $response = $this->provider->validate(
            $licenseKey,
            $this->domain()
        );

        return $this->statusFromResponse(
            $response,
            false
        );
    }

    public function status(): LicenseStatus
    {
        if ($this->isDevelopmentEnvironment()) {
            return $this->developmentStatus(false);
        }

        $status = $this->repository->status();

        if (
            $this->hasLicenseKey()
            && $status->isStale(
                time(),
                self::CHECK_TTL
            )
        ) {
            return $status;
        }

        return $status;
    }

    public function hasLicenseKey(): bool
    {
        return $this->repository->licenseKey() !== '';
    }

    public function maskedLicenseKey(): string
    {
        $key = $this->repository->licenseKey();

        if ($key === '') {
            return '';
        }

        $length = strlen($key);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return substr($key, 0, 4)
            . str_repeat('•', max(4, $length - 8))
            . substr($key, -4);
    }

    public function productToken(): string
    {
        $token = '';

        if (
            defined('HASHIEBAN_ZHAKET_PRODUCT_TOKEN')
            && trim((string) HASHIEBAN_ZHAKET_PRODUCT_TOKEN) !== ''
        ) {
            $token = trim(
                (string) HASHIEBAN_ZHAKET_PRODUCT_TOKEN
            );
        }

        if ($token === '') {
            $token = $this->repository->productToken();
        }

        return (string) apply_filters(
            'hashieban_license_product_token',
            $token,
            $this->provider->key()
        );
    }

    public function marketplaceIsConfigured(): bool
    {
        return $this->productToken() !== '';
    }

    public function isDevelopmentEnvironment(): bool
    {
        if (function_exists('wp_get_environment_type')) {
            $type = (string) wp_get_environment_type();

            if (in_array($type, array('local', 'development'), true)) {
                return true;
            }
        }

        $host = strtolower($this->domain());

        if (
            $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || substr($host, -5) === '.test'
            || substr($host, -6) === '.local'
        ) {
            return true;
        }

        return false;
    }

    public function providerLabel(): string
    {
        return $this->provider->label();
    }

    public function pluginActionLinks(array $links): array
    {
        if (! Capabilities::can(Capabilities::MANAGE_SETTINGS)) {
            return $links;
        }

        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    admin_url(
                        'admin.php?page=hashieban-settings#hb-license'
                    )
                ),
                esc_html('مجوز و بروزرسانی')
            )
        );

        return $links;
    }

    public function renderAdminNotice(): void
    {
        if (
            ! Capabilities::can(Capabilities::MANAGE_SETTINGS)
            || $this->isDevelopmentEnvironment()
            || ! $this->marketplaceIsConfigured()
        ) {
            return;
        }

        $page = isset($_GET['page'])
            ? sanitize_key(
                wp_unslash(
                    (string) $_GET['page']
                )
            )
            : '';

        if (strpos($page, 'hashieban') !== 0) {
            return;
        }

        $status = $this->repository->status();

        if (
            $status->state() === LicenseStatus::ACTIVE
            || $status->state() === LicenseStatus::DEVELOPMENT
        ) {
            return;
        }

        $class = 'notice notice-warning';
        $message = 'برای دریافت بروزرسانی‌ها و پشتیبانی حاشیه‌بان، کد مجوز را فعال کنید.';

        if ($status->state() === LicenseStatus::INVALID) {
            $class = 'notice notice-error';
            $message = $status->message() !== ''
                ? $status->message()
                : 'کد مجوز حاشیه‌بان معتبر نیست.';
        } elseif ($status->state() === LicenseStatus::GRACE) {
            $message = 'ارتباط با سرویس مجوز موقتاً قطع است؛ حاشیه‌بان در دوره اطمینان همچنان فعال می‌ماند.';
        }

        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <p>
                <?php echo esc_html($message); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hashieban-settings#hb-license')); ?>">
                    مدیریت مجوز
                </a>
            </p>
        </div>
        <?php
    }

    private function statusFromResponse(
        LicenseApiResponse $response,
        bool $activation
    ): LicenseStatus {
        $now = time();
        $previous = $this->repository->status();

        if ($response->isSuccessful()) {
            return $this->storeStatus(
                new LicenseStatus(
                    LicenseStatus::ACTIVE,
                    $response->message() !== ''
                        ? $response->message()
                        : ($activation
                            ? 'مجوز با موفقیت فعال شد.'
                            : 'مجوز معتبر است.'),
                    $this->provider->key(),
                    $this->domain(),
                    $now,
                    $now
                )
            );
        }

        if (
            ! $response->transportWasSuccessful()
            && $previous->isInsideGracePeriod(
                $now,
                self::GRACE_SECONDS
            )
        ) {
            return $this->storeStatus(
                new LicenseStatus(
                    LicenseStatus::GRACE,
                    'سرویس مجوز موقتاً در دسترس نیست؛ وضعیت معتبر قبلی تا ۷ روز حفظ می‌شود.',
                    $this->provider->key(),
                    $this->domain(),
                    $now,
                    $previous->lastSuccessfulAt()
                )
            );
        }

        $state = $response->transportWasSuccessful()
            ? LicenseStatus::INVALID
            : LicenseStatus::ERROR;

        return $this->storeStatus(
            new LicenseStatus(
                $state,
                $response->message(),
                $this->provider->key(),
                $this->domain(),
                $now,
                $previous->lastSuccessfulAt()
            )
        );
    }

    private function developmentStatus(
        bool $persist = true
    ): LicenseStatus {
        $status = new LicenseStatus(
            LicenseStatus::DEVELOPMENT,
            'محیط توسعه شناسایی شد؛ برای localhost نیازی به فعال‌سازی مجوز نیست.',
            $this->provider->key(),
            $this->domain(),
            time(),
            time()
        );

        return $persist
            ? $this->storeStatus($status)
            : $status;
    }

    private function storeStatus(
        LicenseStatus $status
    ): LicenseStatus {
        $this->repository->saveStatus(
            $status
        );

        do_action(
            'hashieban_license_status_changed',
            $status
        );

        return $status;
    }

    private function domain(): string
    {
        $host = wp_parse_url(
            home_url('/'),
            PHP_URL_HOST
        );

        if (! is_string($host) || $host === '') {
            return 'unknown';
        }

        return preg_replace(
            '/^www\./i',
            '',
            strtolower($host)
        ) ?: strtolower($host);
    }

    private function sanitizeToken(string $value): string
    {
        $value = sanitize_text_field(
            wp_unslash($value)
        );

        return trim($value);
    }
}
