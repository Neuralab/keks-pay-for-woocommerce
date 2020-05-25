<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Kekspay_IPN' ) ) {
  /**
   * Kekspay_IPN class
   *
   * @since 0.1
   */
  class Kekspay_IPN {

    /**
     * Class constructor.
     */
    public function __construct() {
      add_action( 'woocommerce_api_' . Kekspay_Data::get_wc_endpoint(), array( $this, 'do_checkout_status' ) );
    }

    /**
     * Die with given message, encoded as JSON, and set HTTP response status.
     *
     * @param  mixed   $message
     * @param  integer $status_code Defaults to 200.
     */
    private function respond( $message, $status_code = 200 ) {
      status_header( $status_code );
      header( 'content-type: application/json; charset=utf-8' );

      $encoded_message = wp_json_encode( $message );

      if ( ! $encoded_message ) {
        Kekspay_Logger::log( 'Failed to encode API response message.', 'error' );
        $encoded_message = -1;
      }

      die( $encoded_message );
    }

    /**
     * Die with error and given message, encoded as JSON, and set HTTP response status.
     *
     * @param string $message Message description about the failure of request.
     */
    private function respond_error( $message ) {
      status_header( 400 );
      header( 'content-type: application/json; charset=utf-8' );

      $encoded_message = wp_json_encode(
        array(
          'status'  => -1,
          'message' => $message,
        )
      );

      if ( ! $encoded_message ) {
        Kekspay_Logger::log( 'Failed to encode API response message.', 'error' );
        $encoded_message = -1;
      }

      die( $encoded_message );
    }

    /**
     * Return assoc array of parameters from either 'php://input' (POST request
     * body), or $_REQUEST.
     *
     * @return array $params
     */
    private function resolve_params() {
      $params = json_decode( file_get_contents( 'php://input' ), true );
      if ( empty( $params ) ) {
        // NOTE: external request, signature is checked in the calling method to determine validity.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $params = $_REQUEST;
        // phpcs:enable
      }

      if ( $params ) {
        return array_map(
          function( $item ) {
            return filter_var( $item, FILTER_SANITIZE_STRING );
          },
          $params
        );
      }

      return false;
    }

    /**
     * Should be used as a callback URL for KEKS Pay API checkout request.
     */
    public function do_checkout_status() {
      $params = $this->resolve_params();
      // Check if any parametars are received.
      if ( ! $params ) {
        Kekspay_Logger::log( 'Missing parameters in the request from IPN.', 'error' );
        $this->respond_error( 'Missing parameters.' );
      }

      // Check if required params are recieved.
      foreach ( array( 'bill_id', 'status', 'signature' ) as $required_param ) {
        if ( ! isset( $params[ $required_param ] ) ) {
          Kekspay_Logger::log( 'Missing ' . $required_param . ' parametar in the request from IPN.', 'error' );
          $this->respond_error( 'Missing or corrupt parametar ' . $required_param . '.' );
        }
      }

      // Extract order id and check if order exists.
      $order_id = Kekspay_Data::get_order_id_by_bill_id( $params['bill_id'] );
      $order    = wc_get_order( $order_id );
      if ( ! $order ) {
        Kekspay_Logger::log( 'Failed to find order ' . $order_id . ' for status checkout API endpoint.', 'error' );
        $this->respond_error( 'Couldn\'t find corresponding order ' . $params['bill_id'] . '.' );
      }

      // Verify signature recieved.
      if ( ! hash_equals( Kekspay_Data::get_signature( $order ), $params['signature'] ) ) {
        Kekspay_Logger::log( 'Failed to verify signature ' . $params['signature'], 'error' );
        $this->respond_error( 'Signature mismatch, failed to verify.' );
      }

      if ( (int) $params['status'] !== 0 ) {
        Kekspay_Logger::log( 'Failed to complete payment for order ' . $order_id . ', message: ' . $params['message'], 'error' );
        $order->add_meta_data( 'kekspay_status', strtolower( $params['message'] ), true );
        $order->add_order_note( __( 'Payment failed via KEKS Pay.', 'kekspay' ) );
        $order->save();
      } else {
        Kekspay_Logger::log( 'Successfully completed payment for order ' . $order_id, 'notice' );
        $order->set_status( 'processing', __( 'Payment completed via KEKS Pay.', 'kekspay' ) );
        $order->add_meta_data( 'kekspay_status', strtolower( $params['message'] ), true );
        $order->save();
      }

      $this->respond(
        array(
          'status'  => 0,
          'message' => 'Accepted',
        )
      );
    }
  }
}

new Kekspay_IPN();
