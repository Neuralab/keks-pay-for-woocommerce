jQuery( function( $ ) {
  'use strict';

  /** Swap visibility of test and live credential fields depending on test mode on/off */
  $( '#woocommerce_erste-kekspay-woocommerce_in-test-mode' ).on( 'change', function(e) {
    $('[id*="webshop"]').closest('tr').toggle(! e.target.checked);
    $('[id*="test-webshop"]').closest('tr').toggle(e.target.checked);
  } ).trigger( 'change' );

} );
