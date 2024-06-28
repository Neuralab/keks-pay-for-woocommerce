jQuery( function( $ ) {
  'use strict';

  /** Swap visibility of test and live credential fields depending on test mode on/off */
  $( '#woocommerce_erste-kekspay-woocommerce_in-test-mode' ).on( 'change', function(e) {
    $('[id*="webshop"]').closest('tr').toggle(! e.target.checked);
    $('[id*="test-webshop"]').closest('tr').toggle(e.target.checked);
  } ).trigger( 'change' );

  $( '.kekspay-status-refresh' ).on( 'click', function(e) {
    e.preventDefault();

    var element = $(this);

    element.css("pointer-events", "none");

    var confirmed = window.confirm('Refreshing this order will contact kekspay system and attempt to sync this order with kekpay records. Would you like to continue?');
    if ( ! confirmed ) {
      element.css("pointer-events", "auto");
      return;
    }
  
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : kekspayAdminScript.ajaxurl,
      data : {
        action: 'check_kekspay_status',
        order_id: $('#post_ID').val(),
        kekspay_status_refresh: $('input[name="kekspay_status_refresh"]').val(),
        nonce: $('input[name="_wp_http_referer"]').val()
      },

      success: function(response) {
        if(response.success) {
          alert('Status synced successfully!') && location.reload();
        }
        else {
          alert('Status sync failed, please try again later or check the logs for more information.');
        }
        element.css("pointer-events", "auto");
      }
    })
  })

});