<?php
/**
 * class.BackendAbstract.php 
 * 
 * @author      Sandro Lucifora
 * @copyright   (c) 2020, Openstream Internet Solutions
 * @link        https://www.openstream.ch/
 * @package     WooCommerceRunMyAccounts
 * @since       1.0  
 */

if ( !defined('ABSPATH' ) ) exit;

if ( !class_exists('WC_RMA_BACKEND_ABSTRACT') ) {

    abstract class WC_RMA_BACKEND_ABSTRACT {

        const VERSION = '1.2.0';
        const DB_VERSION = '1.1.0';

	    private static function _table_log() {
		    global $wpdb;
		    return $wpdb->prefix . WC_RMA_LOG_TABLE;
	    }

        public function create() {

            /**
             * Create Custom Table
             * https://codex.wordpress.org/Creating_Tables_with_Plugins
             */

	        global $wpdb;

	        if ($wpdb->get_var("SHOW TABLES LIKE '".self::_table_log()."'") != self::_table_log()) {

		        $charset_collate = $wpdb->get_charset_collate();

		        $sql = 'CREATE TABLE ' . self::_table_log() . ' (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    time datetime DEFAULT "0000-00-00 00:00:00" NOT NULL,
                    status text NOT NULL,
                    section text NOT NULL, 
                    section_id text NOT NULL,
                    mode text NOT NULL,
                    message text NOT NULL, 
                    UNIQUE KEY id (id) ) ' . $charset_collate . ';';

		        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		        dbDelta($sql);

	        }

        }

        /**
         * Update Custom Tables
         * is called in class.Backend.php with plugins_loaded()
         */
        public function update() {
            /**
             * get_option() WP Since: 1.5.0
             * https://codex.wordpress.org/Function_Reference/get_option
             */
            if ( self::DB_VERSION > get_option( 'wc_rma_db_version' ) ) { // update option if value is different

                // database update if necessary
                /**
                 * update_option() WP Since: 1.0.0
                 * https://codex.wordpress.org/Function_Reference/update_option
                 */
                update_option("wc_rma_db_version", self::DB_VERSION);

            }

            if ( self::VERSION > get_option( 'wc_rma_version' ) ) { // update option if value is different

                update_option("wc_rma_version", self::VERSION);

            }
        }

	    public function delete() {

		    $settings = get_option('wc_rma_settings'); // get settings

		    if ( 'yes' == $settings['rma-delete-settings'] ) {
			    global $wpdb;

			    // drop table
			    $wpdb->query('DROP TABLE IF EXISTS ' . self::_table_log() . ';');

			    // clean all option
			    delete_option('wc_rma_db_version');
			    delete_option('wc_rma_version');
			    delete_option('wc_rma_settings');
		    }
	    }

        public function init_options() {

            /**
             * add_option() WP Since: 1.0.0
             * https://codex.wordpress.org/Function_Reference/add_option
             */
            add_option('wc_rma_version', self::VERSION);
            add_option('wc_rma_db_version', self::DB_VERSION);
        }

        public function init_hooks() {

            // fire if woocommerce is enabled
            if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		        // Add RMA fields to user profile
		        add_filter('woocommerce_customer_meta_fields', array( $this, 'usermeta_profile_fields' ), 10, 1 );
            }

            // if WooCommerce Germanized is not active add our own custom meta field title
            if ( ! defined( 'WC_GERMANIZED_VERSION' ) ) {

                add_action( 'edit_user_profile', array( $this, 'usermeta_form_field_title' ) ); // add the field to user's own profile editing screen
                add_action( 'show_user_profile', array( $this, 'usermeta_form_field_title' ) ); // add the field to user profile editing screen
                add_action( 'personal_options_update', array( $this, 'usermeta_form_field_update' ) ); // add the save action to user's own profile editing screen update
                add_action( 'edit_user_profile_update', array( $this, 'usermeta_form_field_update' ) ); // add the save action to user profile editing screen update

            }

        }

        public function init_settings() {

            /**
             * register_setting() WP Since: 2.7.0
             * https://codex.wordpress.org/Function_Reference/register_setting
             */
            register_setting(
                    "wc_rma_settings_group",
                    "wc_rma_settings",
                    array( $this, 'save_option' ) // save options
            );

        }

        /**
         * Save settings options
         *
         * @param $input
         * @return boolean
         */
        public function save_option( $input ) {

            $return = $input;

            if ( !empty( $_POST ) &&
                 check_admin_referer('woocommerce-rma-nonce-action', 'woocommerce-rma-nonce' ) ) {

                /**
                 * https://codex.wordpress.org/Function_Reference/current_user_can
                 * https://codex.wordpress.org/Roles_and_Capabilities
                 * since 2.0.0
                 */
                if ( !current_user_can('edit_posts') &&
                     !current_user_can('edit_pages'))
                    $return = false;

                return $return;
            }

            return false;
        }

	    /**
	     * Add custom fields to user profile
	     *
	     * @param $fields
	     * @return mixed
	     */
	    public function usermeta_profile_fields( $fields ) {

            // if WooCommerce Germanized is not active add our own billing title
            if ( ! defined( 'WC_GERMANIZED_VERSION' ) ) {
                $fields['billing']['fields']['billing_title'] = array(
                    'label'       => __('Title', 'woocommerce-rma'),
                    'type'        => 'select',
                    'options'     => apply_filters( 'woocommerce_rma_title_options',
                        array(
                            1 => __('Mr.', 'woocommerce-rma'),
                            2 => __('Ms.', 'woocommerce-rma')
                        )
                    )
                );
            }

		    $fields[ 'rma' ][ 'title' ] = __( 'Settings Run my Accounts', 'woocommerce-rma' );

		    if ( class_exists('WC_RMA_API') ) {

		        $WC_RMA_API = new WC_RMA_API();
                $options = $WC_RMA_API->get_customers();

            }

		    if( !$options ) {

			    $fields[ 'rma' ][ 'fields' ][ 'rma_customer' ] = array(
				    'label'       => __( 'Customer', 'woocommerce-rma' ),
				    'type'		  => 'select',
				    'options'	  => array('' => __( 'Error while connecting to RMA. Please check your settings.', 'woocommerce-rma' )),
				    'description' => __( 'Select the corresponding RMA customer for this account.', 'woocommerce-rma' )
			    );

			    return $fields;
		    }

		    $options = array('' => __( 'Select customer...', 'woocommerce-rma' )) + $options;

		    $fields[ 'rma' ][ 'fields' ][ 'rma_customer' ] = array(
			    'label'       => __( 'Customer', 'woocommerce-rma' ),
			    'type'		  => 'select',
			    'options'	  => $options,
			    'description' => __( 'Select the corresponding RMA customer for this account.', 'woocommerce-rma' )
		    );

		    if ( !empty( $WC_RMA_API )) unset( $WC_RMA_API );

		    $fields[ 'rma' ][ 'fields' ][ 'rma_billing_account' ] = array(
			    'label'       => __( 'Receivables Account', 'woocommerce-rma' ),
			    'type'		  => 'input',
			    'description' => __( 'The receivables account has to be available in RMA. Leave it blank to use default value 1100.', 'woocommerce-rma' )
		    );

		    $fields[ 'rma' ][ 'fields' ][ 'rma_payment_period' ] = array(
			    'label'       => __( 'Payment Period', 'woocommerce-rma' ),
			    'type'		  => 'input',
			    'description' => __( 'How many days has this customer to pay your invoice?', 'woocommerce-rma' )
		    );

		    return $fields;
	    }

        /**
         * Save custom user meta field.
         *
         * @param $user_id int the ID of the current user.
         * @return bool Meta ID if the key didn't exist, true on successful update, false on failure.
         */
        public function usermeta_form_field_update( $user_id ) {
            // check that the current user have the capability to edit the $user_id
            if (!current_user_can('edit_user', $user_id))
                return false;

            // create/update user meta for the $user_id
            if ( isset( $_POST['billing_title'] ) ) {
                return update_user_meta( $user_id, 'billing_title', absint( $_POST['billing_title'] ) );
            }

            return false;

        }

    }

}
