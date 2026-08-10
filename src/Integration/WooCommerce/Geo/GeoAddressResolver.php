<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Geo;

use WC_Order;

final class GeoAddressResolver
{
    public const COUNTRY_IRAN = 'IR';

    public function resolve(WC_Order $order): array
    {
        $shipping = $this->addressFromOrder($order, 'shipping');
        $billing = $this->addressFromOrder($order, 'billing');

        if ($this->isCompleteIranAddress($shipping)) {
            return $this->finalize($shipping, 'shipping');
        }

        if ($this->isCompleteIranAddress($billing)) {
            return $this->finalize($billing, 'billing');
        }

        if ($this->isIranAddress($shipping) && $this->hasGeoValue($shipping)) {
            return $this->finalize($shipping, 'shipping');
        }

        if ($this->isIranAddress($billing)) {
            return $this->finalize($billing, 'billing');
        }

        if ($this->hasGeoValue($shipping)) {
            return $this->finalize($shipping, 'shipping');
        }

        return $this->finalize($billing, 'billing');
    }

    public function missingFields(WC_Order $order): array
    {
        $resolved = $this->resolve($order);

        if (($resolved['country'] ?? '') !== self::COUNTRY_IRAN) {
            return array();
        }

        $missing = array();

        if (($resolved['state_code'] ?? '') === '') {
            $missing[] = 'استان';
        }

        if (($resolved['city_key'] ?? '') === '') {
            $missing[] = 'شهر';
        }

        return $missing;
    }

    public function isIranOrder(WC_Order $order): bool
    {
        $resolved = $this->resolve($order);

        return ($resolved['country'] ?? '') === self::COUNTRY_IRAN;
    }

    private function addressFromOrder(WC_Order $order, string $type): array
    {
        if ($type === 'shipping') {
            return array(
                'country' => $this->normalizeCountry((string) $order->get_shipping_country()),
                'state_raw' => (string) $order->get_shipping_state(),
                'city_raw' => (string) $order->get_shipping_city(),
            );
        }

        return array(
            'country' => $this->normalizeCountry((string) $order->get_billing_country()),
            'state_raw' => (string) $order->get_billing_state(),
            'city_raw' => (string) $order->get_billing_city(),
        );
    }

    private function finalize(array $address, string $source): array
    {
        $country = $this->normalizeCountry((string) ($address['country'] ?? ''));
        $state = $this->normalizeState(
            $country,
            (string) ($address['state_raw'] ?? '')
        );
        $city = $this->normalizeCity((string) ($address['city_raw'] ?? ''));

        return array(
            'source' => $source,
            'country' => $country,
            'state_code' => $state['code'],
            'state_name' => $state['name'],
            'city_name' => $city['name'],
            'city_key' => $city['key'],
            'complete' => $country === self::COUNTRY_IRAN
                ? ($state['code'] !== '' && $city['key'] !== '')
                : ($country !== '' && $city['key'] !== ''),
        );
    }

    private function normalizeCountry(string $country): string
    {
        return strtoupper(trim($country));
    }

    private function normalizeState(string $country, string $state): array
    {
        $state = $this->normalizePersianText($state);

        if ($state === '') {
            return array('code' => '', 'name' => '');
        }

        if ($country !== self::COUNTRY_IRAN || ! function_exists('WC')) {
            return array(
                'code' => strtoupper($state),
                'name' => $state,
            );
        }

        $states = WC()->countries
            ? WC()->countries->get_states(self::COUNTRY_IRAN)
            : array();

        $codeCandidate = strtoupper($state);
        if (strpos($codeCandidate, 'IR-') === 0) {
            $codeCandidate = substr($codeCandidate, 3);
        }

        if (isset($states[$codeCandidate])) {
            return array(
                'code' => (string) $codeCandidate,
                'name' => $this->normalizePersianText((string) $states[$codeCandidate]),
            );
        }

        foreach ((array) $states as $code => $name) {
            if ($this->normalizePersianText((string) $name) === $state) {
                return array(
                    'code' => (string) $code,
                    'name' => $state,
                );
            }
        }

        return array(
            'code' => $this->slugKey($state),
            'name' => $state,
        );
    }

    private function normalizeCity(string $city): array
    {
        $city = $this->normalizePersianText($city);

        if ($city === '') {
            return array('name' => '', 'key' => '');
        }

        $name = $city;
        $key = $city;

        foreach (array('شهرستان ', 'شهر ') as $prefix) {
            if (strpos($key, $prefix) === 0) {
                $key = trim(substr($key, strlen($prefix)));
                break;
            }
        }

        $key = $this->slugKey($key);

        return array(
            'name' => $name,
            'key' => $key,
        );
    }

    private function normalizePersianText(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = strtr(
            $value,
            array(
                'ي' => 'ی',
                'ى' => 'ی',
                'ك' => 'ک',
                'ۀ' => 'ه',
                'ة' => 'ه',
            )
        );

        $value = preg_replace('/[\x{200c}\x{200e}\x{200f}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($value) ? $value : '';
    }

    private function slugKey(string $value): string
    {
        $value = $this->normalizePersianText($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);

        if (! is_string($value)) {
            return '';
        }

        return trim($value, '-');
    }

    private function isIranAddress(array $address): bool
    {
        return $this->normalizeCountry((string) ($address['country'] ?? '')) === self::COUNTRY_IRAN;
    }

    private function isCompleteIranAddress(array $address): bool
    {
        if (! $this->isIranAddress($address)) {
            return false;
        }

        $state = trim((string) ($address['state_raw'] ?? ''));
        $city = trim((string) ($address['city_raw'] ?? ''));

        return $state !== '' && $city !== '';
    }

    private function hasGeoValue(array $address): bool
    {
        return trim((string) ($address['state_raw'] ?? '')) !== ''
            || trim((string) ($address['city_raw'] ?? '')) !== '';
    }
}
