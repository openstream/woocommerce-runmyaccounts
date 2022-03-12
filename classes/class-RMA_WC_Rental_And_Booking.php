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

}