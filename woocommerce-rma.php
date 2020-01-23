<?php

/**
 * woocommerce-rma.php
 *
 * WooCommerce Run My Accounts
 *
 * @package              WooCommerceRunMyAccounts
 * @author               Sandro Lucifora
 * @copyright            2020 Openstream Internet Solutions
 * @license              GPL-2.0-or-later
 *
 * Plugin Name:          WooCommerce Run My Accounts
 * Version:              1.3.0
 * Plugin URI:           https://www.openstream.ch
 * Description:          This plug-in connects WooCommerce to <a href="https://www.runmyaccounts.ch/">Run my Accounts</a>. Create customers and invoices as soon as you get an order in your WooCommerce shop.
 * Requires at least:    4.7
 * Requires PHP:         7.2
 * Author:               Openstream Internet Solutions
 * Author URI:           https://www.openstream.ch
 * Text Domain:          woocommerce-rma
 * Domain Path:          /languages/
 * WC requires at least: 3.0
 * WC tested up to:      3.2
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( !defined('ABSPATH' ) ) exit;

// Set full path
if (!defined('WC_RMA_PFAD')) { define('WC_RMA_PFAD', plugin_dir_path(__FILE__)); }

if (!defined('WC_RMA_LOG_TABLE')) { define('WC_RMA_LOG_TABLE', 'wc_rma_log'); }

// LOAD BACKEND ////////////////////////////////////////////////////////////////

if ( is_admin() ) {

    // We include our backend class
    require_once WC_RMA_PFAD . 'classes/class.Backend.php';

    // Does the backend class exist?
    if ( class_exists('WC_RMA_BACKEND')  ) {

        // Instantiate backend class
        $WC_RMA_BACKEND = new WC_RMA_BACKEND();

        register_activation_hook(__FILE__, array('WC_RMA_BACKEND', 'activate'));
        register_deactivation_hook(__FILE__, array('WC_RMA_BACKEND', 'deactivate'));
    }

    // We include our settings page class
    require_once WC_RMA_PFAD . 'classes/class.Settings.php';

    if ( class_exists('SETTINGS_PAGE')  ) {

        $my_settings_page = new SETTINGS_PAGE();

    }
}

// LOAD FRONTEND ///////////////////////////////////////////////////////////////

// We include our frontend class
require_once WC_RMA_PFAD . 'classes/class.Frontend.php';
// We include our Run My Accounts class
require_once WC_RMA_PFAD . 'classes/class.RmaApi.php';

// Does the frontend class exist?
if (class_exists('WC_RMA_FRONTEND')) {

    // Instantiate backend class
    $WC_RMA_FRONTEND = new WC_RMA_FRONTEND();
}

