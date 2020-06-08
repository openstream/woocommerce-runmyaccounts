<?php

/**
 * rma-wc.php
 *
 * Run my Accounts for WooCommerce
 *
 * @package              RunmyAccountsforWooCommerce
 * @author               Sandro Lucifora
 * @copyright            2020 Openstream Internet Solutions
 * @license              GPL-3.0-or-later
 *
 * Plugin Name:          Run my Accounts for WooCommerce
 * Version:              1.4.0
 * Description:          This plug-in connects WooCommerce to <a href="https://www.runmyaccounts.ch/">Run my Accounts</a>. Create customers and invoices as soon as you get an order in your WooCommerce shop.
 * Requires at least:    4.7
 * Requires PHP:         7.3
 * Author:               Openstream Internet Solutions
 * Author URI:           https://www.openstream.ch
 * Text Domain:          rma-wc
 * Domain Path:          /languages/
 * WC requires at least: 3.2
 * WC tested up to:      4.2.0
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( !defined('ABSPATH' ) ) exit;

// Set full path
if (!defined('RMA_WC_PFAD')) { define('RMA_WC_PFAD', plugin_dir_path(__FILE__)); }

if (!defined('RMA_WC_LOG_TABLE')) { define('RMA_WC_LOG_TABLE', 'rma_wc_log'); }

// LOAD BACKEND ////////////////////////////////////////////////////////////////

if ( is_admin() ) {

    if ( is_file( RMA_WC_PFAD . 'classes/class-backend.php' )) {
        // We include our backend class
        require_once RMA_WC_PFAD . 'classes/class-backend.php';

        // Does the backend class exist?
        if (class_exists('RMA_WC_BACKEND')) {
            // Instantiate backend class
            $RMA_WC_BACKEND = new RMA_WC_BACKEND();

            register_activation_hook(__FILE__, array('RMA_WC_BACKEND', 'activate'));
            register_deactivation_hook(__FILE__, array('RMA_WC_BACKEND', 'deactivate'));
        }
    }

    // We include our settings page class
    if ( is_file( RMA_WC_PFAD . 'classes/class-settings.php' )) {
        require_once RMA_WC_PFAD . 'classes/class-settings.php';

        if ( class_exists('RMA_SETTINGS_PAGE')  ) {

            $my_settings_page = new RMA_SETTINGS_PAGE();

        }
    }
}

// LOAD FRONTEND ///////////////////////////////////////////////////////////////

// We include our frontend class
require_once RMA_WC_PFAD . 'classes/class-frontend.php';
// We include our Run My Accounts class
require_once RMA_WC_PFAD . 'classes/class-rma-api.php';

// Does the frontend class exist?
if (class_exists('RMA_WC_FRONTEND')) {

    // Instantiate backend class
    $RMA_WC_FRONTEND = new RMA_WC_FRONTEND();
}

