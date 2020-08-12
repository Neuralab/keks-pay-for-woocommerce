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
      add_action( 'wp_ajax_kekspay_status_check', array( $this, 'kekspay_status_check' ) );
      add_action( 'wp_ajax_nopriv_kekspay_status_check', array( $this, 'kekspay_status_check' ) );
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

      wp_die( $encoded_message );
    }

    /**
     * Return assoc array of parameters from either 'php://input' (POST request body), or $_REQUEST.
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

      if ( ! $params ) {
        return false;
      }

      return array_map(
        function( $item ) {
          return filter_var( $item, FILTER_SANITIZE_STRING );
        },
        $params
      );
    }

    /**
     * Check if order status has changed and return the new status
     *
     * @return void
     */
    public function kekspay_status_check() {
      check_ajax_referer( 'kekspay_advice_status' );

      $order  = new WC_Order( filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT ) );
      $status = $order->get_meta( 'kekspay_status' );

      $response = array(
        'status'   => $status,
        'redirect' => null,
      );

      if ( 'failed' === $status ) {
        wc_add_notice( sprintf( __( 'Nešto je pošlo po zlu pri pokušaju naplate vaše narudžbe (#%d) putem KEKS Pay servisa, molimo pokušajte ponoviti narudžbu ili kontaktirajte administratora web trgovine za više informacija.' ), $order->get_id() ), 'error' );
        $response['redirect'] = $order->get_cancel_order_url_raw();
      } elseif ( 'success' === $status ) {
        $response['redirect'] = $order->get_checkout_order_received_url();
      }

      $this->respond( $response );
    }

    /**
     * Should be used as a callback URL for KEKS Pay API checkout request.
     */
    public function do_checkout_status() {
      // Check received parametars.
      $params = $this->resolve_params();
      Kekspay_Logger::log( wp_json_encode( $params ), 'info' );
      // Check if any parametars are received.
      if ( ! $params ) {
        Kekspay_Logger::log( 'Missing parameters in the request for IPN.', 'error' );
        $this->respond_error( 'Missing parameters.' );
      }

      // Check if required params are recieved.
      foreach ( array( 'bill_id', 'status', 'keks_id', 'tid' ) as $required_param ) {
        if ( ! isset( $params[ $required_param ] ) ) {
          Kekspay_Logger::log( 'Missing ' . $required_param . ' parametar in the request for IPN.', 'error' );
          $this->respond_error( 'Missing or corrupt parametars.' );
        }
      }

      // Extract order id and check if order exists.
      $order_id = Kekspay_Data::get_order_id_by_bill_id( $params['bill_id'] );
      $order    = wc_get_order( $order_id );
      if ( ! $order ) {
        Kekspay_Logger::log( 'Failed to find order ' . $params['bill_id'] . ' from the request for IPN.', 'error' );
        $this->respond_error( 'Couldn\'t find corresponding order ' . $params['bill_id'] . '.' );
      }

      // Check if recieved TID matches the webshop TID.
      if ( $params['tid'] !== Kekspay_Data::get_settings( 'webshop-tid', true ) ) {
        Kekspay_Logger::log( 'Recieved TID ' . $params['tid'] . ' does not match webshop TID in the request for IPN.', 'error' );
        $order->add_meta_data( 'kekspay_status', 'failed', true );
        $this->respond_error( 'Webshop verification failed, mismatch for TID ' . $params['tid'] . '.' );
      }

      // Verify token authorization.
      if ( ! $this->verify_kekspay_token() ) {
        Kekspay_Logger::log( 'Failed to verify token.', 'error' );
        $order->add_meta_data( 'kekspay_status', 'failed', true );
        $this->respond_error( 'Webshop autentication failed, token mismatch.' );
      }

      if ( (int) $params['status'] === 0 ) {
        Kekspay_Logger::log( 'KEKS Pay successfully completed payment for order ' . $order_id . ', setting status to ' . $params['message'], 'info' );
        $order->set_status( 'processing', __( 'Narudžba uspješno plaćena putem KEKS Pay aplikacije.', 'kekspay' ) );
        $order->add_meta_data( 'kekspay_status', 'success', true );
        $order->add_meta_data( 'kekspay_id', $params['keks_id'], true );
        $order->save();
      } else {
        Kekspay_Logger::log( 'KEKS Pay failed to complete payment for order ' . $order_id . ', message: ' . $params['message'], 'error' );
        $order->add_meta_data( 'kekspay_status', 'failed', true );
        $order->add_order_note( __( 'Dogodila se greška pri naplati putem KEKS Pay aplikacije.', 'kekspay' ) );
        $order->save();
      }

      $this->respond(
        array(
          'status'  => 0,
          'message' => 'Accepted',
        )
      );
    }

    /**
     * Compares tokens to verify valid request.
     *
     * @return bool
     */
    private function verify_kekspay_token() {
      $token = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? filter_var( $_SERVER['HTTP_AUTHORIZATION'], FILTER_SANITIZE_STRING ) : false;

      if ( ! $token ) {
        Kekspay_Logger::log( 'Failed to recieve authentication token.', 'error' );
        $this->respond_error( 'Authentication token missing, failed to verify.' );
      }

      return hash_equals( Kekspay_Data::get_settings( 'auth-token' ), str_replace( 'Token ', '', $token ) );
    }
  }
}

new Kekspay_IPN();
