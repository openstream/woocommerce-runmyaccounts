jQuery(document).ready(function(){

    show_guest_input();

    jQuery("#rma-create-guest-customer").click( function( event ) {

        show_guest_input();

    });

    jQuery("a#flush-table").click( function( event ) {

        jQuery( "a#flush-table" ).css( { "pointer-events":"none", "color":"lightgrey" } );

        jQuery( "span.spinner" ).addClass("is-active")
            .css( { "float":"left" } );

        var data = {
            'action': 'rma_log_table',
            'db_action': 'flush'
        };

        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery.post( ajaxurl, data, function( response ) {
            jQuery( "span.spinner" ).css( { "float":"right" } )
                .removeClass("is-active");

            jQuery( "a#flush-table" ).css( { "pointer-events":"", "color":"#fff", "display":"none" } );

            jQuery( "tbody" ).css( { "display":"none" } );

            if (response) { alert( response ) }

        });

    });
});

function show_guest_input() {

    if (jQuery('#rma-create-guest-customer').is(":checked"))
    {
        jQuery("#rma-guest-customer-prefix").parent().parent().css("display", "");
        jQuery("select[name='wc_rma_settings[rma-guest-catch-all]']").parent().parent().css("display", "none");

    }
    else {
        jQuery("#rma-guest-customer-prefix").parent().parent().css("display", "none");
        jQuery("select[name='wc_rma_settings[rma-guest-catch-all]']").parent().parent().css("display", "");

    }

}