jQuery( function( $ ) {
  'use strict';

  /** On click show QR code if its hidden. */
  $( '[data-show]' ).on( 'click', function(e) {
    e.preventDefault();
    $( e.target.dataset.show ).slideDown();
  } );

} );
