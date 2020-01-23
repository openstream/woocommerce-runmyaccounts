<?php

/**
 * uninstall.php
 *
 * @author      Sandro Lucifora
 * @copyright   (c) 2020, Openstream Internet Solutions
 * @link        https://www.openstream.ch/
 * @package     WooCommerceRunMyAccounts
 * @since       1.0
 */
if ( !defined('ABSPATH' ) ) exit;
/**
 * https://developer.wordpress.org/plugins/the-basics/uninstall-methods/
 */
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

// Does function not exist?
if ( !function_exists('uninstall_woocommerce_rma' ) ) {

    /**
     * Uninstall
     * @return void
     */
    function uninstall_woocommerce_rma() {

        // Check Admin
        if ( is_admin() ) {

            if ( !current_user_can('delete_plugins' ) ) {
                return;
            }

            /**
             * Unregister settings
             * https://codex.wordpress.org/Function_Reference/unregister_setting
             */
            unregister_setting('wc_rma_settings', 'wc_rma_settings', '');
            delete_option('wc_rma_settings');

            delete_option('wc_rma_db_version');
            delete_option('wc_rma_version');
        }
    }

    uninstall_woocommerce_rma();
}