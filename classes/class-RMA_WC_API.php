<?php
if ( !defined('ABSPATH') ) exit;

if ( !class_exists('RMA_WC_API') ) {

	class RMA_WC_API {

		/**
		 *  Construct
		 */
		public function __construct() {

		    // define constants only if they are not defined yet
		    if ( !defined( 'RMA_MANDANT' ) )
		        self::define_constants();

		}

        /**
         * define default constants
         */
		private function define_constants() {
            // read rma settings
            $settings = get_option('wc_rma_settings');

            // check if operation mode is set and is live
            if( isset( $settings['rma-mode'] ) && 'live' == $settings['rma-mode'] ) {

                // define constants with live values
                DEFINE( 'RMA_MANDANT', ( isset( $settings['rma-live-client'] ) ? $settings['rma-live-client'] : '' ) );
                DEFINE( 'RMA_APIKEY', ( isset( $settings['rma-live-apikey'] ) ? $settings['rma-live-apikey'] : '' ) );
                DEFINE( 'RMA_CALLERSANDBOX', FALSE );

            }
            else {

                // set default operation mode to test
                DEFINE( 'RMA_MANDANT', ( isset( $settings['rma-test-client'] ) ? $settings['rma-test-client'] : '' ) );
                DEFINE( 'RMA_APIKEY', ( isset( $settings['rma-test-apikey'] ) ? $settings['rma-test-apikey'] : '' ) );
                DEFINE( 'RMA_CALLERSANDBOX', TRUE );

            }

            DEFINE( 'RMA_INVOICE_DESCRIPTION', ( isset( $settings['rma-invoice-description'] ) ? $settings['rma-invoice-description'] : '' ) );
            DEFINE( 'RMA_GLOBAL_PAYMENT_PERIOD', ( isset( $settings['rma-payment-period'] ) ? $settings['rma-payment-period'] : '0' ) ); // default value 0 days
            DEFINE( 'RMA_INVOICE_PREFIX', ( isset( $settings['rma-invoice-prefix'] ) ? $settings['rma-invoice-prefix'] : '' ) );
            DEFINE( 'RMA_INVOICE_DIGITS', ( isset( $settings['rma-digits'] ) ? $settings['rma-invoice-description'] : '' ) );

            // if rma-loglevel ist not set, LOGLEVEL is set to error by default
            if( isset( $settings['rma-loglevel'] ) ) {
                if( 'error' == $settings['rma-loglevel']  || empty( $settings['rma-loglevel'] ) ) {
                    DEFINE( 'LOGLEVEL' , 'error' );
                }
                elseif ( $settings['rma-loglevel'] == 'complete' ) {
                    DEFINE( 'LOGLEVEL' , 'complete' );
                }
            } else {
                DEFINE( 'LOGLEVEL' , 'error' );
            }

            // if rma-log-send-email ist not set, SENDLOGEMAIL is set to false by default
            if( isset ( $settings['rma-log-send-email'] ) &&
                'yes' == $settings['rma-log-send-email'] ) {
                DEFINE( 'SENDLOGEMAIL' , true );

                // who will get email on error
                DEFINE( 'LOGEMAIL', ( !empty( $settings['rma-log-email'] ) ? $settings['rma-log-email'] : get_option( 'admin_email' ) ) );

            } else {
                DEFINE( 'SENDLOGEMAIL' , false );
            }

        }

		/**
		 * Set Caller URL live oder sandbox
         *
		 * @return string
		 */
		public static function get_caller_url(): string
        {
			// Set caller URL
			if( RMA_CALLERSANDBOX ) { // Caller URL for sandbox
				$url = 'https://service-swint.runmyaccounts.com/api/latest/clients/'; // End with / !
			}
			else { // Caller URL set for Live page
				$url = 'https://service.runmyaccounts.com/api/latest/clients/'; // End with / !
			}

			return $url;
		}

		/**
		 * Read customer list from RMA
         *
		 * @return mixed
		 */
		public function get_customers() {

		    if( !RMA_MANDANT || !RMA_APIKEY ) {

                $log_values = array(
                    'status' => 'error',
                    'section_id' => '',
                    'section' => esc_html_x('Get Customer', 'Log Section', 'rma-wc'),
                    'mode' => self::rma_mode(),
                    'message' => esc_html__('Missing API data', 'rma-wc') );

                self::write_log($log_values);

                return false;

            }

			$url       = self::get_caller_url() . RMA_MANDANT . '/customers?api_key=' . RMA_APIKEY;
            $response  = wp_remote_get( $url );

            // Check response code
			if ( 200 <> wp_remote_retrieve_response_code( $response ) ){

			    $message = esc_html__( 'Response Code', 'rma-wc') . ' '. wp_remote_retrieve_response_code( $response );
                $message .= ' '. wp_remote_retrieve_response_message( $response );

                $response = (array) $response['http_response'];

                foreach ( $response as $object ) {
                    $message .= ' ' . $object->url;
                    break;
                }

                $log_values = array(
                    'status' => 'error',
                    'section_id' => '',
                    'section' => esc_html_x('Get Customer', 'Log Section', 'rma-wc'),
                    'mode' => self::rma_mode(),
                    'message' => $message );

                self::write_log($log_values);

                return false;

			}
			else {

                libxml_use_internal_errors( true );

                $body = wp_remote_retrieve_body( $response );
                $xml  = simplexml_load_string( $body );

				if ( !$xml ) {
					// ToDO: Add this information to error log
					foreach( libxml_get_errors() as $error ) {
						echo "\t", $error->message;
					}

					return false;

				}
				else {
					// Parse response
					$array = json_decode( json_encode( (array)$xml ), TRUE);

					// Transform into array
					foreach ( $array as $value ) {

						foreach ($value as $key => $customer ) {
                            $number = $customer['customernumber'];

                            if ( is_array( $customer['name'] ) ) {

                                if ( is_array( $customer['firstname'] ) ||
                                     is_array( $customer['lastname'] )) {
                                    $name = '';
                                }
                                else {
                                    $name = $customer['firstname'] . ' ' . $customer['lastname'];
                                }

                            }
                            else {
                                $name   = $customer['name'];
                            }

                            $customers[ $number ] = $name . ' ( ' . $number . ' )';
						}

					}

					return ( !empty( $customers ) ? $customers : false );
				}
			}
		}

        /**
         * Read parts list from RMA
         *
         * @return mixed
         *
         * @since 1.5.0
         */
        public function get_parts() {

            if( !RMA_MANDANT || !RMA_APIKEY ) {

                $log_values = array(
                    'status' => 'error',
                    'section_id' => '',
                    'section' => esc_html_x('Get Parts', 'Log Section', 'rma-wc'),
                    'mode' => self::rma_mode(),
                    'message' => esc_html__('Missing API data', 'rma-wc') );

                self::write_log($log_values);

                return false;

            }

            $url       = self::get_caller_url() . RMA_MANDANT . '/parts?api_key=' . RMA_APIKEY;

            $response  = wp_remote_get( $url );

            // Check response code
            if ( 200 <> wp_remote_retrieve_response_code( $response ) ){

                $message = esc_html__( 'Response Code', 'rma-wc') . ' '. wp_remote_retrieve_response_code( $response );
                $message .= ' '. wp_remote_retrieve_response_message( $response );

                $response = (array) $response['http_response'];

                foreach ( $response as $object ) {
                    $message .= ' ' . $object->url;
                    break;
                }

                $log_values = array(
                    'status' => 'error',
                    'section_id' => '',
                    'section' => esc_html_x('Get Parts', 'Log Section', 'rma-wc'),
                    'mode' => self::rma_mode(),
                    'message' => $message );

                self::write_log($log_values);

                return false;

            }
            else {

                libxml_use_internal_errors( true );

                $body = wp_remote_retrieve_body( $response );
                $xml  = simplexml_load_string( $body );

                if ( !$xml ) {
                    // ToDO: Add this information to error log
                    foreach( libxml_get_errors() as $error ) {
                        echo "\t", $error->message;
                    }

                    return false;

                }
                else {
                    // Parse response
                    $array = json_decode( json_encode( (array)$xml ), TRUE);

                    $sku_list = array();

                    foreach ( $array['part'] as $part ) {

                        $sku_list[ $part['partnumber'] ] = $part['partnumber'];

                    }

                    return ( !empty( $sku_list ) ? $sku_list : false );
                }
            }
        }

        /**
		 * Collect data for invoice
		 *
		 * @param $order_id
         *
		 * @return array
		 */
		private function get_invoice_values( $order_id ): array
        {

            $settings     = get_option( 'wc_rma_settings' );
            $fallback_sku = $settings[ 'rma-product-fallback_id' ];
            unset( $settings );

            if ( !empty( $fallback_sku ) )
                $rma_part_numbers = self::get_parts();

			list( $order_details, $order_details_products ) = self::get_wc_order_details( $order_id );

            $data = array(
                'invoice' => array(
                    'invnumber'      => RMA_INVOICE_PREFIX . str_pad( $order_id, max(intval(RMA_INVOICE_DIGITS) - strlen(RMA_INVOICE_PREFIX ), 0 ), '0', STR_PAD_LEFT ),
                    'ordnumber'      => $order_id,
                    'status'         => 'OPEN',
                    'currency'       => $order_details['currency'],
                    'ar_accno'       => $order_details['ar_accno'],
                    'transdate'      => date( DateTime::RFC3339, time() ),
                    'duedate'        => $order_details['duedate'], //date( DateTime::RFC3339, time() ),
                    'description'    => str_replace('[orderdate]',$order_details['orderdate'], RMA_INVOICE_DESCRIPTION),
                    'notes'          => '',
                    'intnotes'       => $order_details['notes'],
                    'taxincluded'    => $order_details['taxincluded'],
                    'dcn'            => '',
                    'customernumber' => $order_details['customernumber']
                ),
                'part' => array()
            );

            // Add parts
			if ( count( $order_details_products ) > 0 ) :

				foreach ( $order_details_products as $partnumber => $part ) :

                    // check if fallback sku exist and part number does not exist in list of RMA part numbers
                    if( !empty( $fallback_sku ) &&
                        !array_key_exists( $partnumber, $rma_part_numbers ) )
                        $partnumber = $fallback_sku;

					$data['part'][] = array (
						'partnumber'   => $partnumber,
						'description'  => $part['name'],
						'unit'         => '',
						'quantity'     => $part['quantity'],
						'sellprice'    => $part['price'],
						'discount'     => '0.0',
						'itemnote'     => '',
						'price_update' => '',
					);
				endforeach;

			endif;

            return $data;
		}

        /**
         * Prepare data of a customer by user id for sending to Run my Accounts
         *
         * @param $user_id
         *
         * @return array
         * @throws Exception
         */
		private function get_customer_values_by_user_id( $user_id ) :array {

            $settings        = get_option( 'wc_rma_settings' );
            $customer_prefix = isset( $settings[ 'rma-customer-prefix' ] ) ? $settings[ 'rma-customer-prefix' ] : '';

            $customer        = new WC_Customer( $user_id );

            $is_company      = !empty( $customer->get_billing_company() ) ? true : false;
            $billing_account = get_user_meta( $user_id, 'rma_billing_account', true );

            return array(
				'customernumber'    => $customer_prefix . $user_id,
				'name'              => ( $is_company ? $customer->get_billing_company() : $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() ),
				'created'           => date('Y-m-d') . 'T00:00:00+01:00',
				'salutation'        => ( 1 == get_user_meta( $user_id, 'billing_title', true ) ? __('Mr.', 'rma-wc') : __('Ms.', 'rma-wc') ),
				'firstname'         => $customer->get_billing_first_name(),
				'lastname'          => $customer->get_billing_last_name(),
				'address1'          => $customer->get_billing_address_1(),
				'address2'          => $customer->get_billing_address_2(),
				'zipcode'           => $customer->get_billing_postcode(),
				'city'              => $customer->get_billing_city(),
				'state'             => $customer->get_billing_state(),
				'country'           => WC()->countries->countries[ $customer->get_billing_country() ],
				'phone'             => $customer->get_billing_phone(),
				'fax'               => '',
				'mobile'            => '',
				'email'             => $customer->get_billing_email(),
				'cc'                => '',
				'bcc'               => '',
				'language_code'     => '',
				'remittancevoucher' => 'false',
				'arap_accno'        => !empty ( $billing_account ) ? $billing_account : '', // Default accounts receivable account number - 1100
                'payment_accno'     => '', // Default payment account number	1020
				'notes'             => '',
				'terms'             => '0',
				'typeofcontact'     => ( $is_company ? 'company' : 'person' ),
				'gender'            => ( 1 == get_user_meta( $user_id, 'billing_title', true ) ? 'M' : 'F' ),
			);

		}

        /**
         * Prepare data of a customer by order id for sending to Run my Accounts
         *
         * @param $order_id
         *
         * @return array
         * @throws Exception
         */
        private function get_customer_values_by_order_id( $order_id ) : array {

            $settings        = get_option( 'wc_rma_settings' );
            $customer_prefix = isset( $settings[ 'rma-guest-customer-prefix' ] ) ? $settings[ 'rma-guest-customer-prefix' ] : '';
            unset( $settings );

            $order           = new WC_Order( $order_id );

            $is_company      = !empty( $order->get_billing_company() );

            return array(
                'customernumber'    => $customer_prefix . $order_id,
                'name'              => ( $is_company ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'created'           => date('Y-m-d') . 'T00:00:00+01:00',
                'salutation'        => ( 1 == get_post_meta( $order_id, '_billing_title', true ) ? __('Mr.', 'rma-wc') : __('Ms.', 'rma-wc') ),
                'firstname'         => $order->get_billing_first_name(),
                'lastname'          => $order->get_billing_last_name(),
                'address1'          => $order->get_billing_address_1(),
                'address2'          => $order->get_billing_address_2(),
                'zipcode'           => $order->get_billing_postcode(),
                'city'              => $order->get_billing_city(),
                'state'             => $order->get_billing_state(),
                'country'           => WC()->countries->countries[ $order->get_billing_country() ],
                'phone'             => $order->get_billing_phone(),
                'fax'               => '',
                'mobile'            => '',
                'email'             => $order->get_billing_email(),
                'cc'                => '',
                'bcc'               => '',
                'language_code'     => '',
                'remittancevoucher' => 'false',
                'arap_accno'        => '', // Default accounts receivable account number - 1100
                'payment_accno'     => '', // Default payment account number	1020
                'notes'             => '',
                'terms'             => '0',
                'typeofcontact'     => ( $is_company ? 'company' : 'person' ),
                'gender'            => ( 1 == $order->get_meta( '_billing_title' ) ? 'M' : 'F' ),

            );

        }

		/**
		 * get WooCommerce order details
		 *
		 * @param $order_id
		 *
		 * @return bool|array
		 */
		private function get_wc_order_details( $order_id ) {

			$order                = new WC_Order( $order_id );
            $settings             = get_option( 'wc_rma_settings' );
			$option_accounting    = get_option( 'wc_rma_settings_accounting' );
            $order_payment_method = $order->get_payment_method();

            // if order is done without...
            if ( 0 == get_post_meta( $order_id, '_customer_user', true ) ) {

                $settings = get_option('wc_rma_settings');

                if ( 1 == $settings[ 'rma-create-guest-customer' ] ) {

                    $rma_customer_id = $this->create_rma_customer( 'order', $order_id );

                    if ( false == $rma_customer_id ) {

                        $log_values = array(
                            'status' => 'error',
                            'section_id' => $order_id,
                            'section' => esc_html_x( 'Customer', 'Log Section', 'rma-wc'),
                            'mode' => self::rma_mode(),
                            'message' => __( 'Could not create RMA customer dedicated guest account', 'rma-wc' )
                        );

                        self::write_log($log_values);

                    }

                }
                else {

                    // customer id is equal to predefined catch all guest account
                    $rma_customer_id         = $settings['rma-guest-catch-all'];

                }

            }
            // ...or with user account
            else {

                $rma_customer_id             = get_user_meta( $order->get_customer_id(), 'rma_customer', true );

            }

            // Set order header
			$order_details['currency']       = $order->get_currency();
			$order_details['orderdate']      = wc_format_datetime($order->get_date_created(),'d.m.Y');
			$order_details['taxincluded']    = $order->get_prices_include_tax() ? 'true' : 'false';
			$order_details['customernumber'] = $rma_customer_id;
            $order_details['ar_accno']       = isset ( $option_accounting[ $order_payment_method ] ) && !empty( $option_accounting[ $order_payment_method ] ) ? $option_accounting[ $order_payment_method ] : '';

            // Calculate due date
			$user_payment_period             = get_user_meta( $order->get_customer_id(), 'rma_payment_period', true );
			// Set payment period - if user payment period not exist set to global period
			$payment_period                  = $user_payment_period ? $user_payment_period : RMA_GLOBAL_PAYMENT_PERIOD;
			// Calculate duedate (now + payment period)
			$order_details['duedate']        = date( DateTime::RFC3339, time() + ($payment_period*60*60*24) );

			// add shipping address; first converts a break tag to a newline – no matter what kind of HTML is being processed.
            $order_details['notes']          = preg_replace('/<br(\s+)?\/?>/i', "\n", $order->get_formatted_shipping_address());

            // Add products to order
			$_order                          = $order->get_items(); //to get info about product
            $order_details_products          = array();

			foreach( $_order as $order_product_detail ){

                // check if the product is a variation and get the right id
                if ( $order_product_detail[ 'variation_id' ] ) {

                    $_product = wc_get_product( $order_product_detail[ 'variation_id' ] );

                }
                else {

                    $_product = wc_get_product( $order_product_detail[ 'product_id' ] );

                }

				$order_details_products[ $_product->get_sku() ] = array(
					'name'     => $order_product_detail['name'],
					'quantity' => $order_product_detail['quantity'],
					'price'    => $_product->get_price()
				);

			}

			// Add Shipping costs
            // @since 1.6.0
            $order_shipping_total_net  = (float) $order->get_shipping_total();
            $order_shipping_tax        = (float) $order->get_shipping_tax();
			$shipping_costs_product_id = isset( $settings[ 'rma-shipping-id' ] ) ? $settings[ 'rma-shipping-id' ] : '';

			// Calculate shipping costs w/ or wo/ tax
			if( $order->get_prices_include_tax() ) {
                $order_shipping_total  = $order_shipping_total_net + $order_shipping_tax;
            }
			else {
                $order_shipping_total  = $order_shipping_total_net;
            }

			// Do we have shipping costs and a product id to use for?
			if( 0 < $order_shipping_total && !empty( $shipping_costs_product_id ) ) {

			    // we get shipping text from settings page otherwise we take shipping method
                $shipping_text = ( isset( $settings['rma-shipping-text'] ) && !empty( $settings['rma-shipping-text'] ) ? $settings['rma-shipping-text'] : $order->get_shipping_method() );

                $order_details_products[ $shipping_costs_product_id ] = array(
                    'name'     => $shipping_text,
                    'quantity' => 1,
                    'price'    => $order_shipping_total
                );

            }
            // Do we have shipping costs but not set up a product id?
			elseif ( 0 < $order_shipping_total && empty( $shipping_costs_product_id ) ) {

                $log_values = array(
                    'status' => 'error',
                    'section_id' => $order_id,
                    'section' => esc_html_x( 'Invoice', 'Log Section', 'rma-wc'),
                    'mode' => self::rma_mode(),
                    'message' => __( 'Could not add shipping costs to invoice because of missing shipping costs product sku', 'rma-wc' )
                );

                self::write_log($log_values);

            }

			return array( $order_details, $order_details_products );

		}

		/**
         * Create invoice in Run my Accounts
         *
		 * @param string $order_id
		 *
		 * @return bool|array
		 */
		public function create_invoice( $order_id='' ) {

            $is_active = self::is_activated( '$order_id ' . $order_id );

			// Continue only if an order_id is available and plugin function is activated
			if( !$order_id || !$is_active ) return false;

			$data = self::get_invoice_values( $order_id );
			$url  = self::get_caller_url() . RMA_MANDANT . '/invoices?api_key=' . RMA_APIKEY;

			//create the xml document
			$xml  = new DOMDocument('1.0', 'UTF-8');

			// create root element invoice and child
			$root = $xml->appendChild($xml->createElement("invoice"));
			foreach( $data['invoice'] as $key => $value ) {
				if ( ! empty( $key ) )
					$root->appendChild( $xml->createElement( $key, $value ) );
			}

			$tab_invoice = $root->appendChild($xml->createElement('parts'));

			// create child elements part
			foreach( $data['part'] as $part ){
				if( !empty( $part ) ){
					$tab_part = $tab_invoice->appendChild($xml->createElement('part'));

					foreach( $part as $key=>$value ){
						$tab_part->appendChild($xml->createElement($key, $value));
					}
				}
			}

			//make the output pretty
			$xml->formatOutput = true;

			//create xml content
			$xml_str = $xml->saveXML() . "\n";

            // send xml content to RMA
			$response = self::send_xml_content( $xml_str, $url );

            // $response empty == no errors
			if ( 200 == self::first_key_of_array( $response ) ||
                 204 == self::first_key_of_array( $response )) {

                $status         = 'invoiced';

                $invoice_number = $data['invoice']['invnumber'];
                $message        = sprintf( esc_html_x( 'Invoice %s created', 'Log', 'rma-wc'), $invoice_number);

                // add order note
                $order          = wc_get_order(  $order_id );
                $note           = sprintf( esc_html_x( 'Invoice %s created in Run my Accounts', 'Order Note', 'rma-wc'), $invoice_number);
                $order->add_order_note( $note );

                update_post_meta( $order_id, '_rma_invoice', $invoice_number );

                unset( $order );

                $return = true;

            }
			else {

                $status  = 'error';
                $message = '[' . self::first_key_of_array( $response ) . '] ' . reset( $response ); // get value of first key = return message

                $return = false;

            }

			if ( ( 'error' == LOGLEVEL && 'error' == $status ) || 'complete' == LOGLEVEL ) {

                $log_values = array(
					'status' => $status,
					'section_id' => $order_id,
                    'section' => esc_html_x('Invoice', 'Log Section', 'rma-wc'),
					'mode' => self::rma_mode(),
					'message' => $message );

                self::write_log($log_values);

				// send email on error
				if ( 'error' == $status && SENDLOGEMAIL ) $this->send_log_email($log_values);

			}

			return $return;
		}

        /**
         * Create customer in Run my Accounts
         *
         * @param string $type
         * @param string $id
         * @param string $action new|update
         *
         * @return bool|string
         */
		public function create_rma_customer( $type, $id='', $action = 'new') {

            if( !$id || !$type)
                return false;

            // exit if plugin is not activated
			if ( !self::is_activated('$user_id ' . $id ) )
                return false;

			// exit if a customer should not be created automatically
            if ( !self::do_create_customer() )
                return false;

            // exit if user is already linked to a RMA customer account
            if ( 'user' == $type   &&
                 'new'  == $action &&
                 get_user_meta( $id, 'rma_customer', true ) )
                return false;

            $method = 'get_customer_values_by_' . $type . '_id';
            $data = self::$method( $id );

			// build REST api url for Run my Accounts
			$caller_url_customer = self::get_caller_url() . RMA_MANDANT . '/customers?api_key=' . RMA_APIKEY;

			//create the xml document
			$xml_doc = new DOMDocument('1.0', 'UTF-8');
			//make the output pretty
			$xml_doc->formatOutput = true;

			// create root element customer and child
			$root = $xml_doc->createElement('customer');
			$root = $xml_doc->appendChild( $root );

			foreach( $data as $key => $val) {

				if ( ! empty( $key ) ) {
					$child = $xml_doc->createElement( $key );
					$child = $root->appendChild( $child );

					$text = $xml_doc->createTextNode( $val );
					$text = $child->appendChild( $text );
				}

			}

            //create xml content
            $xml_str = $xml_doc->saveXML() . "\n";

            // send xml content to RMA with curl
            $response = self::send_xml_content( $xml_str, $caller_url_customer );

            // $response empty == no errors
            if ( 200 == self::first_key_of_array( $response ) ||
                 204 == self::first_key_of_array( $response )) {

                // add RMA customer number to user_meta
                $status         = 'created';
                $message        = sprintf( esc_html_x( 'Customer %s created', 'Log', 'rma-wc'), $data['customernumber']);

                if ( 'user' == $type )
                    update_user_meta( $id, 'rma_customer', $data[ 'customernumber' ] );

            }
            else {

                $status  = 'error';
                $message = '[' . self::first_key_of_array( $response ) . '] ' . reset( $response ); // get value of first key = return message

            }

            if ( ( 'error' == LOGLEVEL && 'error' == $status ) || 'complete' == LOGLEVEL ) {

                $log_values = array(
                    'status' => $status,
                    'section_id' => $id,
                    'section' => sprintf( esc_html_x('Customer by %s id', 'Log Section', 'rma-wc'), $type),
                    'mode' => self::rma_mode(),
                    'message' => $message );

                self::write_log($log_values);

            }

			return 'error' == $status ? 'false' : $data[ 'customernumber' ];
		}

        /**
         * Return first key of an array
         * can be replaced by array_key_first() when min. PHP is 7.3
         *
         * @param $array
         *
         * @return string
         *
         * @since 1.5.2
         */
		public static function first_key_of_array( $array ): string
        {

		    // set point of the array
            reset( $array );

            // return the key
            return key( $array );

        }

        /**
         * Send xml content to RMA with curl
         *
         * @param $xml string
         * @param $url string
         *
         * @return array
         */
		public static function send_xml_content( string $xml, string $url ): array {

            $response = wp_safe_remote_post(
                $url,
                array(
                    'headers'          => array(
                        'Content-Type' => 'application/xml'
                    ),
                    'body'             => $xml
                )
            );

            $response_code    = wp_remote_retrieve_response_code( $response );
            $response_body    = wp_remote_retrieve_body( $response );

            return array( $response_code => $response_body );

        }

		/**
		 * Check if the plugin is activated on settings page
		 *
		 * @param $section_id
		 *
		 * @return string
		 */
		private function is_activated ( $section_id ): string {

			$settings  = get_option( 'wc_rma_settings' );
			$is_active = ( isset( $settings[ 'rma-active' ] ) ? $settings[ 'rma-active' ] : '');

			if ( !$is_active && 'complete' == LOGLEVEL ) {

				$log_values = array(
					'status'     => 'deactivated',
					'section_id' => $section_id,
                    'section'    => esc_html_x( 'Activation', 'Log Section', 'rma-wc' ),
					'mode'       => self::rma_mode(),
					'message'    => esc_html_x( 'Plugin was not activated', 'Log', 'rma-wc' ) );

				self::write_log($log_values);
				// send email with log details
				if ( SENDLOGEMAIL ) self::send_log_email($log_values);
			}

			return $is_active;
		}

        /**
         * Check if the customer should be created in Run my Accounts
         *
         * @return string
         */
        private function do_create_customer(): string {

            $settings = get_option( 'wc_rma_settings' );
            return isset( $settings[ 'rma-create-customer' ] ) ? $settings[ 'rma-create-customer' ] : '';

        }

        /**
         * @return string
         */
        public function rma_mode(): string
        {
            return RMA_CALLERSANDBOX ? 'Test' : 'Live' ;
        }

		/**
		 * Write Log in DB
		 *
		 * @param $values
		 *
		 * @return bool
		 */
		public function write_log( &$values ): bool {
			global $wpdb;

			$table_name = $wpdb->prefix . RMA_WC_LOG_TABLE;

			$wpdb->insert( $table_name,
				array(
					'time'       => current_time( 'mysql' ),
					'status'     => $values['status'],
					'section_id' => $values['section_id'],
                    'section'    => $values['section'],
					'mode'       => $values['mode'],
					'message'    => $values['message']
				)
			);

            // send email on error
            if ( 'error' == $values['status'] && SENDLOGEMAIL ) $this->send_log_email($values);

            return true;
		}

		/**
		 * Send error log by email
		 *
		 * @param $values
		 *
		 * @return bool
		 */
		public function send_log_email( &$values ): bool {

            ob_start();
            include( plugin_dir_path( __FILE__ ) . '../templates/email/error-email-template.php');
            $email_content = ob_get_contents();
            ob_end_clean();

            $headers = array('Content-Type: text/html; charset=UTF-8');
            if ( !wp_mail( LOGEMAIL, esc_html_x('An error occurred while connecting with Run my Accounts API', 'email', 'rma-wc'), $email_content, $headers) ) {

                $log_values = array(
                    'status'     => 'failed',
                    'section_id' => LOGEMAIL,
                    'section'    => esc_html_x( 'Email', 'Log Section', 'rma-wc' ),
                    'mode'       => self::rma_mode(),
                    'message'    => esc_html_x( 'Failed to send email.' , 'Log', 'rma-wc') );

                self::write_log($log_values);

            } elseif ( 'complete' == LOGLEVEL) {

                $log_values = array(
                    'status'     => 'send',
                    'section_id' => LOGEMAIL,
                    'section'    => esc_html_x( 'Email', 'Log Section', 'rma-wc' ),
                    'mode'       => self::rma_mode(),
                    'message'    => esc_html_x( 'Email sent successfully.' , 'Log', 'rma-wc') );

                self::write_log($log_values);

            }

			return true;
		}

	}
}