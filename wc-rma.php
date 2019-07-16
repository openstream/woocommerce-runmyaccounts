<?php

// wc-rma.php

/*
  Plugin Name: WooCommerce RMA
  Plugin URI:  https://www.openstream.ch
  Description: This plug-in connect WooCommerce to <a href="https://www.runmyaccounts.ch/">Run My Accounts</a>
  Version:     1.1.1
  Author:      Openstream Internet Solutions
  Author URI:  https://www.openstream.ch
  Text Domain: wc-rma
  Domain Path: /languages/
  License:     Commercial
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) { exit; }

// Set full path
if (!defined('WC_RMA_PFAD')) { define('WC_RMA_PFAD', plugin_dir_path(__FILE__)); }

if (!defined('WC_RMA_LOG_TABLE')) { define('WC_RMA_LOG_TABLE', 'wc_rma_log'); }


// LOAD BACKEND ////////////////////////////////////////////////////////////////

if (is_admin()) {

    // Hier binden wir unsere Backend Klasse ein. 
    require_once WC_RMA_PFAD . 'classes/class.Backend.php';

    // Ist die Klasse vorhanden ? 
    if (class_exists('WC_RMA_BACKEND')) {


        // Backend Klasse instanziieren. 
        $WC_RMA_BACKEND = new WC_RMA_BACKEND();

        register_activation_hook(__FILE__, array('WC_RMA_BACKEND', 'activate'));
        register_deactivation_hook(__FILE__, array('WC_RMA_BACKEND', 'deactivate'));
    }
}

// LOAD FRONTEND ///////////////////////////////////////////////////////////////

// Include frontend class
require_once WC_RMA_PFAD . 'classes/class.Frontend.php';
// Include RMA class
require_once WC_RMA_PFAD . 'classes/class.RmaApi.php';

// Ist die Klasse vorhanden ? 
if (class_exists('WC_RMA_FRONTEND')) {

    // Backend Klasse instanziieren. 
    $WC_RMA_FRONTEND = new WC_RMA_FRONTEND();
}

