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

        $dates   = self::calculate_next_invoice_date($period, $weekday );

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
    public function calculate_next_invoice_date( $period, $weekday ): array {

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

}
