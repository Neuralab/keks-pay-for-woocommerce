<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Kekspay_Order_Admin' ) ) {
  /**
   * Kekspay_Order_Admin class
   *
   * @since 0.1
   */
  class Kekspay_Order_Admin {

    /**
     * Class constructor.
     */
    public function __construct() {
      add_action( 'admin_notices', array( $this, 'display_test_order_notice' ), 100 );
    }

    /**
     * Display WordPress warning notice if current order (admin view) is processed in sandbox/test mode.
     *
     * @return void
     */
    public function display_test_order_notice() {
      if ( ! is_admin() ) {
        return;
      }

      $screen = get_current_screen();
      if ( 'post' === $screen->base && 'shop_order' === $screen->id ) {
        $order = wc_get_order( get_the_ID() );
        if ( ! is_a( $order, 'WC_Order' ) ) {
          return;
        }

        if ( Kekspay_Data::order_test_mode( $order ) ) {
          $class   = 'notice notice-warning';
          $message = __( 'Narudžba kreirana koristeći KEKS Pay u testnom načinu rada.', 'kekspay' );

          printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
      }

    }

  }
}

new Kekspay_Order_Admin();
