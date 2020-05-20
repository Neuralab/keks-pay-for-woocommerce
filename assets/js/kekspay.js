jQuery( function( $ ) {
  'use strict';

  /** On click show QR code if its hidden. */
  $( '[qr-code-trigger]' ).on( 'click', function(e) {
    e.preventDefault();
    $( '.kekspay-qr' ).slideDown();
  } );

} );
