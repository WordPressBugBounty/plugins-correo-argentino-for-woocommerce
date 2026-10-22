<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registration of default values for products without dimensions/weight
 */
add_action('admin_init', function () {
    $enabled_key = \Corrarfo\Lib\ServiceOptions::buildKey(CORRARFO_OPTION_DEFAULT_ENABLED);

    \Corrarfo\Lib\ServiceOptions::registerScopedSetting('corrarfo_default_product_settings', CORRARFO_OPTION_DEFAULT_ENABLED, [
        'sanitize_callback' => function ($value) {
            return $value ? 1 : 0;
        }
    ]);

    $fields = [
        CORRARFO_OPTION_DEFAULT_WEIGHT => __('Default weight (kg)', 'correo-argentino-for-woocommerce'),
        CORRARFO_OPTION_DEFAULT_HEIGHT => __('Default height (cm)', 'correo-argentino-for-woocommerce'),
        CORRARFO_OPTION_DEFAULT_WIDTH  => __('Default width (cm)', 'correo-argentino-for-woocommerce'),
        CORRARFO_OPTION_DEFAULT_LENGTH => __('Default length (cm)', 'correo-argentino-for-woocommerce'),
    ];

    foreach ($fields as $base_key => $label) {
        \Corrarfo\Lib\ServiceOptions::registerScopedSetting('corrarfo_default_product_settings', $base_key, [
            'sanitize_callback' => function ($value) use ($base_key) {
                if (!is_scalar($value)) {
                    return \Corrarfo\Lib\ServiceOptions::get($base_key, '');
                }

                $value = wp_unslash((string) $value);
                $normalized = str_replace(',', '.', trim($value));
                if (!is_numeric($normalized)) {
                    return \Corrarfo\Lib\ServiceOptions::get($base_key, '');
                }

                $float = (float) $normalized;

                if ($base_key === CORRARFO_OPTION_DEFAULT_WEIGHT) {
                    if ($float <= 0 || $float > 50) {
                        add_settings_error($base_key, 'invalid_weight', __('The weight must be between 0.01 and 50 kg.', 'correo-argentino-for-woocommerce'));
                        return \Corrarfo\Lib\ServiceOptions::get($base_key, '');
                    }
                } elseif ($float <= 0 || $float > 200) {
                    add_settings_error($base_key, 'invalid_dimension', __('The dimensions must be between 0.01 and 200 cm.', 'correo-argentino-for-woocommerce'));
                    return \Corrarfo\Lib\ServiceOptions::get($base_key, '');
                }

                return number_format($float, 2, '.', '');
            }
        ]);
    }

    add_settings_section(
        'corrarfo_defaults_section',
        null,
        null,
        'corrarfo_default_product_settings'
    );

    add_settings_field(
        'corrarfo_default_enabled',
        __('Enable adjustment', 'correo-argentino-for-woocommerce'),
        function () use ($enabled_key) {
            $enabled = \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_DEFAULT_ENABLED, '');
            echo "<input type='checkbox' id='corrarfo_default_enabled' name='" . esc_attr($enabled_key) . "' value='1' " . checked(1, $enabled, false) . " />";
        },
        'corrarfo_default_product_settings',
        'corrarfo_defaults_section'
    );

    foreach ($fields as $base_key => $label) {
        $scoped_key = \Corrarfo\Lib\ServiceOptions::buildKey($base_key);
        add_settings_field(
            $base_key,
            $label,
            function () use ($base_key, $scoped_key) {
                $enabled = \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_DEFAULT_ENABLED, '');
                $value = esc_attr(\Corrarfo\Lib\ServiceOptions::get($base_key, ''));
                $max = $base_key === CORRARFO_OPTION_DEFAULT_WEIGHT ? '50' : '200';
                echo sprintf(
                    "<input type='number' class='corrarfo-default-dimension-field' step='0.01' min='0.01' max='%s' name='%s' value='%s' %s />",
                    esc_attr($max),
                    esc_attr($scoped_key),
                    esc_attr($value),
                    $enabled ? '' : 'disabled'
                );
            },
            'corrarfo_default_product_settings',
            'corrarfo_defaults_section'
        );
    }
});

/**
 * Render of the "Default Values" block
 */
function corrarfo_render_default_product_values_settings() {
    $service_type = \Corrarfo\Lib\Utils::getCurrentServiceType();
    $service_label = ($service_type === CORRARFO_PAQ_AR) ? 'Paq.Ar' : 'MiCorreo';
    ?>
    <h2><?php echo esc_html(sprintf(__('Default values for products without data', 'correo-argentino-for-woocommerce'), $service_label)); ?></h2>
    <p><?php esc_html_e('The system requires weight and dimensions to process shipments. If a product does not have this data loaded, the default values configured below will be applied to ensure the continuity of rate calculation.', 'correo-argentino-for-woocommerce'); ?></p>
    <form method="post" action="options.php">
        <?php
            settings_fields('corrarfo_default_product_settings');
            do_settings_sections('corrarfo_default_product_settings');
            submit_button();
        ?>
    </form>
    <?php
}

/**
 * Enqueue admin JS to toggle default product fields
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page  = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $match = ( $page === 'corrarfo_shipping_adjustments' );

    if ( ! $match && function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'corrarfo_shipping_adjustments' ) !== false ) {
            $match = true;
        }
    }

    if ( ! $match ) {
        return;
    }

    $base_url = CORRARFO_PLUGIN_URL;
    $ver      = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_script(
        'ca-admin-default-product-values',
        $base_url . 'js/admin-default-product-values.js',
        [],
        $ver,
        true
    );
}, 20);
