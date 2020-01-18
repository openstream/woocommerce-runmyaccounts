<?php
/**
 * class.Backend.php  
 *  
 * @author      Sandro Lucifora
 * @copyright   (c) 2018, Openstream Internet Solutions
 * @link        https://www.openstream.ch/
 * @package     WooCommerceRunMyAccounts
 * @since       1.0  
 */

if ( !defined('ABSPATH' ) ) exit;

require_once 'class.BackendAbstract.php';

if (!class_exists('WC_RMA_BACKEND')) {

    /**
     * Create class and extends it
     */
    class WC_RMA_BACKEND extends WC_RMA_BACKEND_ABSTRACT {

        /**
         * Construct  
         */
        public function __construct() {

            add_action( 'admin_menu', array($this, 'add_menu')); // admin_menu diese action wird benötigt um die Admin Menüs zu registrieren
            add_action( 'admin_init', array($this, 'admin_init')); // admin_init diese action wird benötigt um z.B. option, settings und filter zusetzen
            add_action( 'plugins_loaded', array($this, 'plugins_loaded')); // plugins_loaded diese action wird bei jedem aufruf der Seite ausgeführt
            add_action( 'plugins_loaded', array($this, 'plugins_loaded_settings'), 1); // plugins_loaded diese action begrenzen wir auf ein einmaligen aufruf, der hier beim aktivieren des plugins genutzt wird

        }

        /**
         * Activate - is triggered when calling register_activation_hook(), but we do this in woocommerce-rma.php
         */
        public function activate() {
            /**
             * set_transient() WP Since: 2.8  
             * https://codex.wordpress.org/Function_Reference/set_transient  
             */
            set_transient('woocommerce-rma-page-activated', 1, 30);

        }

        /**
         * Deactivate - is triggered when register_deactivation_hook() is called, but we do this in the woocommerce-rma.php
         */
        public function deactivate() {

        }

	    /**
	     * Uninstall - is triggered when register_uninstall_hook() is called, but we do it already in woocommerce-rma.php
	     */
	    public function uninstall() {

		    $this->delete(); // Delete

	    }

        /**
         * Admin Menus - adds menu in WordPress admin, as submenu in WooCommerce menu
         */
	    public function add_menu() {
		    /**
		     * add_submenu_page() WP Since: 1.5.0
		     * https://developer.wordpress.org/reference/functions/add_submenu_page/
		     */
		    add_submenu_page('woocommerce', // $parent_slug
			    'Run my Accounts - Settings', // $page_title
			    __('Run my Accounts', 'woocommerce-rma'), // $menu_title
			    'manage_options', // $capability
			    'woocommerce-rma-settings', // $menu_slug
			    array($this, 'settings') // $function
		    );
	    }

        /**
         * plugin settings page, is called by add_menu()
         */
        public function settings() {

            require_once WC_RMA_PFAD . 'html/settings.php';
        }

        /**
         * Admin Init - we initiate everything we need
         */
        public function admin_init() {

            $this->init_options();
            $this->init_settings();
            $this->init_hooks();
        }

        /**
         * Plugins Loaded
         */
        public function plugins_loaded() {

            $this->create();
            $this->update();
        }

        /**
         * Plugins Loaded Once on Activate -- This function is only called once when the plugin is activated.
         * this function is called in the constructor, with add_action()
         */
        public function plugins_loaded_settings() {

            /**
             * We check whether there is transient. If not, we will do it here
             * get_transient() WP Since: 2.8 
             * https://codex.wordpress.org/Function_Reference/get_transient 
             */
            if (!get_transient('woocommerce-rma-page-activated')) {
                return;
            }

            /**
             * We delete the transient because we do not want the welcome page to be called again and again
             * delete_transient() WP Since: 2.8 
             * https://codex.wordpress.org/Function_Reference/delete_transient 
             */
            delete_transient('woocommerce-rma-page-activated');

            /**
             * here we redirect to the settings page
             * wp_redirect() WP Since: 1.5.1 
             * https://codex.wordpress.org/Function_Reference/wp_redirect 
             */
            wp_redirect(
                    /**
                     * admin_url() WP Since:2.6.0 
                     * https://codex.wordpress.org/Function_Reference/admin_url 
                     */
                    admin_url('admin.php?page=woocommerce-rma-settings')
            );

            exit;
        }

    }

} 