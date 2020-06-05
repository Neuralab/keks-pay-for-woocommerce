<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Kekspay_Connector' ) ) {
  /**
   * Kekspay_Connector class
   *
   * @since 0.1
   */
  class Kekspay_Connector {

    /**
     * Kekspay system refund endpoint.
     *
     * @var string
     */
    private $refund_ep = 'keksrefund';

    /**
     * Class constructor.
     */
    public function __construct() {
    }

    /**
     * Return an array for default args or false if failed to JSON encode.
     *
     * @param  array  $body
     * @return array|false
     */
    private function get_default_args( $body ) {
      $encoded_body = wp_json_encode( $body );
      if ( ! $encoded_body ) {
        return false;
      }

      return array(
        'body'    => $encoded_body,
        'headers' => array(
          'Content-Type' => 'application/json',
        ),
      );
    }

    /**
     * Trigger refund for given order and amount.
     * If failed to refund, method calls itself with $use_deprecated_id set to true
     * to try to refund order with old version of transaction ID.
     *
     * @param  WC_Order $order
     * @param  float    $amount
     *
     * @return array    [ 'success' => bool, 'message' => string ]
     */
    public function refund( $order, $amount ) {
      if ( 'erste-kekspay-woocommerce' !== $order->get_payment_method() ) {
        return;
      }

      $body = array(
        'bill_id' => Kekspay_Data::get_bill_id_by_order_id( $order->get_id() ),
        'keks_id' => $order->get_meta( 'kekspay_id' ),
        'tid'     => Kekspay_Data::get_settings( 'webshop-tid', true ),
        'amount'  => $amount,
      );

      $args     = $this->get_default_args( $body );
      $response = wp_remote_post( Kekspay_Data::get_kekspay_api_base() . $refund_ep, $args );
      Kekspay_Logger::log( 'Request to refund (' . $amount . ' ' . $order->get_currency() . ') via KEKS Pay payment gateway sent for order ' . $order->get_id(), 'notice' );

      $status_code = $this->get_response_status_code( $response );
      $is_success  = $status_code && ( $status_code >= 200 && $status_code < 300 );
      $wc_price    = wc_price( $amount, array( 'currency' => $order->get_currency() ) );

      if ( $is_success ) {
        $note = sprintf( __( 'Uspješno izvršen povrat %s via KEKS Pay.', 'kekspay' ), $wc_price );
        Kekspay_Logger::log( 'Successfully refunded (' . $amount . ' ' . $order->get_currency() . ') via KEKS Pay payment gateway for order ' . $order->get_id(), 'notice' );
        $order->add_order_note( $note );
        $order->update_meta_data( 'kekspay_status', (int) $order->get_remaining_refund_amount() ? 'refunded_partially' : 'refunded' );
        $order->save();

        return true;
      } else {
        $note = sprintf( __( 'Dogodila se greška pri povratu %s via KEKS Pay.', 'kekspay' ), $wc_price );
        Kekspay_Logger::log( 'Failed to refund (' . $amount . ' ' . $order->get_currency() . ') via KEKS Pay payment gateway for order ' . $order->get_id(), 'error' );
        $order->add_order_note( $note );
        $order->save();

        return false;
      }
    }
  }
}
