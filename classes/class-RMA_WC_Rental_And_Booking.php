<?php

if ( ! defined('ABSPATH') ) exit;

/**
 * Class for extending features for plugin WooCommerce Rental & Booking System
 * https://codecanyon.net/item/rnb-woocommerce-rental-booking-system/14835145
 *
 * @since 1.7.0
 */
class RMA_WC_Rental_And_Booking {

    public function __construct() {

        self::init();

    }

    /**
     * Initialize stuff like variables, filter, hooks
     *
     * @return void
     *
     * @since 1.7.0
     *
     * @author Sandro Lucifora
     */
    public function init() {

        add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tabs' ) );

        add_filter( 'rma_invoice_part', array( $this, 'modify_rma_invoice_part' ), 10, 2 );

    }

    /**
     * Shows additional product tab for product type
     *
     * @param $tabs
     *
     * @return array
     *
     * @since 1.7.0
     *
     * @author Sandro Lucifora
     */
    public function woocommerce_product_data_tabs( $tabs ): array {

        $tabs[ 'inventory' ][ 'class' ][] = 'show_if_redq_rental';

        return $tabs;

    }

    /**
     * Modifies invoice part with rental details
     *
     * @param array $part    The original part array
     * @param int   $item_id The item id of this order part
     *
     * @return array         Modified part
     * @throws Exception
     *
     * @since 1.7.0
     *
     * @author Sandro Lucifora
     */
    public function modify_rma_invoice_part( array $part, int $item_id ): array {

        // get values
        $days            = wc_get_order_item_meta( $item_id, '_return_hidden_days' );
        $total           = wc_get_order_item_meta( $item_id, '_line_total' );
        $tax             = wc_get_order_item_meta( $item_id, '_line_tax' );
        $pickup_location = wc_get_order_item_meta( $item_id, 'Pickup Location' );
        $pickup_date     = wc_get_order_item_meta( $item_id, 'Pickup Date & Time' );
        $return_date     = wc_get_order_item_meta( $item_id, 'Return Date & Time' );
        $total_days      = wc_get_order_item_meta( $item_id, 'Total Days' );

        // set line total price
        if( wc_tax_enabled() ) {

            $part[ 'sellprice' ] = round( $total + $tax, 2 );

        }else {

            $part[ 'sellprice' ] = $total;

        }

        // build multiline description
        $part[ 'description' ] = $part[ 'description' ] . "\n" .
                                 sprintf( __( 'Pickup Location: %s', 'rma-wc' ), $pickup_location ) . "\n" .
                                 sprintf( __( 'Pickup Date/Time: %s', 'rma-wc' ), $pickup_date ) . "\n" .
                                 sprintf( __( 'Return Date/Time: %s', 'rma-wc' ), $return_date ) . "\n" .
                                 sprintf( __( 'Total Days: %s (%s)', 'rma-wc' ), $days, $total_days );

        // return modified array
        return $part;

    }

}