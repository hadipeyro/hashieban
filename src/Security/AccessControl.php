<?php

declare(strict_types=1);

namespace Hashieban\Security;

final class AccessControl
{
    public function register(): void
    {
        add_filter(
            'user_has_cap',
            array($this, 'inheritLegacyWooCommerceAccess'),
            10,
            4
        );

        add_action(
            'admin_init',
            array($this, 'ensureDefaultRoleCapabilities'),
            1
        );
    }

    /**
     * Preserve Hashieban's historical behavior for users who already had
     * WooCommerce management access, while still exposing granular custom
     * capabilities for stores that want tighter role separation later.
     */
    public function inheritLegacyWooCommerceAccess(
        array $allCaps,
        array $caps,
        array $args,
        $user
    ): array {
        if (! empty($allCaps['manage_woocommerce'])) {
            foreach (Capabilities::all() as $capability) {
                $allCaps[$capability] = true;
            }
        }

        if (
            ! empty($allCaps[Capabilities::MANAGE_FINANCE])
            || ! empty($allCaps[Capabilities::MANAGE_SETTINGS])
            || ! empty($allCaps[Capabilities::MANAGE_TOOLS])
        ) {
            $allCaps[Capabilities::VIEW_REPORTS] = true;
        }

        return $allCaps;
    }

    public function ensureDefaultRoleCapabilities(): void
    {
        foreach (array('administrator', 'shop_manager') as $roleName) {
            $role = get_role($roleName);

            if (! $role) {
                continue;
            }

            foreach (Capabilities::all() as $capability) {
                $role->add_cap($capability);
            }
        }
    }
}
