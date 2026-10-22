<?php

if (!defined('ABSPATH')) {
    exit;
}

use Corrarfo\Lib\ServiceOptions;
use Corrarfo\Lib\Utils;

/**
 * Persists free-shipping snapshot on the order using checkout session service/config.
 *
 * @param WC_Order $order
 * @return void
 */
function corrarfo_snapshot_order_free_shipping_meta($order): void
{
    if (!$order instanceof WC_Order) {
        return;
    }

    $checkout_service = Utils::getCheckoutServiceType();

    if ($checkout_service !== CORRARFO_MI_CORREO) {
        $order->update_meta_data('_corrarfo_free_shipping', 'no');
        return;
    }

    $enabled = ServiceOptions::get(CORRARFO_OPTION_FREE_SHIPPING_ENABLED, 'no', $checkout_service);
    if ($enabled !== 'yes') {
        $order->update_meta_data('_corrarfo_free_shipping', 'no');
        return;
    }

    $subtotal = $order->get_subtotal();
    $mode = ServiceOptions::get('corrarfo_free_shipping_mode', 'global', $checkout_service);
    $is_free = false;

    if ($mode === 'global') {
        $min_amount = floatval(ServiceOptions::get(CORRARFO_OPTION_FREE_SHIPPING_AMOUNT, 0, $checkout_service));
        $is_free = ($subtotal >= $min_amount);
    } else {
        $rules = ServiceOptions::get('corrarfo_free_shipping_individual_rules', [], $checkout_service);
        foreach ($order->get_shipping_methods() as $shipping_item) {
            $method_id = $shipping_item->get_method_id();
            $instance_id = $shipping_item->get_instance_id();
            if ($method_id !== CORRARFO_PLUGIN_ID || !$instance_id) {
                continue;
            }

            if (!isset($rules[$instance_id])) {
                continue;
            }

            $rule = $rules[$instance_id];
            $inst_enabled = isset($rule['enabled']) ? $rule['enabled'] : 'no';
            $inst_amount = isset($rule['amount']) ? floatval($rule['amount']) : 0.0;
            if ($inst_enabled === 'yes' && $subtotal >= $inst_amount) {
                $is_free = true;
                break;
            }
        }
    }

    $order->update_meta_data('_corrarfo_free_shipping', $is_free ? 'yes' : 'no');
}

add_action('woocommerce_checkout_create_order', 'corrarfo_snapshot_order_free_shipping_meta', 20, 1);

add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && Utils::orderHadFreeShipping($order)) {
        $order->add_order_note(__('Free shipping Correo Argentino', 'correo-argentino-for-woocommerce'));
    }
});
