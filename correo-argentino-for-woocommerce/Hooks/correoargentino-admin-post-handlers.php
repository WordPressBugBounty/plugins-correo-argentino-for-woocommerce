<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the saving of the automatic sending configuration form.
 */
add_action('admin_post_corrarfo_autosend_settings_save', 'corrarfo_handle_autosend_settings_form');

function corrarfo_handle_autosend_settings_form()
{
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
    } else {
        $nonce = '';
    }

    if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($nonce, 'corrarfo_autosend_settings_verify')) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    $enabled = isset($_POST['corrarfo_auto_send_enabled']) ? '1' : '';
    \Corrarfo\Lib\ServiceOptions::update(CORRARFO_OPTION_AUTOSEND_ENABLED, $enabled);

    if (isset($_POST['corrarfo_auto_send_trigger_status'])) {
        $status = sanitize_text_field(wp_unslash($_POST['corrarfo_auto_send_trigger_status']));
        \Corrarfo\Lib\ServiceOptions::update(CORRARFO_OPTION_AUTOSEND_TRIGGER, $status);
    }

    wp_safe_redirect(admin_url('admin.php?page=corrarfo_behavior_settings&autosend_saved=1'));
    exit;
}

/**
 * Handles the saving of the automatic status change configuration.
 * NOTE: This handler must live here (loaded globally) and NOT inside the settings render file,
 * because admin-post.php does not load page-specific files during form submission.
 */
add_action('admin_post_corrarfo_autostatus_save', 'corrarfo_save_autostatus_settings');

function corrarfo_save_autostatus_settings() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    check_admin_referer('corrarfo_autostatus_settings_verify');

    $corrarfo_enabled = isset($_POST['corrarfo_autostatus_enabled']) ? 'yes' : 'no';
    \Corrarfo\Lib\ServiceOptions::update(CORRARFO_OPTION_AUTOSTATUS_ENABLED, $corrarfo_enabled);

    if (isset($_POST['corrarfo_autostatus_status'])) {
        $corrarfo_status = sanitize_text_field(wp_unslash($_POST['corrarfo_autostatus_status']));
        \Corrarfo\Lib\ServiceOptions::update(CORRARFO_OPTION_AUTOSTATUS_STATUS, $corrarfo_status);
    }

    wp_safe_redirect(admin_url('admin.php?page=corrarfo_behavior_settings&corrarfo_autostatus_saved=1'));
    exit;
}




