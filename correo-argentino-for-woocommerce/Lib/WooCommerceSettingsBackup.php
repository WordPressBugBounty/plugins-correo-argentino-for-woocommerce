<?php

namespace Corrarfo\Lib;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backs up and restores WooCommerce shipping options modified by the plugin lifecycle.
 */
class WooCommerceSettingsBackup
{
    /**
     * Stores current WC values once (pre-install snapshot). Never overwrites an existing backup.
     */
    public static function backupIfNeeded(): void
    {
        if (false === get_option(CORRARFO_BACKUP_WC_SHIP_TO_DESTINATION, false)) {
            add_option(
                CORRARFO_BACKUP_WC_SHIP_TO_DESTINATION,
                get_option(CORRARFO_WC_SHIPPING_TO_DESTINATION, 'billing')
            );
        }

        if (false === get_option(CORRARFO_BACKUP_WC_SHIPPING_DEBUG_MODE, false)) {
            add_option(
                CORRARFO_BACKUP_WC_SHIPPING_DEBUG_MODE,
                get_option(CORRARFO_WC_SHIPPING_DEBUG_MODE, 'no')
            );
        }
    }

    /**
     * Applies plugin-required WooCommerce shipping settings while active.
     */
    public static function applyPluginValues(): void
    {
        update_option(CORRARFO_WC_SHIPPING_TO_DESTINATION, 'shipping');
    }

    /**
     * Restores WooCommerce options from the pre-install snapshot when available.
     */
    public static function restore(): void
    {
        $ship_to = get_option(CORRARFO_BACKUP_WC_SHIP_TO_DESTINATION, false);
        if (false !== $ship_to) {
            update_option(CORRARFO_WC_SHIPPING_TO_DESTINATION, $ship_to);
        }

        $debug_mode = get_option(CORRARFO_BACKUP_WC_SHIPPING_DEBUG_MODE, false);
        if (false !== $debug_mode) {
            update_option(CORRARFO_WC_SHIPPING_DEBUG_MODE, $debug_mode);
        }
    }

    /**
     * Removes backup options after uninstall restoration.
     */
    public static function deleteBackups(): void
    {
        delete_option(CORRARFO_BACKUP_WC_SHIP_TO_DESTINATION);
        delete_option(CORRARFO_BACKUP_WC_SHIPPING_DEBUG_MODE);
    }
}
