<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Attribution;

final class SalesChannelClassifier
{
    public function classify(array $data): array
    {
        $sourceType = $this->normalize((string) ($data['source_type'] ?? ''));
        $source = $this->normalize((string) ($data['source'] ?? ''));
        $medium = $this->normalize((string) ($data['medium'] ?? ''));
        $campaign = trim((string) ($data['campaign'] ?? ''));
        $referrerDomain = $this->domainFromReferrer(
            (string) ($data['referrer'] ?? '')
        );

        $haystack = trim(
            $source . ' '
            . $medium . ' '
            . $this->normalize($referrerDomain)
        );

        $known = array(
            array('torob', 'ترب', 'comparison', array('torob')),
            array('emalls', 'ایمالز', 'comparison', array('emalls')),
            array('instagram', 'اینستاگرام', 'social', array('instagram')),
            array('telegram', 'تلگرام', 'social', array('telegram', 't.me')),
            array('whatsapp', 'واتساپ', 'social', array('whatsapp', 'wa.me')),
            array('google', 'گوگل', 'search', array('google')),
            array('bing', 'بینگ', 'search', array('bing')),
            array('yahoo', 'یاهو', 'search', array('yahoo')),
        );

        foreach ($known as $rule) {
            if ($this->containsAny($haystack, (array) $rule[3])) {
                return $this->result(
                    (string) $rule[0],
                    (string) $rule[1],
                    (string) $rule[2],
                    $sourceType,
                    $source,
                    $medium,
                    $campaign,
                    $referrerDomain,
                    true
                );
            }
        }

        if (
            $medium === 'email'
            || $this->containsAny($source, array('newsletter', 'email'))
        ) {
            return $this->result(
                'email',
                'ایمیل و خبرنامه',
                'email',
                $sourceType,
                $source,
                $medium,
                $campaign,
                $referrerDomain,
                true
            );
        }

        if (
            in_array($sourceType, array('typein', 'direct'), true)
            || in_array($source, array('direct', '(direct)'), true)
        ) {
            return $this->result(
                'direct',
                'ورود مستقیم',
                'direct',
                $sourceType,
                $source,
                $medium,
                $campaign,
                $referrerDomain,
                true
            );
        }

        if (
            in_array(
                $sourceType,
                array('admin', 'web_admin', 'mobile_app'),
                true
            )
        ) {
            return $this->result(
                'manual',
                $sourceType === 'mobile_app'
                    ? 'اپلیکیشن مدیریت فروشگاه'
                    : 'ثبت دستی در مدیریت',
                'manual',
                $sourceType,
                $source,
                $medium,
                $campaign,
                $referrerDomain,
                true
            );
        }

        if ($source !== '') {
            return $this->result(
                'source_' . $this->keyPart($source),
                $this->displayLabel($source),
                $sourceType === 'organic'
                    ? 'search'
                    : ($sourceType === 'referral' ? 'referral' : 'campaign'),
                $sourceType,
                $source,
                $medium,
                $campaign,
                $referrerDomain,
                true
            );
        }

        if ($referrerDomain !== '') {
            return $this->result(
                'ref_' . $this->keyPart($referrerDomain),
                $referrerDomain,
                $sourceType === 'organic' ? 'search' : 'referral',
                $sourceType,
                $source,
                $medium,
                $campaign,
                $referrerDomain,
                true
            );
        }

        if (
            $sourceType !== ''
            && $sourceType !== 'unknown'
        ) {
            return $this->result(
                'type_' . $this->keyPart($sourceType),
                $this->sourceTypeLabel($sourceType),
                'other',
                $sourceType,
                $source,
                $medium,
                $campaign,
                $referrerDomain,
                true
            );
        }

        return $this->result(
            'unknown',
            'بدون داده منبع',
            'unknown',
            $sourceType,
            $source,
            $medium,
            $campaign,
            $referrerDomain,
            false
        );
    }

    private function result(
        string $key,
        string $label,
        string $group,
        string $sourceType,
        string $source,
        string $medium,
        string $campaign,
        string $referrerDomain,
        bool $known
    ): array {
        return array(
            'channel_key' => $key,
            'channel_name' => $label,
            'channel_group' => $group,
            'source_type' => $sourceType,
            'source' => $source,
            'medium' => $medium,
            'campaign' => trim($campaign),
            'referrer_domain' => $referrerDomain,
            'known' => $known,
        );
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = $this->normalize((string) $needle);

            if (
                $needle !== ''
                && strpos($haystack, $needle) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function domainFromReferrer(string $referrer): string
    {
        $referrer = trim($referrer);

        if ($referrer === '') {
            return '';
        }

        $candidate = preg_match(
            '~^[a-z][a-z0-9+.-]*://~i',
            $referrer
        ) === 1
            ? $referrer
            : 'https://' . ltrim($referrer, '/');

        $host = parse_url($candidate, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return '';
        }

        $host = strtolower(trim($host, '.'));

        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host;
    }

    private function keyPart(string $value): string
    {
        $normalized = $this->normalize($value);
        $slug = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $normalized
        );

        $slug = is_string($slug)
            ? trim($slug, '_')
            : '';

        if ($slug === '') {
            return substr(sha1($normalized), 0, 12);
        }

        return substr($slug, 0, 70);
    }

    private function displayLabel(string $source): string
    {
        $source = trim($source);

        if ($source === '') {
            return 'سایر منابع';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($source, 0, 80);
        }

        return substr($source, 0, 80);
    }

    private function sourceTypeLabel(string $sourceType): string
    {
        $labels = array(
            'organic' => 'جست‌وجوی طبیعی',
            'referral' => 'ارجاع از سایت دیگر',
            'utm' => 'کمپین دارای کد رهگیری',
        );

        return $labels[$sourceType] ?? 'سایر منابع';
    }
}
