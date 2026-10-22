<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gets a free shipping option scoped to the active service (MiCorreo / Paq.Ar).
 *
 * @param string $key_suffix e.g. 'enabled', 'amount', 'mode', 'individual_rules'
 * @param mixed $default
 * @return mixed
 */
function corrarfo_get_free_shipping_option($key_suffix, $default = null)
{
    if (!\Corrarfo\Lib\ServiceOptions::isActiveMicorreo()) {
        if ($key_suffix === 'enabled') {
            return 'no';
        }
        if ($key_suffix === 'individual_rules') {
            return [];
        }
        return $default;
    }

    return \Corrarfo\Lib\ServiceOptions::get('corrarfo_free_shipping_' . $key_suffix, $default);
}

/**
 * Updates a free shipping option scoped to the active service (MiCorreo / Paq.Ar).
 *
 * @param string $key_suffix e.g. 'enabled', 'amount', 'mode', 'individual_rules'
 * @param mixed $value
 * @return bool
 */
function corrarfo_update_free_shipping_option($key_suffix, $value)
{
    return \Corrarfo\Lib\ServiceOptions::update('corrarfo_free_shipping_' . $key_suffix, $value);
}

/**
 * Retrieves all active shipping method instances of Correo Argentino.
 *
 * @return array
 */
function corrarfo_get_active_shipping_instances()
{
    if (!class_exists('WC_Shipping_Zones')) {
        return [];
    }

    $zones = \WC_Shipping_Zones::get_zones();
    $instances = [];

    // Check custom zones
    foreach ($zones as $zone_data) {
        $zone = \WC_Shipping_Zones::get_zone($zone_data['zone_id']);
        $methods = $zone->get_shipping_methods();
        foreach ($methods as $method) {
            if ($method->id === CORRARFO_PLUGIN_ID) {
                $instances[] = $method;
            }
        }
    }

    // Check default zone (ID 0)
    $zone_0 = \WC_Shipping_Zones::get_zone(0);
    if ($zone_0) {
        $methods_0 = $zone_0->get_shipping_methods();
        foreach ($methods_0 as $method) {
            if ($method->id === CORRARFO_PLUGIN_ID) {
                $instances[] = $method;
            }
        }
    }

    return $instances;
}

/**
 * Checks if free shipping is active for a specific shipping method and cart subtotal.
 *
 * @param string $chosenShippingMethod e.g., "correoargentino_shipping_method:3"
 * @param float $subtotal
 * @return bool
 */
function corrarfo_is_free_shipping_active_for_method($chosenShippingMethod, $subtotal)
{
    if (!\Corrarfo\Lib\ServiceOptions::isActiveMicorreo()) {
        return false;
    }

    $enabled = corrarfo_get_free_shipping_option('enabled', 'no');
    if ($enabled !== 'yes') {
        return false;
    }

    $mode = corrarfo_get_free_shipping_option('mode', 'global');

    if ($mode === 'global') {
        $minAmount = floatval(corrarfo_get_free_shipping_option('amount'));
        return ($subtotal >= $minAmount);
    } else {
        // Individual mode
        if (strpos($chosenShippingMethod, CORRARFO_PLUGIN_ID) === 0) {
            $parts = explode(':', $chosenShippingMethod);
            $instance_id = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($instance_id > 0) {
                $rules = corrarfo_get_free_shipping_option('individual_rules', []);
                if (isset($rules[$instance_id])) {
                    $rule = $rules[$instance_id];
                    $inst_enabled = isset($rule['enabled']) ? $rule['enabled'] : 'no';
                    $inst_amount = isset($rule['amount']) ? floatval($rule['amount']) : 0.0;
                    return ($inst_enabled === 'yes' && $subtotal >= $inst_amount);
                }
            }
        }
    }
    return false;
}

/**
 * Checks if at least one active Correo Argentino method qualifies for free shipping.
 *
 * @param float $subtotal
 * @return bool
 */
function corrarfo_any_active_method_qualifies_for_free_shipping($subtotal)
{
    if (!\Corrarfo\Lib\ServiceOptions::isActiveMicorreo()) {
        return false;
    }

    $mode = corrarfo_get_free_shipping_option('mode', 'global');
    if ($mode === 'global') {
        $minAmount = floatval(corrarfo_get_free_shipping_option('amount'));
        return ($minAmount && $subtotal >= $minAmount);
    }

    // Individual mode: check all active instances
    $instances = corrarfo_get_active_shipping_instances();
    $rules = corrarfo_get_free_shipping_option('individual_rules', []);
    foreach ($instances as $instance) {
        if ($instance->enabled === 'yes') {
            $instance_id = $instance->instance_id;
            if (isset($rules[$instance_id])) {
                $rule = $rules[$instance_id];
                $inst_enabled = isset($rule['enabled']) ? $rule['enabled'] : 'no';
                $inst_amount = isset($rule['amount']) ? floatval($rule['amount']) : 0.0;
                if ($inst_enabled === 'yes' && $subtotal >= $inst_amount) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Applies free shipping if the configuration is enabled and the cart subtotal exceeds the threshold.
 * Should be called from inside the calculate_shipping() method.
 *
 * @param float $currentRate
 * @param float $cartSubtotal
 * @param WC_Shipping_Method|null $shippingMethod
 * @return float
 */
function corrarfo_process_free_shipping($currentRate, $cartSubtotal, $shippingMethod = null)
{
    if (!\Corrarfo\Lib\ServiceOptions::isActiveMicorreo()) {
        return $currentRate;
    }

    $enabled = corrarfo_get_free_shipping_option('enabled', 'no');
    if ($enabled !== 'yes') {
        return $currentRate;
    }

    $mode = corrarfo_get_free_shipping_option('mode', 'global');

    if ($mode === 'global') {
        $minAmount = floatval(corrarfo_get_free_shipping_option('amount'));
        if ($cartSubtotal >= $minAmount) {
            return 0;
        }
    } elseif ($shippingMethod) {
        $instance_id = $shippingMethod->instance_id;
        $rules = corrarfo_get_free_shipping_option('individual_rules', []);
        if (isset($rules[$instance_id])) {
            $rule = $rules[$instance_id];
            $inst_enabled = isset($rule['enabled']) ? $rule['enabled'] : 'no';
            $inst_amount = isset($rule['amount']) ? floatval($rule['amount']) : 0.0;
            if ($inst_enabled === 'yes' && $cartSubtotal >= $inst_amount) {
                return 0;
            }
        }
    }

    return $currentRate;
}

/*
 * NOTE: The free shipping banner (corrarfo_show_free_shipping_message) was removed.
 * Free shipping is now indicated directly in each shipping method's label in the checkout.
 */


/*
 * Historical free-shipping meta is snapshotted in correoargentino-order-regression.php
 * using checkout session service/config (never recalculated from current admin service).
 */
