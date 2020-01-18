<?php
/**
 * class.Frontend.php 
 * 
 * @author      Sandro Lucifora
 * @copyright   (c) 2020, Openstream Internet Solutions
 * @link        https://www.openstream.ch/
 * @package     WooCommerceRunMyAccounts
 * @since       1.0
 */

if ( !defined('ABSPATH' ) ) exit;

if ( ! class_exists('WC_RMA_FRONTEND' ) ) {

    class WC_RMA_FRONTEND {

        private $locale = '';

        /**
         *  Construct
         */
        public function __construct() {

            /**
             * add_action() WP Since: 1.2.0
             * https://developer.wordpress.org/reference/functions/add_action/
             */
            add_action( 'init', array( $this, 'init') );
            add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

            if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

                // fire when new customer was saved on checkout page
                add_action('woocommerce_checkout_update_user_meta', array( $this, 'create_rma_customer_on_registration'), 10, 1 );
                // ToDo: update RMA customer on user data update

                // fire when a new order comes in ro create new invoice in RMA
                add_action('woocommerce_checkout_order_processed', array( $this, 'create_rma_invoice_at_new_order'), 10, 1 );

                // add title field to woocommerce billing address only, if WooCommerce Germanized is not activ
                if ( ! defined( 'WC_GERMANIZED_VERSION' ) ) {
                    add_filter('woocommerce_billing_fields', array($this, 'woocommerce_billing_fields'), 10, 1);
                    add_filter('woocommerce_checkout_fields', array($this, 'woocommerce_billing_fields'), 10, 1);
                }

            }

        }

        /**
         * Add custom user meta for WooCommerce
         *
         * @param $fields
         *
         * @return mixed
         */
        public function woocommerce_billing_fields( $fields ) {

            $fields['billing_title'] = array(
                'label'       => __('Title', 'woocommerce-rma'),
                'type'        => 'select',
                'class'       => array ( 'form-row-wide', 'address-field' ),
                'options'     => apply_filters(
                    'woocommerce_rma_title_options',
                    array(
                        1 => __('Mr.', 'woocommerce-rma'),
                        2 => __('Ms.', 'woocommerce-rma')
                    )
                ),
                'required' => true,
                'priority' => 5
            );

            return $fields;

        }

        /**
         * Init
         */
        public function init() {

            $this->init_filters(); // Filter
        }

        /**
         * Filters
         */
        public function init_filters() {

            /**
             * apply_filters() WP Since: 0.71
             * https://developer.wordpress.org/reference/functions/apply_filters/
             */
            $this->locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-rma');
        }

        /**
         * Plugins Loaded
         */
        public function plugins_loaded() {

            $this->load_textdomain();

            // Display the admin notification
	        add_action( 'admin_notices', array( $this, 'admin_notices' ) ) ;
        }

        /**
         * Load Textdomains
         */
        public function load_textdomain() {

            /**
             * load_textdomain() WP Since: 1.5.0
             * https://codex.wordpress.org/Function_Reference/load_textdomain
             */
            load_textdomain('woocommerce-rma', WP_LANG_DIR . "/plugins/woocommerce-rma/woocommerce-rma-$this->locale.mo");

            /**
             * load_plugin_textdomain() WP Since: 1.5.0
             * https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
             */
            load_plugin_textdomain('woocommerce-rma', false, plugin_basename(WC_RMA_PFAD . 'languages/'));
        }

	    /**
	     * Show admin notices
	     */
	    public function admin_notices() {

	        $rma_settings = get_option('wc_rma_settings');
	        $rma_client = ( isset ($rma_settings['rma-live-client']) ? $rma_settings['rma-live-client'] : '');
	        $rma_apikey = ( isset ($rma_settings['rma-live-apikey']) ? $rma_settings['rma-live-apikey'] : '');

	        if( ( !$rma_client || !$rma_apikey ) ) {

		        $html = '<div class="notice notice-error">';
		        $html .= '<p>';
		        $html .= '<b>'.__( 'Warning', 'woocommerce-rma' ).'&nbsp;</b>';
		        $html .= __( 'Please enter your live client and live API key before start using WooCommerce Run my Accounts.', 'woocommerce-rma' );
		        $html .= '</p>';
		        $html .= '</div>';

		        echo $html;

	        }

	        if( ( isset($rma_settings['rma-active']) && $rma_settings['rma-active']=='') || !isset($rma_settings['rma-active'] ))  {

		        $html = '<div class="update-nag">';
		        $html .= __( 'WooCommerce Run my Accounts is not activated. No invoice will be created.', 'woocommerce-rma' );
		        $html .= '</div>';

		        echo $html;

	        }

            // Check if cCURL is installed. Is needed for POST requests to RMA
            if ( !self::is_extension_loaded('curl') ) {

                $html = '<div class="notice notice-error">';
                $html .= '<p>';
                $html .= '<b>'.__( 'Warning', 'woocommerce-rma' ).'&nbsp;</b>';
                $html .= sprintf( esc_html__( '%s is not loaded. This php extension is needed to connect to the Run My Accounts server.', 'woocommerce-rma' ), '<a href="https://www.php.net/manual/en/book.curl.php">curl</a>' );
                $html .= '</p>';
                $html .= '</div>';

                echo $html;

            }


        }

        /**
         * check if a given php extension is loaded/enabled
         *
         * @param $extension_name
         *
         * @return bool
         */
        private function is_extension_loaded($extension_name){

            return extension_loaded($extension_name);

        }

        /**
	     * @param $order_id
	     *
	     * @return bool|string
	     */
	    public function create_rma_invoice_at_new_order( $order_id ) {

		    if (class_exists('WC_RMA_API')) {
		    	$WC_RMA_API = new WC_RMA_API();

			    $result = $WC_RMA_API->create_invoice( $order_id );

                unset( $WC_RMA_API );
		    }

		    return ( !empty( $result) ? $result : '' );

	    }

        /**
         * Create customer in Run My Accounts when user is registered in WooCommerce
         *
         * @param $user_id
         *
         * @return bool|string
         */
        public function create_rma_customer_on_registration( $user_id ) {

            if ( class_exists('WC_RMA_API') ) {

                $WC_RMA_API = new WC_RMA_API();
                $result = $WC_RMA_API->create_customer( $user_id );

                unset( $WC_RMA_API );
            }

            return ( !empty( $result) ? $result : true );

        }
    }

}
