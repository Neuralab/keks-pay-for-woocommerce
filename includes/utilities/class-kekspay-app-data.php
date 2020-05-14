<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

use chillerlan\QRCode\{QRCode, QROptions};

if ( ! class_exists( 'Kekspay_App_Data' ) ) {
  /**
   * Kekspay_App_Data class
   *
   * @since 0.1
   */
  class Kekspay_App_Data {

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

    /**
     * Url of kekspay service.
     *
     * @var string
     */
    private $keks_api_url;

    /**
     * Url of kekspay service.
     *
     * @var array
     */
    private $uploads;

    /**
     * Class constructor.
     */
    public function __construct() {
      require_once( KEKSPAY_DIR_PATH . 'vendor/autoload.php' );
      require_once( KEKSPAY_DIR_PATH . 'includes/utilities/class-kekspay-logger.php' );

      $this->set_qr_dir();

      $this->settings     = WC_Kekspay::get_gateway_settings();
      $this->logger       = new Kekspay_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );
      $this->keks_api_url = 'https://kekspay.hr/' . ( $this->settings['in-test-mode'] ? 'sokolpay' : 'pay' );
    }

    /**
     * Create directory in wp uploads folder for QR codes.
     *
     * @return void
     */
    public function set_qr_dir() {
      $this->uploads           = wp_upload_dir();
      $qr_dir                  = $this->uploads['basedir'] . '/qr-codes/';
      $this->uploads['qr_url'] = $this->uploads['baseurl'] . '/qr-codes/';
      $this->uploads['qr_dir'] = file_exists( $qr_dir ) ? $qr_dir : wp_mkdir_p( $qr_dir );
    }

    /**
     * Gathers all data needed for payment and formats it as array.
     *
     * @param  object $order Order from which to extract data.
     * @return array         Extracted data as array.
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
     * Create url for mobile app.
     *
     * @param  object $order Order for which to create url.
     * @return string        Url for mobile app.
     */
    public function get_url( $order ) {
      return add_query_arg( $this->get_payment_data( $order ), $this->keks_api_url );
    }

    /**
     * Create QR code for mobile app.
     *
     * @param  object $order    Order for which to create QR code.
     * @param  string $filepath Path where to save the QR code png file.
     * @return string           base64 encoded png file.
     */
    public function generate_qr_code( $order, $filepath ) {
      $data = $this->get_payment_data( $order );

      $options = new QROptions( [
        'version'       => 15,
        'quietzoneSize' => 4,
        'eccLevel'      => QRCode::ECC_L,
      ] );

      $qrcode = new QRCode( $options );

      return $qrcode->render( wp_json_encode( $data ), $filepath );
    }

    /**
     * Fetch created QR code or generate new.
     *
     * @param  object $order Order for which to fetch QR code.
     * @return string        Path to or base64 encoded QR code for mobile app.
     */
    public function get_qr_code( $order ) {
      $qr_name = 'wc-order-' . $order->get_id() . '.png';
      $qr_path = $this->uploads['qr_dir'] . $qr_name;
      $qr_url  = $this->uploads['qr_url'] . $qr_name;

      return file_exists( $qr_path ) ? $qr_url : $this->generate_qr_code( $order, $qr_path );
    }

  }
}
