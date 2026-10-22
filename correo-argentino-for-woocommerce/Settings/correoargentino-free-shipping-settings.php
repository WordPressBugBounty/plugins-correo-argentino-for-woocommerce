<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Free Shipping settings section for MiCorreo / Paq.Ar
 */
function corrarfo_render_free_shipping_settings() {
    require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-free-shipping-hook.php';

    $enabled = corrarfo_get_free_shipping_option('enabled', 'no');
    $amount  = corrarfo_get_free_shipping_option('amount', '');
    $mode    = corrarfo_get_free_shipping_option('mode', 'global');
    $individual_rules = corrarfo_get_free_shipping_option('individual_rules', []);

    $instances = corrarfo_get_active_shipping_instances();

    $current_service = \Corrarfo\Lib\Utils::getCurrentServiceType();
    $service_label = ($current_service === CORRARFO_PAQ_AR) ? 'Paq.Ar' : 'MiCorreo';
    ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field('corrarfo_free_shipping_verify'); ?>
        <input type="hidden" name="action" value="corrarfo_free_shipping_save" />

        <h2><?php esc_html_e('Free Shipping Settings', 'correo-argentino-for-woocommerce'); ?></h2>
        <p><?php
            /* translators: %s: connected service name (MiCorreo or Paq.Ar). */
            echo esc_html(sprintf(__('Configure free shipping for %s. If the total order amount reaches or exceeds the defined value, the customer will not be charged for shipping.', 'correo-argentino-for-woocommerce'), $service_label));
        ?></p>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Enable adjustment', 'correo-argentino-for-woocommerce'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="corrarfo_free_shipping_enabled" name="corrarfo_free_shipping_enabled" value="yes" <?php checked($enabled, 'yes'); ?> />
                        <?php esc_html_e('Activate free shipping threshold', 'correo-argentino-for-woocommerce'); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top" id="corrarfo_free_shipping_mode_row">
                <th scope="row"><?php esc_html_e('Configuration mode', 'correo-argentino-for-woocommerce'); ?></th>
                <td>
                    <label style="margin-right: 15px;">
                        <input type="radio" class="corrarfo_free_shipping_mode_radio" name="corrarfo_free_shipping_mode" value="global" <?php checked($mode, 'global'); ?> />
                        <?php esc_html_e('Global (Same amount for all methods)', 'correo-argentino-for-woocommerce'); ?>
                    </label>
                    <label>
                        <input type="radio" class="corrarfo_free_shipping_mode_radio" name="corrarfo_free_shipping_mode" value="individual" <?php checked($mode, 'individual'); ?> />
                        <?php esc_html_e('Individual (Specific amount per method)', 'correo-argentino-for-woocommerce'); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top" id="corrarfo_free_shipping_amount_row">
                <th scope="row"><?php esc_html_e('Minimum order amount', 'correo-argentino-for-woocommerce'); ?></th>
                <td>
                    <input type="text" id="corrarfo_free_shipping_amount" name="corrarfo_free_shipping_amount" value="<?php echo esc_attr($amount); ?>" />
                    <p class="description"><?php esc_html_e('Enter the minimum amount (e.g. 3500.50)', 'correo-argentino-for-woocommerce'); ?></p>
                </td>
            </tr>
            <tr valign="top" id="corrarfo_free_shipping_individual_row">
                <th scope="row"><?php esc_html_e('Free shipping per method', 'correo-argentino-for-woocommerce'); ?></th>
                <td>
                    <?php if (empty($instances)) : ?>
                        <p class="description" style="color: #c00; font-weight: bold;">
                            <?php esc_html_e('No active Correo Argentino shipping methods found in WooCommerce zones.', 'correo-argentino-for-woocommerce'); ?>
                        </p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped" style="max-width: 800px;">
                            <thead>
                                <tr>
                                    <th style="padding: 10px;"><?php esc_html_e('Shipping Method Name', 'correo-argentino-for-woocommerce'); ?></th>
                                    <th style="padding: 10px;"><?php esc_html_e('Status', 'correo-argentino-for-woocommerce'); ?></th>
                                    <th style="padding: 10px;"><?php esc_html_e('Free Shipping', 'correo-argentino-for-woocommerce'); ?></th>
                                    <th style="padding: 10px;"><?php esc_html_e('Minimum Amount', 'correo-argentino-for-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($instances as $instance) : 
                                    $instance_id = $instance->instance_id;
                                    $inst_enabled = isset($individual_rules[$instance_id]['enabled']) ? $individual_rules[$instance_id]['enabled'] : 'no';
                                    $inst_amount = isset($individual_rules[$instance_id]['amount']) ? $individual_rules[$instance_id]['amount'] : '';
                                    $status_label = ($instance->enabled === 'yes') ? __('Active', 'correo-argentino-for-woocommerce') : __('Inactive', 'correo-argentino-for-woocommerce');
                                    $status_style = ($instance->enabled === 'yes') ? 'color: green; font-weight: bold;' : 'color: gray;';
                                ?>
                                    <tr>
                                        <td style="padding: 10px; vertical-align: middle;"><?php echo esc_html($instance->title); ?></td>
                                        <td style="padding: 10px; vertical-align: middle;"><span style="<?php echo esc_attr($status_style); ?>"><?php echo esc_html($status_label); ?></span></td>
                                        <td style="padding: 10px; vertical-align: middle;">
                                            <input type="checkbox" class="corrarfo_inst_free_shipping_enabled" name="free_shipping_enabled_<?php echo absint($instance_id); ?>" value="yes" <?php checked($inst_enabled, 'yes'); ?> />
                                        </td>
                                        <td style="padding: 10px; vertical-align: middle;">
                                            <input type="text" style="width: 120px;" class="corrarfo_inst_free_shipping_amount" name="free_shipping_amount_<?php echo absint($instance_id); ?>" value="<?php echo esc_attr($inst_amount); ?>" placeholder="0" />
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
    <?php
}

/**
 * Handles the saving of the free shipping configuration.
 */
add_action('admin_post_corrarfo_free_shipping_save', 'corrarfo_save_free_shipping_settings');

function corrarfo_save_free_shipping_settings() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    check_admin_referer('corrarfo_free_shipping_verify');

    require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-free-shipping-hook.php';

    $new_enabled    = isset($_POST['corrarfo_free_shipping_enabled']) ? 'yes' : 'no';
    $new_amount_raw = isset($_POST['corrarfo_free_shipping_amount']) ? sanitize_text_field(wp_unslash($_POST['corrarfo_free_shipping_amount'])) : '';
    $new_amount     = floatval(str_replace(',', '.', $new_amount_raw));

    $new_mode = isset($_POST['corrarfo_free_shipping_mode']) ? sanitize_key($_POST['corrarfo_free_shipping_mode']) : 'global';
    if (!in_array($new_mode, ['global', 'individual'], true)) {
        $new_mode = 'global';
    }

    if ( $new_amount >= 0 ) {
        corrarfo_update_free_shipping_option('enabled', $new_enabled);
        corrarfo_update_free_shipping_option('amount', $new_amount);
        corrarfo_update_free_shipping_option('mode', $new_mode);
    }

    // Save individual settings
    $instances = corrarfo_get_active_shipping_instances();
    $individual_rules = [];

    foreach ($instances as $instance) {
        $instance_id = $instance->instance_id;
        $enabled_key = 'free_shipping_enabled_' . $instance_id;
        $amount_key  = 'free_shipping_amount_' . $instance_id;

        $inst_enabled = isset($_POST[$enabled_key]) ? 'yes' : 'no';
        $inst_amount_raw = isset($_POST[$amount_key]) ? sanitize_text_field(wp_unslash($_POST[$amount_key])) : '';
        
        $inst_amount = 0.0;
        if ($inst_amount_raw !== '') {
            $inst_amount = floatval(str_replace(',', '.', $inst_amount_raw));
            if ($inst_amount < 0) {
                $inst_amount = 0.0;
            }
        }

        $individual_rules[$instance_id] = [
            'enabled' => $inst_enabled,
            'amount' => $inst_amount
        ];
    }

    corrarfo_update_free_shipping_option('individual_rules', $individual_rules);

    // Clear shipping transients to force recalculation
    if (class_exists('WC_Cache_Helper')) {
        WC_Cache_Helper::get_transient_version('shipping', true);
    }

    wp_safe_redirect(admin_url('admin.php?page=corrarfo_shipping_adjustments&settings-updated=true'));
    exit;
}

/**
 * Enqueue admin JS for the free shipping settings screen.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Read-only reading of $_GET (nonce required).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    $match = ( $page === 'corrarfo_shipping_adjustments' );

    if ( ! $match && function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        if ( $screen && ( strpos( $screen->id, 'corrarfo_shipping_adjustments' ) !== false || strpos( $screen->id, 'corrarfo_shipping' ) !== false ) ) {
            $match = true;
        }
    }

    if ( ! $match ) {
        return;
    }

    $base_url = CORRARFO_PLUGIN_URL;
    $ver      = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_script(
        'ca-admin-free-shipping',
        $base_url . 'js/admin-free-shipping.js',
        [],
        $ver,
        true
    );
}, 20);
