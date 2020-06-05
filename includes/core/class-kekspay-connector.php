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
        'headers' => array(
          'Content-Type' => 'application/json',
        ),
        'method'  => 'POST',
        'body'    => $encoded_body,
        'cookies' => [],
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

      $wc_price = wc_price( $amount, array( 'currency' => $order->get_currency() ) );

      $response = wp_safe_remote_post( Kekspay_Data::get_kekspay_api_base() . '/keksrefund', $this->get_default_args( $body ) );
      Kekspay_Logger::log( 'Request sent to refund order ' . $order->get_id() . ' (' . $amount . $order->get_currency() . ') via KEKS Pay.', 'info' );

      $status_code = wp_remote_retrieve_response_code( $response );
      if ( $status_code < 200 || $status_code > 299 ) {
        Kekspay_Logger::log( 'Refund for order ' . $order->get_id() . ' (' . $amount . $order->get_currency() . ') via KEKS Pay failed, does not have a success status code.', 'error' );
        return false;
      }

      $body = wp_remote_retrieve_body( $response );
      if ( ! $body ) {
        Kekspay_Logger::log( 'Refund for order ' . $order->get_id() . ' (' . $amount . $order->get_currency() . ') via KEKS Pay failed, body corrupted or missing.', 'error' );
        return false;
      }

      $response_data = json_decode( $body );

      if ( isset( $response_data->status ) && 0 === $response_data->status ) {
        $note = sprintf( __( 'Uspješno izvršen povrat %s via KEKS Pay.', 'kekspay' ), $wc_price );
        Kekspay_Logger::log( 'Successfully refunded order ' . $order->get_id() . ' (' . $amount . $order->get_currency() . ') via KEKS Pay. Setting status refunded.', 'info' );
        $order->add_order_note( $note );
        $order->update_meta_data( 'kekspay_status', (int) $order->get_remaining_refund_amount() ? 'refunded_partially' : 'refunded' );
        $order->save();

        return true;
      } else {
        $note = sprintf( __( 'Dogodila se greška pri povratu %s via KEKS Pay.', 'kekspay' ), $wc_price );
        $message = isset( $response_data->message ) ? $response_data->message : '';
        Kekspay_Logger::log( 'Failed to refund order ' . $order->get_id() . ' (' . $amount . $order->get_currency() . ') via KEKS Pay. Message: ' . $message, 'error' );
        $order->add_order_note( $note );
        $order->save();

        return false;
      }
    }
  }
}
