<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Kekspay_Payment_Gateway_IPN' ) ) {
  /**
   * Kekspay_Payment_Gateway_IPN class
   *
   * @since 0.1
   */
  class Kekspay_Payment_Gateway_IPN {

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

    /**
     * Webhook endpoint.
     *
     * @var array
     */
    private static $endpoint = 'wc-kekspay';

    /**
     * Class constructor.
     */
    public function __construct() {
      require_once( KEKSPAY_DIR_PATH . '/includes/utilities/class-kekspay-logger.php' );

      $this->settings = WC_Kekspay::get_gateway_settings();
      $this->logger   = new Kekspay_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );
    }

    /**
     * Initialize all the needed hook methods.
     */
    public function register() {
      add_action( 'woocommerce_api_' . self::$endpoint, array( $this, 'do_checkout_status' ) );
    }

    /**
     * Return full URL of the 'kekspay' endpoint.
     *
     * @return string
     */
    public static function get_webhook_url() {
      return untrailingslashit( WC()->api_request_url( self::$endpoint ) );
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
        $this->logger->log( 'Failed to encode API response message.', 'error' );
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
        $this->logger->log( 'Failed to encode API response message.', 'error' );
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

      return $params;
    }

    /**
     * Should be used as a callback URL for KEKS Pay API checkout request.
     */
    public function do_checkout_status() {
      $params = $this->resolve_params();
      if ( empty( $params ) ) {
        $this->logger->log( 'Missing params for status checkout API endpoint.', 'error' );
        $this->respond_error( 'Missing parameters.' );
      }

      // Check if required params are recieved.
      foreach ( array( 'bill_id', 'status' ) as $required_param ) {
        if ( ! isset( $params[ $required_param ] ) ) {
          $this->logger->log( 'Missing ' . $required_param . ' param for status checkout API endpoint.', 'error' );
          $this->respond_error( 'Missing or corrupt parametar ' . $required_param . '.' );
        }
      }

      $order_id = wc_get_order_id_by_order_key( $params['bill_id'] );
      if ( empty( $order_id ) ) {
        $order_id = intval( $params['bill_id'] );
      }

      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' for status checkout API endpoint.', 'error' );
        $this->respond_error( 'Couldn\'t find corresponding order ' . $params['bill_id'] . '.' );
      }

      $order->set_status( 'processing', __( 'Payment completed via KEKS Pay.', 'kekspay' ) );
      $order->save();

      $this->respond(
        array(
          'status'  => 0,
          'message' => 'Accepted',
        )
      );
    }
  }
}
