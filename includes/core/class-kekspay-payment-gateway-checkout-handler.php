<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Kekspay_Payment_Gateway_Checkout_Handler' ) ) {
  /**
   * Kekspay_Payment_Gateway_Checkout_Handler class
   *
   * @since 0.1
   */
  class Kekspay_Payment_Gateway_Checkout_Handler {

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $keks_api_url;

    /**
     * Class constructor.
     */
    public function __construct() {
      require_once( KEKSPAY_DIR_PATH . 'includes/utilities/class-kekspay-logger.php' );
      require_once( KEKSPAY_DIR_PATH . 'vendor/phpqrcode/qrlib.php' );

      $this->settings     = WC_Kekspay::get_gateway_settings();
      $this->logger       = new Kekspay_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );
      $this->keks_api_url = 'https://kekspay.hr/' . ( $this->settings['in-test-mode'] ? 'sokolpay' : 'pay' );
    }

    /**
     * Should be used as a callback URL for KEKS Pay API checkout request.
     */
    public function get_payment_data( $order ) {
      return array(
        'qr_type' => 1,
        'cid'     => $this->settings['webshop-cid'],
        'tid'     => $this->settings['webshop-tid'],
        'bill_id' => $order->get_order_key(),
        'amount'  => $order->get_total(),
        'store'   => get_bloginfo( 'name' ),
      );
    }

    /**
     * Should be used as a callback URL for KEKS Pay API checkout request.
     */
    public function generate_url( $order ) {
      return '<a href="' . add_query_arg( $this->get_payment_data( $order ), $this->keks_api_url ) . '" class="button" target="_blank">' . __( 'Pay', 'kekspay' ) . '</a>';
    }

    /**
     * Should be used as a callback URL for KEKS Pay API checkout request.
     */
    public function generate_qr( $order ) {
      $data        = $this->get_payment_data( $order );
      $upload_path = wp_upload_dir();
      $qr_dir_name = $upload_path['basedir'] . '/qr-codes/';
      $qr_dir      = file_exists( $qr_dir_name ) ? $qr_dir_name : wp_mkdir_p( $qr_dir_name );
      $qr_filename = $qr_dir . 'wc-order-' . $order->get_id() . '.svg';

      $qrcode = QRcode::svg( wp_json_encode( $data ), false, $qr_filename, 250 );

      return $qrcode;
    }

  }
}
