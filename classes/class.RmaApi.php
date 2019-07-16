<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('WC_RMA_API')) {

	class WC_RMA_API {

		/**
		 *  Construct
		 */
		public function __construct() {

			// read rma settings
			$rmasettings = get_option('wc_rma_settings');

			// if rma-mode ist not set, operation mode is test by default
			if( isset($rmasettings['rma-mode'] ) ) {
				if( $rmasettings['rma-mode'] == 'live' ) {
					if(isset($rmasettings['rma-live-client'])) { DEFINE( 'MANDANT', $rmasettings['rma-live-client']); } else { DEFINE('MANDANT', '');}
					if(isset($rmasettings['rma-live-apikey'])) { DEFINE( 'APIKEY', $rmasettings['rma-live-apikey']); } else { DEFINE('APIKEY', '');}
					DEFINE( 'CALLERSANDBOX' , FALSE );
				} elseif ( $rmasettings['rma-mode'] == 'test' ) {
					if(isset($rmasettings['rma-test-client'])) { DEFINE( 'MANDANT', $rmasettings['rma-test-client']); } else { DEFINE('MANDANT', '');}
					if(isset($rmasettings['rma-test-apikey'])) { DEFINE( 'APIKEY', $rmasettings['rma-test-apikey']); } else { DEFINE('APIKEY', '');}
					DEFINE( 'CALLERSANDBOX' , TRUE );
				} else {
					DEFINE( 'MANDANT', '' );
					DEFINE( 'APIKEY', '' );
					DEFINE( 'CALLERSANDBOX' , TRUE );
				}
			} else {
				if(isset($rmasettings['rma-test-client'])) { DEFINE('MANDANT', $rmasettings['rma-test-client']); } else {DEFINE('MANDANT', '');}
				if(isset($rmasettings['rma-test-apikey'])) { DEFINE('APIKEY', $rmasettings['rma-test-apikey']); } else {DEFINE('APIKEY', '');}
			}
			if( isset( $rmasettings['rma-invoice-description'] ) ) { DEFINE( 'DESCRIPTION', $rmasettings['rma-invoice-description'] ); } else { DEFINE('DESCRIPTION', '' );}
			if( isset( $rmasettings['rma-payment-period'] ) ) { DEFINE( 'GLOBALPAYMENTPERIOD', $rmasettings['rma-payment-period'] ); } else { DEFINE( 'GLOBALPAYMENTPERIOD', '0' );} // default set zu 0 days
			if(isset($rmasettings['rma-invoice-prefix'])) { DEFINE('INVPREFIX', $rmasettings['rma-invoice-prefix']); } else {DEFINE('INVPREFIX', '');}
			if(isset($rmasettings['rma-digits'])) { DEFINE('INVDIGITS', $rmasettings['rma-digits']); } else {DEFINE('INVDIGITS', '');}

			// if rma-loglevel ist not set, LOGLEVEL is set to error by default
			if( isset($rmasettings['rma-loglevel'] ) ) {
				if( 'error' == $rmasettings['rma-loglevel']  || empty( $rmasettings['rma-loglevel'] ) ) {
					DEFINE( 'LOGLEVEL' , 'error' );
				} elseif ( $rmasettings['rma-loglevel'] == 'complete' ) {
					DEFINE( 'LOGLEVEL' , 'complete' );
				}
			} else {
				DEFINE( 'LOGLEVEL' , 'error' );
			}

			// if rma-logemail ist not set, LOGEMAIL is set to false by default
			if( isset($rmasettings['rma-logemail'] ) ) {
				if( 'no' == $rmasettings['rma-logemail']  || empty( $rmasettings['rma-logemail'] ) ) {
					DEFINE( 'LOGEMAIL' , false );
				} elseif ( $rmasettings['rma-logemail'] == 'yes' ) {
					DEFINE( 'LOGEMAIL' , true );
				}
			} else {
				DEFINE( 'LOGEMAIL' , false );
			}

		}

		/**
		 * Set Caller URL live oder sandbox
		 * @return string
		 */
		public function get_callerUrl() {
			// Set caller URL
			if(CALLERSANDBOX) { // Caller URL set for Sandbox
				$callerUrl = 'https://service-swint.runmyaccounts.com/rma-backend/latest/clients/'; // End with / !
			} else { // Caller URL set for Live page
				$callerUrl = 'https://service.runmyaccounts.com/api/latest/clients/'; // End with / !
			}

			return $callerUrl;
		}

		/**
		 * Read customer list from RMA
		 * @return mixed
		 */
		public function get_customers() {
			$callerUrlCustomer = $this->get_callerUrl() . MANDANT . '/customers?api_key=' . APIKEY;

			// Read response file
			if ( false === ($response_xml_data = @file_get_contents($callerUrlCustomer) ) ){

				return false;

			} else {
				libxml_use_internal_errors(true);
				$data = simplexml_load_string($response_xml_data, 'SimpleXMLElement', LIBXML_NOCDATA);
				if (!$data) {
					// ToDO: Add this information to error log
					foreach(libxml_get_errors() as $error) {
						echo "\t", $error->message;
					}

					return false;

				} else {
					// Parse response
					$array = json_decode(json_encode((array)$data), TRUE);

					// Transform into array
					foreach ($array as $value) {
						foreach ($value as $key => $customer ) {
							$customers[$customer['customernumber']] = $customer['name'] . ' ( ' . $customer['customernumber'] . ')';
						}
					}

					return $customers;
				}
			}
		}

		/**
		 * Read product list from RMA
		 * @return mixed
		 */
		public function get_parts() {
			$callerUrlParts = $this->get_callerUrl() . MANDANT . '/parts?api_key=' . APIKEY;

			// Read response file
			if (($response_xml_data = file_get_contents($callerUrlParts))===false){
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

					return $parts;
				}
			}
		}

		/**
		 * Create data for invoice
		 *
		 * @param $orderID
		 * @return array
		 */
		private function get_invoice_data($orderID) {

			list($orderDetails, $orderDetailsProducts) = $this->getWC_order_details($orderID);


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
					'customernumber' => $orderDetails['customernumber'],
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
		 * get WooCommerce order details
		 *
		 * @param $orderID
		 *
		 * @return array
		 */
		private function getWC_order_details($orderID) {

			$order = new WC_Order( $orderID );

			$orderDetails['currency'] = $order->get_currency();
			$orderDetails['orderdate'] = wc_format_datetime($order->get_date_created(),'d.m.Y');
			$orderDetails['taxincluded'] = ( $order->get_prices_include_tax() ? 'true' : 'false' );
			$orderDetails['ar_accno'] = get_user_meta( $order->get_customer_id(), 'rma_billing_account', true );
			$orderDetails['customernumber'] = get_user_meta( $order->get_customer_id(), 'rma_customer', true );

			// Calculate due date
			$user_payment_period = get_user_meta( $order->get_customer_id(), 'rma_payment_period', true );
			// Set payment period - if user payment not period exist set tu global period
			$payment_period = ( $user_payment_period ? $user_payment_period : GLOBALPAYMENTPERIOD);
			// Calculate duedate (now + payment period)
			$orderDetails['duedate'] = date( DateTime::RFC3339, time() + ($payment_period*60*60*24) );

			$_order = $order->get_items(); //to get info about product
			foreach($_order as $order_product_detail){

				$_product = wc_get_product( $order_product_detail['product_id'] );

				$orderDetailsProducts[$_product->get_sku()] = array(
					'name' => $order_product_detail['name'],
					'quantity' => $order_product_detail['quantity'],
					'price' => $_product->get_price()
				);

			}

			return array($orderDetails, $orderDetailsProducts);
		}

		public function post_invoice($orderID='') {

			$rmasettings = get_option('wc_rma_settings');
			$rmaactive = ( isset($rmasettings['rma-active']) ? $rmasettings['rma-active'] : '');
			$rmaMode = CALLERSANDBOX ? 'Test' : 'Live' ;

			if ( !$rmaactive && 'complete' == LOGLEVEL ) {

				$logvalues = array(
					'status' => 'deactivated',
					'orderid' => $orderID,
					'mode' => $rmaMode,
					'message' => __('Plugin was not activated','wc-rma','Log') );

				$this->writeLog($logvalues);
				// send email with log details
				if ( LOGEMAIL ) $this->sendLogEmail($logvalues);
			}


			// Continue only if an orderID is available or plugin function is activated
			if( !$orderID || !$rmaactive ) return false;

			$data = $this->get_invoice_data($orderID);

			$callerUrlInvoice = $this->get_callerUrl() . MANDANT . '/invoices?api_key=' . APIKEY;

			//create the xml document
			$xmlDoc = new DOMDocument('1.0', 'UTF-8');

			// create root element invoice and child
			$root = $xmlDoc->appendChild($xmlDoc->createElement("invoice"));
			foreach($data['invoice'] as $key=>$val) {
				if ( ! empty( $key ) )
					$root->appendChild( $xmlDoc->createElement( $key, $val ) );
			}

			$tabInvoice = $root->appendChild($xmlDoc->createElement('parts'));

			// create child elements part
			foreach($data['part'] as $part){
				if(!empty($part)){
					$tabPart = $tabInvoice->appendChild($xmlDoc->createElement('part'));

					foreach($part as $key=>$val){
						$tabPart->appendChild($xmlDoc->createElement($key, $val));
					}
				}
			}

			// header("Content-Type: text/plain");

			//make the output pretty
			$xmlDoc->formatOutput = true;

			//create xml content
			$xml_str = $xmlDoc->saveXML() . "\n";

			// send xml content to RMA with curl
			$ch = curl_init($callerUrlInvoice);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
			curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml_str");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$response = curl_exec($ch);
			curl_close($ch);

			// $response !empty => errors
			$status = ( ( empty($response) ) ? 'invoiced' : 'error' );

			//ToDo: add order note if no error

			if ( ( 'error' == LOGLEVEL && 'error' == $status ) || 'complete' == LOGLEVEL ) {

				$logvalues = array(
					'status' => $status,
					'orderid' => $orderID,
					'mode' => $rmaMode,
					'message' => $response );
				$this->writeLog($logvalues);

				// send email on error
				if ( 'error' == $status && LOGEMAIL ) $this->sendLogEmail($logvalues);

			}

			return $response;
		}

		/**
		 *
		 * Write Log in DB
		 *
		 * @param $values
		 *
		 * @return bool
		 */
		public function writeLog(&$values) {
			global $wpdb;

			$tablename = $wpdb->prefix . WC_RMA_LOG_TABLE;

			$wpdb->insert( $tablename,
				array(
					'time' => current_time( 'mysql' ),
					'status' => $values['status'],
					'orderid' => $values['orderid'],
					'mode' => $values['mode'],
					'message' => $values['message']
				)
			);

			return true;
		}

		/**
		 *
		 * Send error log by email
		 *
		 * @param $values
		 *
		 * @return bool
		 */
		public function sendLogEmail(&$values) {
			// ToDo: send email out
			/*
							array(
								'time' => current_time( 'mysql' ),
								'status' => $values['status'],
								'orderid' => $values['orderid'],
								'mode' => $values['mode'],
								'message' => $values['message']
							)
			*/
			return true;
		}


	}
}