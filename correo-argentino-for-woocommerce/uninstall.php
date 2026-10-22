<?php
/**
 * Complete uninstallation of the Correo Argentino plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

if (!defined('CORRARFO_PLUGIN_DIR')) {
	define('CORRARFO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once CORRARFO_PLUGIN_DIR . 'Lib/Constants.php';
require_once CORRARFO_PLUGIN_DIR . 'Lib/ServiceOptions.php';
require_once CORRARFO_PLUGIN_DIR . 'Lib/WooCommerceSettingsBackup.php';

\Corrarfo\Lib\WooCommerceSettingsBackup::restore();
\Corrarfo\Lib\WooCommerceSettingsBackup::deleteBackups();

$corrarfo_remove = get_option(CORRARFO_OPTION_REMOVE_ON_UNINSTALL);
if ($corrarfo_remove !== 'yes') {
	return;
}

/**
 * 1) Eliminar opciones registradas del plugin usando la API.
 */
delete_option(CORRARFO_OPTION_REMOVE_ON_UNINSTALL);
\Corrarfo\Lib\ServiceOptions::deleteAllScopedOptions();
delete_option('corrarfo_service_options_migration_version');
delete_option('corrarfo_service_options_migrated');
delete_option('corrarfo_free_shipping_mode');
delete_option('corrarfo_free_shipping_individual_rules');

/**
 * 2) Pattern-specific cleaning (no API for LIKE):
 *    - options: woocommerce_correoargentino_shipping_method_*
 *    - postmeta: _correoargentino_*
 *
 *    $wpdb->prepare + esc_like is used and it is documented for PHPCS that this
 *    It occurs in the context of uninstallation and there is no alternative in the API.
 */
global $wpdb;

// Delete shipping method options from the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s",
		$wpdb->esc_like('woocommerce_correoargentino_shipping_method_') . '%'
	)
);

// Delete order metadata from the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$wpdb->postmeta}` WHERE meta_key LIKE %s",
		$wpdb->esc_like('_correoargentino_') . '%'
	)
);

// 3) Plugin transients/caches.
delete_transient('corrarfo_cached_services');
delete_transient('corrarfo_last_branch_fetch');

