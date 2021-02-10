<?php

if ( ! defined('ABSPATH')) exit;

/**
 * Class for sending payment to Run My Accounts
 *
 * @since 1.6.0
 */
class RMA_WC_Payment {

    public $order_id;
    private $invoice;

    public function __construct() {

        // define constants only if they are not defined yet
        if ( !defined( 'RMA_MANDANT' ) )
            self::define_constants();

    }

    /**
     * define default constants
     *
     * @since 1.6.0
     */
    private function define_constants() {
        //ToDO: create a central definition which can be used by all classes

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

    }

    /**
     * Send payment
     *
     * @return array|bool
     *
     * @since 1.6.0
     */
    public function send_payment() {

        // continue only if an order_id is available
        if( !$this->order_id ) return false;

        // get order details
        $order = new WC_Order( $this->order_id );

        // continue only if the order is paid
        if( !$order->is_paid() ) return false;

        // get the Run My Accounts invoice number
        $this->invoice = get_post_meta( $this->order_id, '_rma_invoice', true );

        $data = self::get_payment_details();
        $url  = RMA_WC_API::get_caller_url() . RMA_MANDANT . '/invoices/' . $this->invoice . '/payment_list?api_key=' . RMA_APIKEY;

        //create the xml document
        $xml  = new DOMDocument('1.0', 'UTF-8');

        // create root element invoice and child
        $root    = $xml->appendChild( $xml->createElement('payments' ) );
        $payment = $root->appendChild( $xml->createElement('payment' ) );
        foreach( $data['payment'] as $key => $value ) {
            if ( ! empty( $key ) )
                $payment->appendChild( $xml->createElement( $key, $value ) );
        }

        // make the output pretty
        $xml->formatOutput = true;

        // create xml content
        $xml_str = $xml->saveXML() . "\n";

        // send xml content to RMA
        $response = RMA_WC_API::send_xml_content( $xml_str, $url );

        // $response empty == no errors
        if ( 200 == RMA_WC_API::first_key_of_array( $response ) ||
             204 == RMA_WC_API::first_key_of_array( $response )) {

            $status         = 'paid';

            $message        = sprintf( esc_html_x( 'Payment %s %s booked on account %s', 'Order Note', 'rma-wc'), $data['payment']['amount_paid'], $data['payment']['currency'], $data['payment']['payment_accno'] );

            // add order note
            $order          = wc_get_order(  $this->order_id );
            $order->add_order_note( $message );

            unset( $order );

        }
        else {

            $status  = 'error';
            $message = '[' . RMA_WC_API::first_key_of_array( $response ) . '] ' . reset( $response ); // get value of first key = return message

        }

        if ( ( 'error' == LOGLEVEL && 'error' == $status ) || 'complete' == LOGLEVEL ) {

            $log_values = array(
                'status' => $status,
                'section_id' => $this->order_id,
                'section' => esc_html_x('Payment', 'Log Section', 'rma-wc'),
                'mode' => (new RMA_WC_API)->rma_mode(),
                'message' => $message );

            (new RMA_WC_API)->write_log($log_values);

        }

        return $response;

    }

    /**
     * Create payment details
     *
     * @return array
     *
     * @since 1.6.0
     * @author Sandro Lucifora
     */
    private function get_payment_details(): array
    {

        $option = get_option( 'wc_rma_settings_accounting' );

        $order = new WC_Order( $this->order_id );

        $account = isset( $option[ $order->get_payment_method() . '_payment_account' ] ) && !empty( $option[ $order->get_payment_method() . '_payment_account' ] ) ? $option[ $order->get_payment_method() . '_payment_account' ] : 1020 ;

        return array(
            'payment' => array(
                'id'             => $this->order_id,
                'invnumber'      => $this->invoice,
                'datepaid'       => $order->get_date_paid() ,//date( DateTime::RFC3339, time() ),
                'amount_paid'    => $order->get_total(),
                'source'         => 'Shop-Payment',
                'memo'           => $order->get_payment_method_title(),
                'payment_accno'  => $account,
                'currency'       => $order->get_currency(),
                'exchangerate'   => '1.0',
                'flag'           => 'NEW',
            ),
        );

    }

}