<?php
/**
 * class.Settings.php
 *
 * @author      Sandro Lucifora
 * @copyright   (c) 2020, Openstream Internet Solutions
 * @link        https://www.openstream.ch/
 * @package     WooCommerceRunMyAccounts
 * @since       1.3
 */

if ( !defined('ABSPATH' ) ) exit;

if ( !class_exists('SETTINGS_PAGE') ) {

    class SETTINGS_PAGE {

        private $admin_url;
        private $option_group_general;
        private $option_group_accounting;
        private $options_general;
        private $options_accounting;
        private $option_page_general;
        private $option_page_accounting;

        public function __construct() {

            $this->admin_url               = 'admin.php?page=woocommerce-rma';
            $this->option_group_general    = 'wc_rma_settings';
            $this->option_group_accounting = 'wc_rma_settings_accounting';
            $this->option_page_general     = 'settings-general';
            $this->option_page_accounting  = 'settings-accounting';

            $this->options_general    = get_option( $this->option_group_general );
            $this->options_accounting = get_option( $this->option_group_accounting );

            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );

        }

        public function add_plugin_page(){
            // This page will be under "WooCommerce"
            add_submenu_page('woocommerce', // $parent_slug
                             'Run my Accounts - Settings', // $page_title
                             __('Run my Accounts', 'woocommerce-rma'), // $menu_title
                             'manage_options', // $capability
                             'woocommerce-rma', // $menu_slug
                             array($this, 'create_admin_page') // $function
            );

            add_action( 'admin_init', array( $this, 'options_init') );

        }

        public function create_admin_page() {

            $active_page = ( ( isset( $_GET[ 'tab' ] ) ) ? $_GET[ 'tab' ] : 'general' ); // set default tab ?>

            <div class="wrap">
                <h1><?php _e('Run my Accounts - Settings', 'woocommerce-rma'); ?></h1>
                <?php settings_errors(); ?>
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo admin_url( $this->admin_url ); ?>" class="nav-tab<?php echo ( 'general' == $active_page ? ' nav-tab-active' : '' ); ?>"><?php esc_html_e('General', 'woocommerce-rma'); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'accounting' ), admin_url( $this->admin_url ) ) ); ?>" class="nav-tab<?php echo ( 'accounting' == $active_page ? ' nav-tab-active' : '' ); ?>"><?php esc_html_e('Accounting', 'woocommerce-rma'); ?></a>
                </h2>

                <form method="post" action="options.php"><?php //   settings_fields( $this->option_group_general );
                    switch ( $active_page ) {
                        case 'accounting':
                            settings_fields( $this->option_group_accounting );
                            do_settings_sections( $this->option_page_accounting );
                            submit_button();
                            break;
                        default:
                            settings_fields( $this->option_group_general );
                            do_settings_sections( $this->option_page_general );
                            submit_button();
                            break;
                    } ?>
                </form>
            </div> <?php
        }

        /**
         * Initialize Options on Settings Page
         */
        public function options_init() {
            register_setting(
                $this->option_group_general, // Option group
                $this->option_group_general, // Option name
                array( $this, 'sanitize' ) // Sanitize
            );

            $this->options_general_api();

            $this->options_general_billing();

            $this->options_general_customer();

            $this->options_general_misc();

            register_setting(
                $this->option_group_accounting, // Option group
                $this->option_group_accounting, // Option name
                array( $this, 'sanitize' ) // Sanitize
            );

            $this->options_accounting_gateways();

        }

        /**
         * Page General, Section API
         */
        public function options_general_api() {

            $section = 'general_settings_api';

            add_settings_section(
                $section, // ID
                esc_html__('API', 'woocommerce-rma'),
                array( $this, 'section_info_api' ), // Callback
                $this->option_page_general // Page
            );

            $id = 'rma-active';
            add_settings_field(
                $id,
                esc_html__('Activate Function', 'woocommerce-rma'),
                array( $this, 'option_input_checkbox_cb'), // general callback for checkbox
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Do not activate the plugin before you have set up all data.', 'woocommerce-rma' )
                )
            );

            $id = 'rma-mode';
            add_settings_field(
                $id,
                esc_html__('Operation Mode', 'woocommerce-rma'),
                array( $this, 'rma_mode_cb'), // individual callback
                $this->option_page_general,
                $section,
                array( 'option_group' => $this->option_group_general, 'id' => $id )
            );

            $id = 'rma-live-client';
            add_settings_field(
                $id,
                esc_html__('Live Client', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'), // general call back for input text
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : ''
                )
            );

            $id = 'rma-live-apikey';
            add_settings_field(
                $id,
                esc_html__('Live API key', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : ''
                )
            );

            $id = 'rma-test-client';
            add_settings_field(
                $id,
                esc_html__('Test Client', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'), // general call back for input text
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : ''
                )
            );

            $id = 'rma-test-apikey';
            add_settings_field(
                $id,
                esc_html__('Test API key', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : ''
                )
            );
        }

        public function print_section_info(){
            //your code...
        }

        /**
         * Page General, Section Billing
         */
        public function options_general_billing() {

            $section = 'general_settings_billing';

            add_settings_section(
                $section, // ID
                esc_html__('Billing', 'woocommerce-rma'),
                array( $this, 'section_info_billing' ), // Callback
                $this->option_page_general // Page
            );

            $id = 'rma-payment-period';
            add_settings_field(
                $id,
                esc_html__('Payment Period in days', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Please set the general payment period. You can set a individual value, for a customer, in the user profile.', 'woocommerce-rma' )
                )
            );

            $id = 'rma-invoice-prefix';
            add_settings_field(
                $id,
                esc_html__('Invoice Prefix', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => 'rma-invoice-prefix',
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Prefix followed by order number will be the invoice number in Run my Accounts.', 'woocommerce-rma' )
                )
            );

            $id = 'rma-digits';
            add_settings_field(
                $id,
                esc_html__('Number of digits', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Set the maximum number of digits for the invoice number (including prefix).', 'woocommerce-rma' )
                )
            );

            $id = 'rma-invoice-description';
            add_settings_field(
                $id,
                esc_html__('Invoice Description in RMA', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Possible variable: [orderdate]', 'woocommerce-rma' )
                )
            );

        }

        /**
         * Page General, Section Customer
         */
        public function options_general_customer() {

            $section = 'general_settings_customer';

            add_settings_section(
                $section, // ID
                esc_html__('Customer', 'woocommerce-rma'),
                array( $this, 'section_info_customer' ), // Callback
                $this->option_page_general // Page
            );

            $id = 'rma-create-customer';
            add_settings_field(
                $id,
                esc_html__('Create New Customer', 'woocommerce-rma'),
                array( $this, 'option_input_checkbox_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Tick this if you want to create a customer as soon as a new user is created in WooCommerce (recommended if customer can register by itself).', 'woocommerce-rma' )
                )
            );

            $id = 'rma-customer-prefix';
            add_settings_field(
                $id,
                esc_html__('Customer Number Prefix', 'woocommerce-rma'),
                array( $this, 'option_input_text_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'description'  => esc_html__('Prefix followed by user id will be  the customer number in Run my Accounts.', 'woocommerce-rma' )
                )
            );

        }

        /**
         * Page General, Section Misc
         */
        public function options_general_misc() {

            $section = 'general_settings_misc';

            add_settings_section(
                $section, // ID
                esc_html__('Misc', 'woocommerce-rma'),
                array( $this, 'section_info_misc' ), // Callback
                $this->option_page_general // Page
            );

            $id = 'rma-loglevel';
            add_settings_field(
                $id,
                esc_html__('Log Level', 'woocommerce-rma'),
                array( $this, 'option_select_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'options'      => array(
                        'error'    => esc_html__('error','woocommerce-rma'),
                        'complete' => esc_html__('complete','woocommerce-rma'),
                    )
                )
            );

            $id = 'rma-delete-settings';
            add_settings_field(
                $id,
                esc_html__('Delete Settings', 'woocommerce-rma'),
                array( $this, 'option_select_cb'),
                $this->option_page_general,
                $section,
                array(
                    'option_group' => $this->option_group_general,
                    'id'           => $id,
                    'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                    'options'      => array(
                        'no'       => esc_html__('no','woocommerce-rma'),
                        'yes'      => esc_html__('yes','woocommerce-rma'),
                    ),
                    'description'  => esc_html__('Remove all plugin data when using the "Delete" link on the plugins screen', 'woocommerce-rma' ),

                )
            );

        }

        public function options_accounting_gateways() {

            $section = 'accounting_settings_payment';

            add_settings_section(
                $section, // ID
                'Payment Provider Accounting Account', // Title
                array( $this, 'section_info_accounting' ), // Callback
                $this->option_page_accounting // Page
            );

            // add  settings fields for all payment gateways

            $available_gateways = WC()->payment_gateways->payment_gateways();

            foreach ( $available_gateways as $gateway_key => $values ) {

                add_settings_field(
                    $gateway_key,
                    $values->title,
                    array( $this, 'option_input_text_cb'),
                    $this->option_page_accounting,
                    $section,
                    array(
                        'option_group' => $this->option_group_accounting,
                        'id'           => $gateway_key,
                        'value'        => isset( $this->options_accounting[ $gateway_key ] ) ? $this->options_accounting[ $gateway_key ] : ''
                    )
                );

            }

        }

        public function section_info_accounting() {
            esc_html_e('You can specify a dedicated receivable account for each payment gateway.', 'woocommerce-rma');
        }


        /**
         * General Input Field Checkbox
         *
         * @param array $args
         */
        public function option_input_checkbox_cb( $args ){

            $option_group = ( isset( $args['option_group'] ) ) ? $args['option_group'] : '';
            $id           = ( isset( $args['id'] ) ) ? $args['id'] : '';
            $checked      = ( isset( $args['value'] ) && !empty( $args['value'] ) ) ? 'checked' : '';
            $description  = ( isset( $args['description'] ) ) ? $args['description'] : '';

            printf(
                '<input type="checkbox" id="%1$s" name="%3$s[%1$s]" value="1" %2$s />',
                $id, $checked, $option_group
            );

            if ( !empty( $description) )
                echo '<p class="description">' . $description . '</p>';

        }

        /**
         * General Input Field Text
         *
         * @param array $args
         */
        public function option_input_text_cb( $args ) {

            $option_group = ( isset( $args['option_group'] ) ) ? $args['option_group'] : '';
            $id           = ( isset( $args['id'] ) ) ? $args['id'] : '';
            $value        = ( isset( $args['value'] ) ) ? $args['value'] : '';
            $description  = ( isset( $args['description'] ) ) ? $args['description'] : '';

            printf(
                '<input type="text" id="%1$s" name="%3$s[%1$s]" value="%2$s" />',
                $id, $value, $option_group
            );

            if ( !empty( $description) )
                echo '<p class="description">' . $description . '</p>';
        }

        /**
         * General Select
         *
         * @param array $args
         */
        public function option_select_cb( $args )
        {
            $option_group = (isset($args['option_group'])) ? $args['option_group'] : '';
            $id           = (isset($args['id'])) ? $args['id'] : '';
            $options      = (isset($args['options'])) ? $args['options'] : array();
            $description  = (isset($args['description'])) ? $args['description'] : '';

            echo '<select name="' . $option_group . '[' . $id . ']">';

            foreach ($options as $value => $text) {
                printf(
                    '<option value="%1$s" %2$s />%3$s</option>',
                    $value,
                    ( isset( $this->options_general[ $id ] ) && $value == $this->options_general[ $id ] ) ? 'selected="selected"' : '',
                    $text
                );
            }

            echo '</select>';

            if ( !empty( $description) )
                echo '<p class="description">' . $description . '</p>';

        }

        /**
         * Individual pulldown
         *
         * @param array $args
         */
        public function rma_mode_cb( $args ) {
            $option_group = ( isset( $args['option_group'] ) ) ? $args['option_group'] : '';
            $id           = ( isset( $args['id'] ) ) ? $args['id'] : '';

            $select_args  = array(
                'option_group' => $option_group,
                'id'           => $id,
                'value'        => isset( $this->options_general[ $id ] ) ? $this->options_general[ $id ] : '',
                'options'      => array(
                    'test' => esc_html__('Test','woocommerce-rma'),
                    'live' => esc_html__('Live','woocommerce-rma'),
                )
            );

            // create select
            self::option_select_cb( $select_args );

            // output connection status
            if (class_exists('WC_RMA_API')) {
                $WC_RMA_API = new WC_RMA_API();
                // Retrieve customers to check connection
                $options = $WC_RMA_API->get_customers();
                if ( ! $options )
                    echo '&nbsp;<span style="color: red; font-weight: bold">' . __('No connection. Please check your settings.', 'woocommerce-rma') . '</span>';
                else
                    echo '&nbsp;<span style="color: green">' . __('Connection successful.', 'woocommerce-rma') . '</span>';
            }
        }

        /**
         * Sanitizes a string from user input
         * Checks for invalid UTF-8, Converts single < characters to entities, Strips all tags, Removes line breaks, tabs, and extra whitespace, Strips octets
         *
         * @param array $input
         *
         * @return array
         */
        public function sanitize( $input )  {

            $new_input = array();

            foreach ( $input as $key => $value ) {

                $new_input[ $key ] = sanitize_text_field( $input[ $key ] );

            }

            return $new_input;
        }

    }

}
