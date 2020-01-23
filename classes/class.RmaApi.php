<?php
if ( !defined('ABSPATH') ) exit;

if ( !class_exists('WC_RMA_API') ) {

	class WC_RMA_API {

		/**
		 *  Construct
		 */
		public function __construct() {

		    self::define_constants();

		}

        /**
         * define default constants
         */
		private function define_constants() {
            // read rma settings
            $rma_settings = get_option('wc_rma_settings');

            // check if operation mode is set and is live
            if( isset( $rma_settings['rma-mode'] ) && 'live' == $rma_settings['rma-mode'] ) {

                // define constants with live values
                DEFINE( 'MANDANT', ( isset( $rma_settings['rma-live-client'] ) ? $rma_settings['rma-live-client'] : '' ) );
                DEFINE( 'APIKEY', ( isset( $rma_settings['rma-live-apikey'] ) ? $rma_settings['rma-live-apikey'] : '' ) );
                DEFINE( 'CALLERSANDBOX', FALSE );

            } else {

                // set default operation mode to test
                DEFINE( 'MANDANT', ( isset( $rma_settings['rma-test-client'] ) ? $rma_settings['rma-test-client'] : '' ) );
                DEFINE( 'APIKEY', ( isset( $rma_settings['rma-test-apikey'] ) ? $rma_settings['rma-test-apikey'] : '' ) );
                DEFINE( 'CALLERSANDBOX', TRUE );

            }

            DEFINE( 'DESCRIPTION', ( isset( $rma_settings['rma-invoice-description'] ) ? $rma_settings['rma-invoice-description'] : '' ) );
            DEFINE( 'GLOBALPAYMENTPERIOD', ( isset( $rma_settings['rma-payment-period'] ) ? $rma_settings['rma-payment-period'] : '0' ) ); // default value 0 days
            DEFINE( 'INVPREFIX', ( isset( $rma_settings['rma-invoice-prefix'] ) ? $rma_settings['rma-invoice-prefix'] : '' ) );
            DEFINE( 'INVDIGITS', ( isset( $rma_settings['rma-digits'] ) ? $rma_settings['rma-invoice-description'] : '' ) );

            // if rma-loglevel ist not set, LOGLEVEL is set to error by default
            if( isset( $rma_settings['rma-loglevel'] ) ) {
                if( 'error' == $rma_settings['rma-loglevel']  || empty( $rma_settings['rma-loglevel'] ) ) {
                    DEFINE( 'LOGLEVEL' , 'error' );
                } elseif ( $rma_settings['rma-loglevel'] == 'complete' ) {
                    DEFINE( 'LOGLEVEL' , 'complete' );
                }
            } else {
                DEFINE( 'LOGLEVEL' , 'error' );
            }

            // if rma-logemail ist not set, LOGEMAIL is set to false by default
            if( isset ($rma_settings['rma-logemail'] ) &&
                'yes' == $rma_settings['rma-logemail'] &&
                !empty( $rma_settings['rma-logemail'] )) {
                DEFINE( 'LOGEMAIL' , true );
            } else {
                DEFINE( 'LOGEMAIL' , false );
            }

        }

		/**
		 * Set Caller URL live oder sandbox
		 * @return string
		 */
		public function get_caller_url() {
			// Set caller URL
			if( CALLERSANDBOX ) { // Caller URL set for Sandbox
				$caller_url = 'https://service-swint.runmyaccounts.com/api/latest/clients/'; // End with / !
			} else { // Caller URL set for Live page
				$caller_url = 'https://service.runmyaccounts.com/api/latest/clients/'; // End with / !
			}

			return $caller_url;
		}

		/**
		 * Read customer list from RMA
		 * @return mixed
		 */
		public function get_customers() {
			$caller_url_customer = $this->get_caller_url() . MANDANT . '/customers?api_key=' . APIKEY;

			// Read response file
			if ( false === ( $response_xml_data = @file_get_contents($caller_url_customer ) ) ){

				return false;

			} else {
				libxml_use_internal_errors(true);
				$data = simplexml_load_string($response_xml_data, 'SimpleXMLElement', LIBXML_NOCDATA);
				if ( !$data ) {
					// ToDO: Add this information to error log
					foreach(libxml_get_errors() as $error) {
						echo "\t", $error->message;
					}

					return false;

				} else {
					// Parse response
					$array = json_decode( json_encode( (array)$data ), TRUE);

					// Transform into array
					foreach ($array as $value) {
						foreach ($value as $key => $customer ) {
							$customers[$customer['customernumber']] = $customer['name'] . ' ( ' . $customer['customernumber'] . ')';
						}
					}

					return ( !empty( $customers ) ? $customers : false );
				}
			}
		}

		/**
		 * Read product list from RMA
		 * @return mixed
		 */
		public function get_parts() {

			$caller_url_parts = $this->get_caller_url() . MANDANT . '/parts?api_key=' . APIKEY;

			// Read response file
			if (($response_xml_data = file_get_contents($caller_url_parts))===false){
				echo "Error fetching XML\n";
			} else {
				libxml_use_internal_errors(true);
				$data = simplexml_load_string($response_xml_data, 'SimpleXMLElement', LIBXML_NOCDATA);
				if (!$data) {
					echo "Error loading XML\n";
					foreach(libxml_get_errors() as $error) {
						echo "\t", $error->message;
					}
				} else {
					// Parse response
					$array = json_decode(json_encode((array)$data), TRUE);

					// Transform into array
					foreach ($array as $value) {
						foreach ($value as $key => $part ) {
							$description = $part['description'];
							if(!is_array($description)) // proceed only if description is not an array
								$parts[$part['partnumber']] = str_replace(array("\r", "\n"), '', $description); // Remove line breaks
						}
					}

					return ( !empty( $parts ) ? $parts : false);
				}
			}
		}

		/**
		 * Create data for invoice
		 *
		 * @param $orderID
         *
		 * @return array
		 */
		private function get_invoice_values($orderID) {

			list( $orderDetails, $orderDetailsProducts ) = $this->get_wc_order_details( $orderID );


			// ToDo: add notes to invoice from notes field WC order
			$data = array(
                    'invoice' => array(
                    'invnumber' => INVPREFIX . str_pad($orderID, max( INVDIGITS-strlen(INVPREFIX), 0 ), '0', STR_PAD_LEFT),
                    'ordnumber' => $orderID,
                    'status' => 'OPEN',
                    'currency' => $orderDetails['currency'],
                    'ar_accno' => $orderDetails['ar_accno'],
                    'transdate' => date( DateTime::RFC3339, time() ),
                    'duedate' => $orderDetails['duedate'], //date( DateTime::RFC3339, time() ),
                    'description' => str_replace('[orderdate]',$orderDetails['orderdate'], DESCRIPTION),
                    'notes' => '',
                    'intnotes' => '',
                    'taxincluded' => $orderDetails['taxincluded'],
                    'dcn' => '',
                    'customernumber' => $orderDetails['customernumber']
				),
				'part' => array()
			);

			// Add parts
			if (count($orderDetailsProducts) > 0) :
				foreach ($orderDetailsProducts as $partnumber => $part ) :
					$data['part'][] = array (
						'partnumber' => $partnumber,
						'description' => $part['name'],
						'unit' => '',
						'quantity' => $part['quantity'],
						'sellprice' => $part['price'],
						'discount' => '0.0',
						'itemnote' => '',
						'price_update' => '',
					);
				endforeach;
			endif;

			return $data;
		}

        /**
         * Prepare data of a customer for sending to Run my Accounts
         *
         * @param $user_id
         *
         * @return array
         * @throws Exception
         */
		private function get_customer_values( $user_id ) {

            $settings = get_option( 'wc_rma_settings' );
            $customer_prefix = isset( $settings[ 'rma-customer-prefix' ] ) ? $settings[ 'rma-customer-prefix' ] : '';

            $customer = new WC_Customer( $user_id );

            $is_company = !empty( $customer->get_billing_company() ) ? true : false;

            return array(
				'customernumber' => $customer_prefix . $user_id,
				'name' => ( $is_company ? $customer->get_billing_company() : $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() ),
				'created' => date('Y-m-d') . 'T00:00:00+01:00',
				'salutation' => ( 1 == get_user_meta( $user_id, 'billing_title', true ) ? __('Mr.', 'woocommerce-rma') : __('Ms.', 'woocommerce-rma') ),
				'firstname' => $customer->get_billing_first_name(),
				'lastname' => $customer->get_billing_last_name(),
				'address1' => $customer->get_billing_address_1(),
				'address2' => $customer->get_billing_address_2(),
				'zipcode' => $customer->get_billing_postcode(),
				'city' => $customer->get_billing_city(),
				'state' => $customer->get_billing_state(),
				'country' => WC()->countries->countries[ $customer->get_billing_country() ],
				'phone' => $customer->get_billing_phone(),
				'fax' => '',
				'mobile' => '',
				'email' => $customer->get_billing_email(),
				'cc' => '',
				'bcc' => '',
				'language_code' => '',
				'remittancevoucher' => 'false',
				'arap_accno' => '', // Default accounts receivable account number - 1100
                'payment_accno' => '', // Default payment account number	1020
				'notes' => '',
				'terms' => '0',
				'typeofcontact' => ( $is_company ? 'company' : 'person' ),
				'gender' => ( 1 == get_user_meta( $user_id, 'billing_title', true ) ? 'M' : 'F' ),

			);

		}

		/**
		 * get WooCommerce order details
		 *
		 * @param $orderID
		 *
		 * @return array
		 */
		private function get_wc_order_details($orderID) {

			$order = new WC_Order( $orderID );

			$orderDetails['currency'] = $order->get_currency();
			$orderDetails['orderdate'] = wc_format_datetime($order->get_date_created(),'d.m.Y');
			$orderDetails['taxincluded'] = $order->get_prices_include_tax() ? 'true' : 'false';
			$orderDetails['ar_accno'] = get_user_meta( $order->get_customer_id(), 'rma_billing_account', true );
			$orderDetails['customernumber'] = get_user_meta( $order->get_customer_id(), 'rma_customer', true );

			// Calculate due date
			$user_payment_period = get_user_meta( $order->get_customer_id(), 'rma_payment_period', true );
			// Set payment period - if user payment not period exist set tu global period
			$payment_period = ( $user_payment_period ? $user_payment_period : GLOBALPAYMENTPERIOD);
			// Calculate duedate (now + payment period)
			$orderDetails['duedate'] = date( DateTime::RFC3339, time() + ($payment_period*60*60*24) );

			$_order = $order->get_items(); //to get info about product
            $order_details_products = array();

			foreach($_order as $order_product_detail){

				$_product = wc_get_product( $order_product_detail['product_id'] );

				$order_details_products[$_product->get_sku()] = array(
					'name' => $order_product_detail['name'],
					'quantity' => $order_product_detail['quantity'],
					'price' => $_product->get_price()
				);

			}

			return array($orderDetails, $order_details_products);
		}

		/**
         * Create inovice in Run My Accounts
         *
		 * @param string $order_id
		 *
		 * @return bool|string
		 */
		public function create_invoice( $order_id='' ) {

			$is_active = self::is_activated( __('$order_id', 'woocommerce-rma', 'Log') . ' ' . $order_id );

			// Continue only if an order_id is available and plugin function is activated
			if( !$order_id || !$is_active ) return false;

			$data = $this->get_invoice_values( $order_id );

			$caller_url_invoice = $this->get_caller_url() . MANDANT . '/invoices?api_key=' . APIKEY;

			//create the xml document
			$xml_doc = new DOMDocument('1.0', 'UTF-8');

			// create root element invoice and child
			$root = $xml_doc->appendChild($xml_doc->createElement("invoice"));
			foreach($data['invoice'] as $key=>$val) {
				if ( ! empty( $key ) )
					$root->appendChild( $xml_doc->createElement( $key, $val ) );
			}

			$tab_invoice = $root->appendChild($xml_doc->createElement('parts'));

			// create child elements part
			foreach( $data['part'] as $part ){
				if( !empty( $part ) ){
					$tab_part = $tab_invoice->appendChild($xml_doc->createElement('part'));

					foreach( $part as $key=>$val ){
						$tab_part->appendChild($xml_doc->createElement($key, $val));
					}
				}
			}

			//make the output pretty
			$xml_doc->formatOutput = true;

			//create xml content
			$xml_str = $xml_doc->saveXML() . "\n";

            // send xml content to RMA with curl
			$response = self::send_xml_content( $xml_str, $caller_url_invoice );

			// $response !empty => errors
			$status = ( ( empty( $response ) ) ? 'invoiced' : 'error' );

			//ToDo: add order note if no error

			if ( ( 'error' == LOGLEVEL && 'error' == $status ) || 'complete' == LOGLEVEL ) {

                $log_values = array(
					'status' => $status,
					'section_id' => $order_id,
					'section' => __( 'Invoice', 'woocommerce-rma'),
					'mode' => self::rma_mode(),
					'message' => $response );
				self::write_log($log_values);

				// send email on error
				if ( 'error' == $status && LOGEMAIL ) $this->send_log_email($log_values);

			}

			return $response;
		}

        /**
         * Create customer in Run My Accounts
         *
         * @param string $user_id
         *
         * @return bool|string
         * @throws Exception
         */
		public function create_customer( $user_id='' ) {

		    // is plugin function activated
			$is_active = self::is_activated( __('$user_id','woocommerce-rma','Log') . ' ' . $user_id );

			// should a customer be created automatically
			$do_create_customer = self::do_create_customer();

            // is user connected with a RMA customer
            $rma_customer_id = get_user_meta( $user_id, 'rma_customer', true );

            if( !$user_id || !$is_active || !$do_create_customer )
			    return false;

            // user_id ias already linked to a RMA customer
            if( $rma_customer_id )
                return true;

			$data = $this->get_customer_values( $user_id );

			// build REST api url for Run My Accounts
			$caller_url_customer = $this->get_caller_url() . MANDANT . '/customers?api_key=' . APIKEY;

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

			// $response !empty => errors
			$status = ( ( empty($response) ) ? 'customer created' : 'error' );

			if ( ( 'error' == LOGLEVEL && 'error' == $status ) || 'complete' == LOGLEVEL ) {

				$log_values = array(
					'status' => $status,
					'section_id' => $user_id,
                    'section' => __( 'Customer', 'woocommerce-ram' ),
					'mode' => self::rma_mode(),
					'message' => $response );
				self::write_log($log_values);

				// send email on error
				if ( 'error' == $status && LOGEMAIL ) $this->send_log_email($log_values );

			}

            // $response empty => no errors
            if ( empty( $response ) ) {
                // add RMA customer number to user_meta
                update_user_meta( $user_id, 'rma_customer', $data['customernumber'] ); // return (int|bool) Meta ID if the key didn't exist, true on successful update, false on failure.

                return true;
            }

			return $response;
		}

        /**
         * Send xml content to RMA with curl
         *
         * @param $str string
         * @param $url string
         *
         * @return bool|string
         */
		private function send_xml_content($str, $url) {

            // send xml content to RMA with curl
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, "$str");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            curl_close($ch);

            return $response;

        }

		/**
		 * Check if the plugin is activated on settings page
		 *
		 * @param $id
		 *
		 * @return string
		 */
		private function is_activated ( $id ) {

			$settings = get_option( 'wc_rma_settings' );
			$is_active = ( isset( $settings[ 'rma-active' ] ) ? $settings[ 'rma-active' ] : '');

			if ( !$is_active && 'complete' == LOGLEVEL ) {

				$log_values = array(
					'status' => 'deactivated',
					'section_id' => $id,
                    'section' => __( 'Activation', 'woocommerce-ram' ),
					'mode' => self::rma_mode(),
					'message' => __('Plugin was not activated','woocommerce-rma','Log') );

				self::write_log($log_values);
				// send email with log details
				if ( LOGEMAIL ) $this->send_log_email($log_values);
			}

			return $is_active;
		}

        /**
         * Check if the customer should be created in Run My Accounts
         *
         * @param $id
         *
         * @return string
         */
        private function do_create_customer() {

            $settings = get_option( 'wc_rma_settings' );
            return isset( $settings[ 'rma-create-customer' ] ) ? $settings[ 'rma-create-customer' ] : '';

        }

        /**
         * @return string
         */
        private function rma_mode() {
            return CALLERSANDBOX ? 'Test' : 'Live' ;
        }

		/**
		 * Write Log in DB
		 *
		 * @param $values
		 *
		 * @return bool
		 */
		private function write_log(&$values) {
			global $wpdb;

			$table_name = $wpdb->prefix . WC_RMA_LOG_TABLE;

			$wpdb->insert( $table_name,
				array(
					'time' => current_time( 'mysql' ),
					'status' => $values['status'],
					'section_id' => $values['section_id'],
                    'section' => $values['section'],
					'mode' => $values['mode'],
					'message' => $values['message']
				)
			);

			return true;
		}

		/**
		 * Send error log by email
		 *
		 * @param $values
		 *
		 * @return bool
		 */
		public function send_log_email(&$values) {
			// ToDo: send email out
			/*
							array(
								'time' => current_time( 'mysql' ),
								'status' => $values['status'],
								'section_id' => $values['orderid'],

								'mode' => $values['mode'],
								'message' => $values['message']
							)
			*/
			return true;
		}

	}
}