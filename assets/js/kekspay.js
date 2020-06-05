jQuery( function( $ ) {
  'use strict';

  /** On click show QR code if its hidden. */
  $( '[data-show]' ).on( 'click', function(e) {
    e.preventDefault();
    $( e.target.dataset.show ).slideDown();
  } );

  // Check kekspay status periodically and redirect to confirmation page when payment completes.
  function statusCheck() {
    if ( ! kekspayClientScript.order_id ) {
      return;
    }

    $.ajax({
      method: 'POST',
      url: kekspayClientScript.ajaxurl,
      dataType: 'json',
      data: {
        'action': 'kekspay_status_check',
        '_ajax_nonce': kekspayClientScript.nonce,
        'order_id': kekspayClientScript.order_id
      },
      success: function(response) {
        if ( 'processing' === response.status ) {
          window.location.href = kekspayClientScript.redirectSuccess;
        }
      },
      error: function(response) { // error logging
        console.log(response);
      }
    });
  };

  // Check on page load.
  statusCheck();

  // Check every 15s.
  let statusCheckInterval = setInterval(function() {
    statusCheck();
  }, 15000);

} );
