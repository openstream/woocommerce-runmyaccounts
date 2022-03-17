<?php

if ( ! defined('ABSPATH')) exit;

/**
 * Class for collective invoicing
 *
 * @since 1.7.0
 */
class RMA_WC_Collective_Invoicing {

    private $settings;

    public function __construct() {

        // read rma settings
        $this->settings = get_option( 'wc_rma_settings_collective_invoice' );

        add_action('wp_ajax_next_invoice_date', [ $this, 'ajax_next_invoice_date' ] );

    }

    /**
     * Handle next invoice date by ajax
     *
     * @return void
     *
     * @since 1.7.0
     */
    public function ajax_next_invoice_date(){

        $period  = $_POST['period'];
        $weekday = $_POST['weekday'];

        $dates   = self::get_next_invoice_date($period, $weekday );

        if( !empty( $dates ) ) {

            $this->settings[ 'collective_invoice_next_date' ] = $dates[ 'next_date_ts' ];
            update_option( 'wc_rma_settings_collective_invoice', $this->settings );

        }

        echo $dates[ 'date' ] ?? '';
        exit();
    }

    /**
     * Calculate next invoice date
     *
     * @param $period
     * @param $weekday
     *
     * @return array
     *
     * @since 1.7.0
     */
    public function get_next_invoice_date( $period, $weekday ): array {

        switch ( $period ) {
            case 'week' :
                $next_date_ts = strtotime("next $weekday");
                break;
            case 'second_week' :
                $next_date_ts = strtotime("+2 weeks next $weekday");
                break;
            case 'month' :
                $next_date_ts = strtotime("first $weekday of next month");
                break;
        }

        if( !empty( $next_date_ts ) ) {

            $date = date_i18n( get_option('date_format'), $next_date_ts );

            return array(
                'next_date_ts' => $next_date_ts,
                'date'         => $date
            );

        }

        return array();

    }

    /**
     * Collecting completed invoices, sorted by customer
     *
     * @return array
     *
     * @since 1.7.0
     */
    public function get_paid_orders(): array {

        switch ( $this->settings[ 'collective_invoice_span' ] ) {
            case 'per_week':
                $invoice_from_date = strtotime( "-1 week" );
                break;
            case 'per_month':
                $invoice_from_date = strtotime( "-1 month" );
                break;
            default:
                $invoice_from_date = 0;
                break;
        }

        $orders_no_invoice = get_posts( array(
                                            'numberposts'     => -1,
                                            'post_type'       => 'shop_order',
                                            'post_status'     => 'wc-completed',
                                            'meta_query'      => array(
                                                'relation'    => 'AND',
                                                array(
                                                    'key'     => '_rma_invoice',
                                                    'compare' => 'NOT EXISTS',
                                                ),
                                            )
                                        )
        );

        $cumulated_orders_by_customer_id = array();

        // loop through orders and create an associative array with orders for customer
        foreach ( $orders_no_invoice as $key => $order ) {

            // get values
            $WC_Order  = new WC_Order( $order->ID );
            $user_id   = $WC_Order->get_user_id( );
            $paid_date = $WC_Order->get_date_completed();

            // is paid date in the range for invoicing?
            if( strtotime( $paid_date ) > $invoice_from_date ) {

                // prepare arrays to merge
                $a = is_array(  $cumulated_orders_by_customer_id[ $user_id ] ) ?  $cumulated_orders_by_customer_id[ $user_id ] : array();
                $b = array( 1 => $order->ID );

                // merge previous order ids of a customer with additional order id
                $cumulated_orders_by_customer_id[ $user_id ] = array_merge( $a, $b );

           }

        }

        return $cumulated_orders_by_customer_id;

    }

}
