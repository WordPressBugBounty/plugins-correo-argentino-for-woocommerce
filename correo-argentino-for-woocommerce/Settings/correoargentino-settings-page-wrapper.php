<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-shipping-adjustments.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-free-shipping-settings.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-default-product-values.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-autosend-settings.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-uninstall-settings.php';

defined('ABSPATH') || exit;

/**
 * Renders the main "Shipping Adjustments" page, which contains multiple blocks
 * (e.g., surcharge/discount, default values, etc.)
 */
function corrarfo_shipping_adjustments_page()
{
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Shipping Settings', 'correo-argentino-for-woocommerce') . '</h1>';
    
    // Override standard WP "Settings saved" with our custom text
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $settings_updated = isset($_GET['settings-updated']) ? sanitize_text_field(wp_unslash($_GET['settings-updated'])) : false;
    if ($settings_updated) {
        global $wp_settings_errors;
        get_settings_errors(); // Ensure transient errors are loaded
        $found = false;
        if (is_array($wp_settings_errors)) {
            foreach ($wp_settings_errors as &$error) {
                if ($error['code'] === 'settings_updated') {
                    $error['message'] = __('Configuration saved.', 'correo-argentino-for-woocommerce');
                    $found = true;
                }
            }
        }
        if (!$found) {
            add_settings_error('general', 'settings_updated', __('Configuration saved.', 'correo-argentino-for-woocommerce'), 'updated');
        }
    }
    
    settings_errors();

    // Block: Surcharge / Discount
    if (\Corrarfo\Lib\Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        if (function_exists('corrarfo_render_shipping_surcharge_settings')) {
            corrarfo_render_shipping_surcharge_settings();
        }
    }

    echo '<hr>';

    // Block: Default values for products without data
    if (function_exists('corrarfo_render_default_product_values_settings')) {
        corrarfo_render_default_product_values_settings();
        echo '<hr>';
    }

    // Block: Free Shipping Settings
    if (\Corrarfo\Lib\Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        if (function_exists('corrarfo_render_free_shipping_settings')) {
            corrarfo_render_free_shipping_settings();
            echo '<hr>';
        }
    }
    echo '</div>';
}

/**
 * Renders the "General Behavior" page for the plugin settings.
 */
function corrarfo_behavior_settings_page()
{
    $isConnected = \Corrarfo\Lib\Utils::isConnected();
    $currentServiceType = \Corrarfo\Lib\Utils::getCurrentServiceType();

    $serviceLabel = $isConnected
        ? (($currentServiceType === CORRARFO_PAQ_AR) ? 'PAQ.AR' : 'MiCorreo')
        : '';

    $pageTitle = __('Dashboard', 'correo-argentino-for-woocommerce') . ($serviceLabel ? " $serviceLabel" : '');

    echo '<div class="wrap">';
    echo '<h1>' . esc_html($pageTitle) . '</h1>';
    settings_errors();
    if ($isConnected && $currentServiceType === CORRARFO_MI_CORREO) {
        require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-autostatus-settings.php';
        if (function_exists('corrarfo_render_autostatus_settings')) {
            corrarfo_render_autostatus_settings();
        }
        echo '<hr>';
    }

    if ($isConnected && $currentServiceType === CORRARFO_MI_CORREO) {
        require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-autosend-settings.php';
        if (function_exists('corrarfo_render_autosend_settings')) {
            corrarfo_render_autosend_settings();
        }
        echo '<hr>';
    }

    if (\Corrarfo\Lib\Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        if (function_exists('corrarfo_render_order_prefix_settings')) {
            corrarfo_render_order_prefix_settings();
        }
        echo '<hr>';
    }

    if (function_exists('corrarfo_render_uninstall_settings')) {
        echo '<form method="post" action="options.php">';
        settings_fields('correoargentino_settings');
        corrarfo_render_uninstall_settings();
        submit_button();
        echo '</form>';
        echo '<hr>';
    }


    echo '</div>';
}