<?php

declare(strict_types=1);

namespace Hashieban\Integration\WooCommerce\Geo;

use WC_Order;

final class GeoAddressCapture
{
    private GeoAddressResolver $resolver;

    public function __construct(GeoAddressResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function register(): void
    {
        add_filter(
            'woocommerce_get_country_locale',
            array($this, 'requireIranGeoFields')
        );

        add_action(
            'woocommerce_checkout_order_created',
            array($this, 'snapshotCheckoutOrder')
        );

        add_action(
            'woocommerce_store_api_checkout_order_processed',
            array($this, 'snapshotCheckoutOrder')
        );
    }

    public function requireIranGeoFields(array $locales): array
    {
        if (! isset($locales[GeoAddressResolver::COUNTRY_IRAN])) {
            $locales[GeoAddressResolver::COUNTRY_IRAN] = array();
        }

        if (! isset($locales[GeoAddressResolver::COUNTRY_IRAN]['state'])) {
            $locales[GeoAddressResolver::COUNTRY_IRAN]['state'] = array();
        }

        if (! isset($locales[GeoAddressResolver::COUNTRY_IRAN]['city'])) {
            $locales[GeoAddressResolver::COUNTRY_IRAN]['city'] = array();
        }

        $locales[GeoAddressResolver::COUNTRY_IRAN]['state']['required'] = true;
        $locales[GeoAddressResolver::COUNTRY_IRAN]['state']['hidden'] = false;
        $locales[GeoAddressResolver::COUNTRY_IRAN]['state']['label'] = 'استان';

        $locales[GeoAddressResolver::COUNTRY_IRAN]['city']['required'] = true;
        $locales[GeoAddressResolver::COUNTRY_IRAN]['city']['hidden'] = false;
        $locales[GeoAddressResolver::COUNTRY_IRAN]['city']['label'] = 'شهر';

        return $locales;
    }

    public function snapshotCheckoutOrder(WC_Order $order): void
    {
        $geo = $this->resolver->resolve($order);

        $order->update_meta_data('_hashieban_geo_source', (string) $geo['source']);
        $order->update_meta_data('_hashieban_geo_country', (string) $geo['country']);
        $order->update_meta_data('_hashieban_geo_state_code', (string) $geo['state_code']);
        $order->update_meta_data('_hashieban_geo_state_name', (string) $geo['state_name']);
        $order->update_meta_data('_hashieban_geo_city_name', (string) $geo['city_name']);
        $order->update_meta_data('_hashieban_geo_city_key', (string) $geo['city_key']);
        $order->update_meta_data('_hashieban_geo_complete', ! empty($geo['complete']) ? 'yes' : 'no');
        $order->update_meta_data('_hashieban_geo_schema', '1');
        $order->save_meta_data();
    }
}
