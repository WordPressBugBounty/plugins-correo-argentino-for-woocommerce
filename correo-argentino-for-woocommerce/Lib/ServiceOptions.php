<?php

namespace Corrarfo\Lib;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads and writes plugin options scoped per service (MiCorreo / Paq.Ar).
 *
 * Option key pattern: {base_key}_{service_type}
 * Example: corrarfo_default_weight_micorreo
 */
class ServiceOptions
{
    private const MIGRATION_OPTION = 'corrarfo_service_options_migration_version';
    private const MIGRATION_VERSION = 2;

    /**
     * @param string|null $service_type
     * @return string
     */
    public static function resolveServiceType(?string $service_type = null): string
    {
        if (!empty($service_type)) {
            return $service_type;
        }

        $current = Utils::getCurrentServiceType();
        return !empty($current) ? $current : CORRARFO_MI_CORREO;
    }

    /**
     * @param string|null $service_type
     * @return bool
     */
    public static function isActiveMicorreo(?string $service_type = null): bool
    {
        return self::resolveServiceType($service_type) === CORRARFO_MI_CORREO;
    }

    /**
     * @param string $option_key
     * @return bool
     */
    public static function isMicorreoOnlyOption(string $option_key): bool
    {
        return in_array($option_key, self::getMicorreoOnlyBaseKeys(), true);
    }

    /**
     * @param string $option_key Base option name without service suffix.
     * @param string|null $service_type
     * @return string
     */
    public static function buildKey(string $option_key, ?string $service_type = null): string
    {
        return $option_key . '_' . self::resolveServiceType($service_type);
    }

    /**
     * @return string[]
     */
    public static function getMicorreoOnlyBaseKeys(): array
    {
        return [
            CORRARFO_OPTION_SHIPPING_ENABLE,
            CORRARFO_OPTION_SHIPPING_TYPE,
            CORRARFO_OPTION_SHIPPING_PERCENTAGE,
            CORRARFO_OPTION_SHIPPING_MIN_TOTAL,
            CORRARFO_OPTION_AUTOSEND_ENABLED,
            CORRARFO_OPTION_AUTOSEND_TRIGGER,
            CORRARFO_OPTION_AUTOSTATUS_ENABLED,
            CORRARFO_OPTION_AUTOSTATUS_STATUS,
            CORRARFO_OPTION_PREFIX_ENABLED,
            CORRARFO_OPTION_PREFIX_VALUE,
            CORRARFO_OPTION_FREE_SHIPPING_ENABLED,
            CORRARFO_OPTION_FREE_SHIPPING_AMOUNT,
            'corrarfo_free_shipping_mode',
            'corrarfo_free_shipping_individual_rules',
        ];
    }

    /**
     * @return string[]
     */
    public static function getSharedScopedBaseKeys(): array
    {
        return [
            CORRARFO_OPTION_DEFAULT_ENABLED,
            CORRARFO_OPTION_DEFAULT_WEIGHT,
            CORRARFO_OPTION_DEFAULT_HEIGHT,
            CORRARFO_OPTION_DEFAULT_WIDTH,
            CORRARFO_OPTION_DEFAULT_LENGTH,
        ];
    }

    /**
     * @return string[]
     */
    public static function getAllScopedBaseKeys(): array
    {
        return array_merge(self::getMicorreoOnlyBaseKeys(), self::getSharedScopedBaseKeys());
    }

    /**
     * @param string $option_key
     * @param mixed $default
     * @param string|null $service_type
     * @return mixed
     */
    public static function get(string $option_key, $default = null, ?string $service_type = null)
    {
        $service_type = self::resolveServiceType($service_type);

        if ($service_type === CORRARFO_PAQ_AR && self::isMicorreoOnlyOption($option_key)) {
            return $default;
        }

        $dynamic_key = self::buildKey($option_key, $service_type);

        $value = get_option($dynamic_key, false);
        if (false !== $value) {
            return $value;
        }

        // Legacy keys belong to MiCorreo only; never leak to Paq.Ar on read.
        if ($service_type === CORRARFO_MI_CORREO) {
            $legacy = get_option($option_key, false);
            if (false !== $legacy) {
                update_option($dynamic_key, $legacy);
                delete_option($option_key);
                return $legacy;
            }
        }

        return $default;
    }

    /**
     * @param string $option_key
     * @param mixed $value
     * @param string|null $service_type
     * @return bool
     */
    public static function update(string $option_key, $value, ?string $service_type = null): bool
    {
        return update_option(self::buildKey($option_key, $service_type), $value);
    }

    /**
     * @param string $group
     * @param string $option_key
     * @param array $args
     */
    public static function registerScopedSetting(string $group, string $option_key, array $args = []): void
    {
        register_setting($group, self::buildKey($option_key), $args);
    }

    /**
     * Runs incremental migrations for service-scoped options.
     */
    public static function migrateLegacyOptions(): void
    {
        $version = (int) get_option(self::MIGRATION_OPTION, 0);

        if ($version === 0 && get_option('corrarfo_service_options_migrated', false)) {
            $version = 1;
        }

        if ($version < 1) {
            self::migrateV1FreeShipping();
            $version = 1;
        }

        if ($version < 2) {
            self::migrateV2AllScopedSettings();
            $version = 2;
        }

        update_option(self::MIGRATION_OPTION, $version);
        delete_option('corrarfo_service_options_migrated');

        if ($version < 3) {
            self::scheduleOrderHistoricalMetaMigration();
        }
    }

    /**
     * Backfills historical order meta so legacy orders are not affected by service-scoped settings.
     */
    public static function scheduleOrderHistoricalMetaMigration(): void
    {
        if (did_action('woocommerce_init')) {
            self::migrateV3OrderHistoricalMeta();
            return;
        }

        add_action('woocommerce_init', [self::class, 'migrateV3OrderHistoricalMeta'], 20);
    }

    public static function migrateV3OrderHistoricalMeta(): void
    {
        static $has_run = false;
        if ($has_run) {
            return;
        }
        $has_run = true;

        $version = (int) get_option(self::MIGRATION_OPTION, 0);
        if ($version >= 3 || !function_exists('wc_get_orders')) {
            return;
        }

        $order_ids = wc_get_orders([
            'limit' => -1,
            'return' => 'ids',
            'status' => array_keys(wc_get_order_statuses()),
        ]);

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order || !Utils::orderUsesCorreoArgentinoShipping($order)) {
                continue;
            }

            $needs_save = false;

            if ($order->get_meta(CORRARFO_CHOSEN_SERVICE_TYPE, true) === '') {
                $order->update_meta_data(CORRARFO_CHOSEN_SERVICE_TYPE, CORRARFO_MI_CORREO);
                $needs_save = true;
            }

            if ($order->get_meta('_corrarfo_free_shipping', true) === ''
                && $order->get_meta('_ca_free_shipping', true) !== '') {
                $legacy_free = $order->get_meta('_ca_free_shipping', true) === 'yes' ? 'yes' : 'no';
                $order->update_meta_data('_corrarfo_free_shipping', $legacy_free);
                $needs_save = true;
            } elseif ($order->get_meta('_corrarfo_free_shipping', true) === '') {
                $shipping_total = (float) $order->get_shipping_total();
                $order->update_meta_data('_corrarfo_free_shipping', $shipping_total <= 0 ? 'yes' : 'no');
                $needs_save = true;
            }

            if ($needs_save) {
                $order->save();
            }
        }

        update_option(self::MIGRATION_OPTION, 3);
    }

    private static function migrateV1FreeShipping(): void
    {
        $legacy_free_shipping_keys = [
            CORRARFO_OPTION_FREE_SHIPPING_ENABLED,
            CORRARFO_OPTION_FREE_SHIPPING_AMOUNT,
            'corrarfo_free_shipping_mode',
            'corrarfo_free_shipping_individual_rules',
        ];

        foreach ($legacy_free_shipping_keys as $option_key) {
            self::migrateLegacyKeyToMicorreo($option_key);
        }

        self::resetPaqArFreeShippingOptions();
    }

    private static function migrateV2AllScopedSettings(): void
    {
        foreach (self::getAllScopedBaseKeys() as $option_key) {
            self::migrateLegacyKeyToMicorreo($option_key);
        }

        foreach (self::getMicorreoOnlyBaseKeys() as $option_key) {
            delete_option(self::buildKey($option_key, CORRARFO_PAQ_AR));
        }
    }

    private static function migrateLegacyKeyToMicorreo(string $option_key): void
    {
        $legacy = get_option($option_key, false);
        if (false === $legacy) {
            return;
        }

        $micorreo_key = self::buildKey($option_key, CORRARFO_MI_CORREO);
        if (false === get_option($micorreo_key, false)) {
            update_option($micorreo_key, $legacy);
        }

        delete_option($option_key);
    }

    /**
     * Paq.Ar has no free-shipping settings UI; clear values wrongly assigned by the old fallback.
     */
    private static function resetPaqArFreeShippingOptions(): void
    {
        $suffixes = ['enabled', 'amount', 'mode', 'individual_rules'];

        foreach ($suffixes as $suffix) {
            delete_option(self::buildKey('corrarfo_free_shipping_' . $suffix, CORRARFO_PAQ_AR));
        }

        update_option(self::buildKey(CORRARFO_OPTION_FREE_SHIPPING_ENABLED, CORRARFO_PAQ_AR), 'no');
    }

    /**
     * Deletes all service-scoped plugin options (used on hard uninstall).
     */
    public static function deleteAllScopedOptions(): void
    {
        foreach (self::getAllScopedBaseKeys() as $base_key) {
            delete_option(self::buildKey($base_key, CORRARFO_MI_CORREO));
            delete_option(self::buildKey($base_key, CORRARFO_PAQ_AR));
            delete_option($base_key);
        }
    }
}

/**
 * @param string $option_key
 * @param mixed $default
 * @param string|null $service_type
 * @return mixed
 */
function corrarfo_get_service_option(string $option_key, $default = null, ?string $service_type = null)
{
    return ServiceOptions::get($option_key, $default, $service_type);
}

/**
 * @param string $option_key
 * @param mixed $value
 * @param string|null $service_type
 * @return bool
 */
function corrarfo_update_service_option(string $option_key, $value, ?string $service_type = null): bool
{
    return ServiceOptions::update($option_key, $value, $service_type);
}
