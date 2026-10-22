<?php
namespace Corrarfo\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

use WC_Order;

class AdminOrderShippingMethod
{

    public static function init()
    {
        // Displays shipping method(s) on WooCommerce admin order details page
        add_action('woocommerce_admin_order_data_after_shipping_address', [__CLASS__, 'displayShippingMethod'], 10, 1);
    }

    public static function displayShippingMethod($order)
    {
        if (!$order instanceof WC_Order) {
            return;
        }

        $shipping_methods = $order->get_shipping_methods();
        if (!empty($shipping_methods)) {
            echo '<p><strong>' . esc_html__('Shipping method:', 'correo-argentino-for-woocommerce') . '</strong><br>';
            foreach ($shipping_methods as $method) {
                echo esc_html($method->get_name()) . '<br>';
            }
            echo '</p>';
        }

        // Show ID with prefix if exists
        $customId = $order->get_meta('_corrarfo_prefixed_order_id');
        if (!empty($customId)) {
            echo '<p><strong>' . esc_html__('MiCorreo Reference ID:', 'correo-argentino-for-woocommerce') . '</strong> ' . esc_html($customId) . '</p>';
        }

        // Display Correo Argentino Shipping Cost (only for MiCorreo)
        $ca_shipping_cost = null;
        $is_micorreo = false;

        if (!empty($shipping_methods)) {
            foreach ($shipping_methods as $method) {
                if (strpos($method->get_method_id(), CORRARFO_PLUGIN_ID) !== false) {
                    $service_type = $method->get_meta(CORRARFO_CHOSEN_SERVICE_TYPE);
                    if ($service_type === CORRARFO_MI_CORREO) {
                        $is_micorreo = true;
                    }

                    $original_cost = $method->get_meta('_corrarfo_original_shipping_cost');
                    if ($original_cost !== '') {
                        $ca_shipping_cost = $original_cost;
                    } else {
                        $ca_shipping_cost = $method->get_total();
                    }
                    break;
                }
            }
        }

        if ($is_micorreo && $ca_shipping_cost !== null) {
            echo '<p><strong>' . esc_html__('Correo Argentino shipping cost:', 'correo-argentino-for-woocommerce') . '</strong> ' . wp_kses_post(wc_price($ca_shipping_cost)) . '</p>';
        }

        if (\Corrarfo\Lib\Utils::orderHadFreeShipping($order)) {
            echo '<div style="margin-top: 10px;">
                <strong>' . esc_html__('Free shipping Correo Argentino', 'correo-argentino-for-woocommerce') . '</strong>
            </div>';
        }

    }
}

// Backward compatibility alias
if (!class_exists('AdminOrderShippingMethod')) {
    class_alias(AdminOrderShippingMethod::class, 'AdminOrderShippingMethod');
}
