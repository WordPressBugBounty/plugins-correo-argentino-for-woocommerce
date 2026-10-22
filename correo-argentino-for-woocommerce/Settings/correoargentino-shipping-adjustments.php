<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the surcharge/discount adjustment block.
 */
function corrarfo_render_shipping_surcharge_settings()
{
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('corrarfo_shipping_settings');
        do_settings_sections('corrarfo_shipping_settings');
        submit_button();
        ?>
    </form>
    <?php
}

add_action('admin_init', function () {
    if (\Corrarfo\Lib\Utils::getCurrentServiceType() !== CORRARFO_MI_CORREO) {
        return;
    }

    \Corrarfo\Lib\ServiceOptions::registerScopedSetting('corrarfo_shipping_settings', CORRARFO_OPTION_SHIPPING_ENABLE, [
        'sanitize_callback' => function ($value) {
            return $value ? 1 : 0;
        }
    ]);

    \Corrarfo\Lib\ServiceOptions::registerScopedSetting('corrarfo_shipping_settings', CORRARFO_OPTION_SHIPPING_TYPE, [
        'sanitize_callback' => function ($value) {
            return in_array($value, ['surcharge', 'discount'], true) ? $value : 'surcharge';
        }
    ]);

    \Corrarfo\Lib\ServiceOptions::registerScopedSetting('corrarfo_shipping_settings', CORRARFO_OPTION_SHIPPING_PERCENTAGE, [
        'sanitize_callback' => function ($value) {
            if (!is_scalar($value)) {
                return '0.00';
            }
            $value = wp_unslash((string) $value);
            $normalized = str_replace(',', '.', trim($value));
            if (!is_numeric($normalized)) {
                return '0.00';
            }
            $float = (float) $normalized;
            $float = max(0, min(100, $float));
            return number_format($float, 2, '.', '');
        }
    ]);

    \Corrarfo\Lib\ServiceOptions::registerScopedSetting('corrarfo_shipping_settings', CORRARFO_OPTION_SHIPPING_MIN_TOTAL, [
        'sanitize_callback' => function ($value) {
            if (!is_scalar($value)) {
                return '0.00';
            }
            $value = wp_unslash((string) $value);
            $normalized = str_replace(',', '.', trim($value));
            if (!is_numeric($normalized)) {
                return '0.00';
            }
            $float = max(0, (float) $normalized);
            return number_format($float, 2, '.', '');
        }
    ]);

    add_settings_section(
        'corrarfo_shipping_section',
        __('Shipping Surcharge / Discount Adjustments', 'correo-argentino-for-woocommerce'),
        'corrarfo_render_shipping_surcharge_settings_description',
        'corrarfo_shipping_settings'
    );

    function corrarfo_render_shipping_surcharge_settings_description()
    {
        echo '<p>' . esc_html__('Configure a percentage surcharge or discount on the base shipping cost returned by MiCorreo. The adjustment will only apply if the indicated minimum amount is reached.', 'correo-argentino-for-woocommerce') . '</p>';
    }

    $enable_key = \Corrarfo\Lib\ServiceOptions::buildKey(CORRARFO_OPTION_SHIPPING_ENABLE);
    $type_key = \Corrarfo\Lib\ServiceOptions::buildKey(CORRARFO_OPTION_SHIPPING_TYPE);
    $percentage_key = \Corrarfo\Lib\ServiceOptions::buildKey(CORRARFO_OPTION_SHIPPING_PERCENTAGE);
    $min_total_key = \Corrarfo\Lib\ServiceOptions::buildKey(CORRARFO_OPTION_SHIPPING_MIN_TOTAL);

    add_settings_field(
        'corrarfo_shipping_enable',
        __('Enable adjustment', 'correo-argentino-for-woocommerce'),
        function () use ($enable_key) {
            $value = \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_SHIPPING_ENABLE, false);
            echo "<input type='checkbox' id='corrarfo_shipping_enable' name='" . esc_attr($enable_key) . "' value='1' " . checked(1, $value, false) . " />";
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );

    add_settings_field(
        'corrarfo_shipping_type',
        __('Adjustment Type', 'correo-argentino-for-woocommerce'),
        function () use ($type_key) {
            $value = \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_SHIPPING_TYPE, 'surcharge');
            echo '<select id="corrarfo_shipping_type" name="' . esc_attr($type_key) . '">';
            echo '<option value="surcharge" ' . selected($value, 'surcharge', false) . '>' . esc_html__('Surcharge', 'correo-argentino-for-woocommerce') . '</option>';
            echo '<option value="discount" ' . selected($value, 'discount', false) . '>' . esc_html__('Discount', 'correo-argentino-for-woocommerce') . '</option>';
            echo '</select>';
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );

    add_settings_field(
        'corrarfo_shipping_percentage',
        __('Percentage (%)', 'correo-argentino-for-woocommerce'),
        function () use ($percentage_key) {
            $value = number_format((float) \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_SHIPPING_PERCENTAGE, 0), 2, '.', '');
            echo "<input type='number' id='corrarfo_shipping_percentage' step='0.10' inputmode='decimal' name='" . esc_attr($percentage_key) . "' value='" . esc_attr($value) . "' min='0' max='100' />";
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );

    add_settings_field(
        'corrarfo_shipping_min_total',
        __('Minimum order amount', 'correo-argentino-for-woocommerce'),
        function () use ($min_total_key) {
            $value = number_format((float) \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_SHIPPING_MIN_TOTAL, 0), 2, '.', '');
            echo "<input type='number' id='corrarfo_shipping_min_total' step='0.10' inputmode='decimal' name='" . esc_attr($min_total_key) . "' value='" . esc_attr($value) . "' min='0' />";
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );
});

/**
 * Admin enqueue: surcharge/discount adjustments.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';

    $match = ($page === 'corrarfo_shipping_adjustments');

    if (!$match && $page === 'wc-settings' && $tab === 'shipping' && $section === CORRARFO_PLUGIN_ID) {
        $match = true;
    }

    if (!$match && function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'corrarfo_shipping_adjustments') !== false) {
            $match = true;
        }
    }

    if (!$match) {
        return;
    }

    wp_enqueue_script(
        'ca-admin-shipping-adjustments',
        CORRARFO_PLUGIN_URL . 'js/admin-shipping-adjustments.js',
        array('jquery'),
        (defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time()),
        true
    );
}, 20);
