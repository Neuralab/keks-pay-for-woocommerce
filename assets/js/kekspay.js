jQuery( function( $ ) {
  'use strict';

  $( '[qr-code-trigger]' ).on( 'click', function(e) {
    e.preventDefault();
    $('.kekspay-qr').slideDown();
  } );

} );
