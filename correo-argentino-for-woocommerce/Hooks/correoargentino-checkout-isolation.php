<?php

if (!defined('ABSPATH')) {
    exit;
}

use Corrarfo\Lib\Utils;

const CORRARFO_CHECKOUT_SERVICE_SESSION_KEY = 'corrarfo_active_checkout_service';

/**
 * Clears WooCommerce shipping state so checkout recalculates with the active service config.
 */
function corrarfo_clear_checkout_shipping_state(): void
{
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $packages = WC()->cart->get_shipping_packages();
    if (WC()->session) {
        foreach (array_keys($packages) as $key) {
            WC()->session->__unset('shipping_for_package_' . $key);
        }

        WC()->session->set('chosen_shipping_methods', []);

        foreach (WC()->session->get_session_data() as $session_key => $unused) {
            if (
                strpos($session_key, 'rateList_') === 0
                || strpos($session_key, 'rateListKey_') === 0
            ) {
                WC()->session->__unset($session_key);
            }
        }
    }

    if (WC()->session && method_exists(Utils::class, 'unset_wc_chosen_branch_values')) {
        Utils::unset_wc_chosen_branch_values();
    }
}

/**
 * Detects service switches during cart/checkout and invalidates stale shipping data.
 */
function corrarfo_sync_checkout_service_isolation(): void
{
    static $is_syncing = false;

    if ($is_syncing || !function_exists('WC') || !WC()->session || !WC()->cart) {
        return;
    }

    if (!is_cart() && !is_checkout() && !(defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }

    $current_service = Utils::getCurrentServiceType();
    if (empty($current_service)) {
        return;
    }

    $stored_service = WC()->session->get(CORRARFO_CHECKOUT_SERVICE_SESSION_KEY);
    if (!empty($stored_service) && $stored_service !== $current_service) {
        $is_syncing = true;
        WC()->session->set(CORRARFO_CHECKOUT_SERVICE_SESSION_KEY, $current_service);
        corrarfo_clear_checkout_shipping_state();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        $is_syncing = false;
        return;
    }

    WC()->session->set(CORRARFO_CHECKOUT_SERVICE_SESSION_KEY, $current_service);
}

/**
 * Removes MiCorreo-only labels from cached rates when Paq.Ar is active.
 *
 * @param array $rates
 * @return array
 */
function corrarfo_filter_package_rates_by_service(array $rates): array
{
    if (Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        return $rates;
    }

    $free_label_suffix = ' — ' . __('Free Shipping', 'correo-argentino-for-woocommerce');

    foreach ($rates as $rate_id => $rate) {
        if (strpos((string) $rate_id, CORRARFO_PLUGIN_ID) !== 0) {
            continue;
        }

        if (strpos($rate->label, $free_label_suffix) !== false) {
            $rates[$rate_id]->label = str_replace($free_label_suffix, '', $rate->label);
        }
    }

    return $rates;
}

add_action('update_option_' . CORRARFO_SERVICE_TYPE, function ($old_value, $new_value) {
    if ($old_value === $new_value) {
        return;
    }

    corrarfo_clear_checkout_shipping_state();

    if (function_exists('WC') && WC()->session) {
        WC()->session->set(CORRARFO_CHECKOUT_SERVICE_SESSION_KEY, $new_value);
    }
}, 10, 2);

add_action('woocommerce_before_calculate_totals', 'corrarfo_sync_checkout_service_isolation', 1);
add_action('woocommerce_before_cart', 'corrarfo_sync_checkout_service_isolation', 1);
add_action('woocommerce_before_checkout_form', 'corrarfo_sync_checkout_service_isolation', 1);

add_filter('woocommerce_package_rates', 'corrarfo_filter_package_rates_by_service', 99);
