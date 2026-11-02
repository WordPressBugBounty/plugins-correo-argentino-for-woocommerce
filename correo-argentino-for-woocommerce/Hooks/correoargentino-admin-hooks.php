<?php
namespace Corrarfo\Hooks;

use Corrarfo\Lib\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class AdminHooks
{
    public static function init()
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
        add_action('admin_notices', [self::class, 'showRestoredOrdersNotice']);
        add_action('admin_notices', [self::class, 'showDeletedReferenceNotice']);
        add_filter('plugin_row_meta', [self::class, 'addPluginRowMeta'], 10, 2);
        add_filter('admin_footer_text', [self::class, 'customizeAdminFooterText']);
    }

    public static function enqueueAdminAssets($hook)
    {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page !== 'correoargentino-orders') {
            return;
        }

        $base_url = CORRARFO_PLUGIN_URL;

        wp_enqueue_style(
            'ca-admin-actions',
            $base_url . 'css/admin-actions.css',
            [],
            defined('CORRARFO_VERSION') ? CORRARFO_VERSION : '1.0'
        );

        wp_enqueue_script(
            'ca-order-list-actions',
            $base_url . 'js/order-list-actions.js',
            [],
            defined('CORRARFO_VERSION') ? CORRARFO_VERSION : '1.0',
            true
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

        if (
            class_exists('\Corrarfo_Utils')
            && Utils::isConnected()
            && in_array($form, ['user-validate-mi-correo-form', 'login-paq-ar-form'], true)
        ) {
            if (!wp_style_is('ca-admin-actions', 'enqueued')) {
                wp_enqueue_style('ca-admin-actions');
            }
            wp_add_inline_style('ca-admin-actions', '.woocommerce-save-button{display:none!important;}');
        }
    }

    public static function showRestoredOrdersNotice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $restored = isset($_GET['restored']) ? sanitize_text_field(wp_unslash($_GET['restored'])) : '';

        if ($restored === '1') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('All hidden orders have been successfully restored.', 'correo-argentino-for-woocommerce') . '</p>';
            echo '</div>';
        }
    }

    public static function showDeletedReferenceNotice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $deleted = isset($_GET['deleted']) ? sanitize_text_field(wp_unslash($_GET['deleted'])) : '';

        if ($deleted === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('The shipping reference was successfully deleted.', 'correo-argentino-for-woocommerce') .
                '</p></div>';
        }
    }

    /**
     * Add developer attribution to plugin row meta on the plugins screen.
     *
     * @param string[] $plugin_meta An array of the plugin's metadata.
     * @param string   $plugin_file Path to the plugin file relative to plugins directory.
     * @return string[]
     */
    public static function addPluginRowMeta($plugin_meta, $plugin_file)
    {
        if (defined('CORRARFO_PLUGIN_FILE') && $plugin_file === plugin_basename(CORRARFO_PLUGIN_FILE)) {
            foreach ($plugin_meta as $meta) {
                if (is_string($meta) && strpos($meta, 'aiotek.com') !== false) {
                    return $plugin_meta;
                }
            }

            $developer_link = sprintf(
                /* translators: %s: Developer link */
                __('Developed by %s', 'correo-argentino-for-woocommerce'),
                '<a href="https://aiotek.com" target="_blank" rel="noopener noreferrer">Aiotek</a>'
            );

            // Insert immediately after author element (index 1) if present, otherwise append.
            if (isset($plugin_meta[1])) {
                array_splice($plugin_meta, 2, 0, [$developer_link]);
            } else {
                $plugin_meta[] = $developer_link;
            }
        }

        return $plugin_meta;
    }

    /**
     * Append developer attribution to admin footer on Correo Argentino orders screen.
     *
     * @param string $text Default admin footer text.
     * @return string
     */
    public static function customizeAdminFooterText($text)
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        $is_orders_page = ($screen && strpos($screen->id, 'correoargentino-orders') !== false)
            || ($page === 'correoargentino-orders');

        if ($is_orders_page) {
            if (is_string($text) && strpos($text, 'aiotek.com') !== false) {
                return $text;
            }

            $developer_link = sprintf(
                /* translators: %s: Developer link */
                __('Developed by %s', 'correo-argentino-for-woocommerce'),
                '<a href="https://aiotek.com" target="_blank" rel="noopener noreferrer">Aiotek</a>'
            );

            if (!empty($text)) {
                $text .= ' | ' . $developer_link;
            } else {
                $text = $developer_link;
            }
        }

        return $text;
    }
}

// Backward compatibility alias
if (!class_exists('AdminHooks')) {
    class_alias(AdminHooks::class, 'AdminHooks');
}
