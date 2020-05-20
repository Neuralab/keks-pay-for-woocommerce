<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

use chillerlan\QRCode\{QRCode, QROptions};

if ( ! class_exists( 'Kekspay_App_Handler' ) ) {
  /**
   * Kekspay_App_Handler class
   *
   * @since 0.1
   */
  class Kekspay_App_Handler {

    /**
     * Instance of Kekspay Data class.
     *
     * @var Kekspay_Data
     */
    private $data;

    /**
     * WordPress upload directory info.
     *
     * @var array
     */
    private $uploads;

    /**
     * Class constructor.
     */
    public function __construct( $data, $logger ) {
      require_once( KEKSPAY_DIR_PATH . 'vendor/autoload.php' );

      $this->set_qr_dir();

      $this->data   = $data;
      $this->logger = $logger;
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
        'cid'     => $this->data->get_settings( 'in-test-mode' ) ? $this->data->get_settings( 'test-webshop-cid' ) : $this->data->get_settings( 'webshop-cid' ),
        'tid'     => $this->data->get_settings( 'in-test-mode' ) ? $this->data->get_settings( 'test-webshop-tid' ) : $this->data->get_settings( 'webshop-tid' ),
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
      return add_query_arg( $this->get_payment_data( $order ), $this->data->get_kekspay_endpoint() );
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

    /**
     * Format QR Code with html for display.
     *
     * @param  object $order Order for which to fetch QR code.
     * @return string        Path to or base64 encoded QR code for mobile app wrapped in img tags.
     */
    public function display_kekspay_qr( $order ) {
      return apply_filters( 'kekspay_qr_code_image', '<img id="kekspay-qr-code" src="' . $this->get_qr_code( $order ) . '">' );
    }

    /**
     * Format Keks pay url with html for display
     *
     * @param  object $order Order for which to get the pay url.
     * @return string        Link for payment.
     */
    public function display_kekspay_url( $order ) {
      $attrs = apply_filters(
        'kekspay_pay_link_attributes',
        array(
          'id'     => 'kekspay-pay-url',
          'class'  => 'button',
          'target' => '_blank',
          'label'  => __( 'Pay via app', 'kekspay' ),
        )
      );

      return apply_filters( 'kekspay_pay_link', '<a id="' . esc_attr( $attrs['id'] ) . '" href="' . $this->get_url( $order ) . '" class="' . esc_attr( $attrs['class'] ) . '" target="' . esc_attr( $attrs['target'] ) . '">' . esc_html( $attrs['label'] ) . '</a>' );
    }

  }
}
