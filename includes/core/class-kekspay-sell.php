<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

use chillerlan\QRCode\{ QRCode, QROptions };

if ( ! class_exists( 'Kekspay_Sell' ) ) {
  /**
   * Kekspay_Sell class
   *
   * @since 0.1
   */
  class Kekspay_Sell {

    /**
     * Class constructor.
     */
    public function __construct() {
      require_once( KEKSPAY_DIR_PATH . 'vendor/autoload.php' );
    }

    /**
     * Create url for mobile app.
     *
     * @param  object $order Order for which to create url.
     *
     * @return string        Url for mobile app.
     */
    public function get_sell_url( $order ) {
      return add_query_arg( Kekspay_Data::get_sell_data( $order ), Kekspay_Data::get_kekspay_endpoint() );
    }

    /**
     * Create QR code for mobile app.
     *
     * @param  object $order Order for which to create QR code.
     *
     * @return string        base64 encoded png file.
     */
    public function get_sell_qr( $order ) {
      $data = Kekspay_Data::get_sell_data( $order );

      $options = new QROptions(
        array(
          'version'       => 15,
          'quietzoneSize' => 4,
          'eccLevel'      => QRCode::ECC_L,
        )
      );

      $qrcode = new QRCode( $options );

      return $qrcode->render( wp_json_encode( $data ) );
    }

    /**
     * Format QR Code with html for display.
     *
     * @param  object $order Order for which to fetch QR code.
     *
     * @return string        Path to or base64 encoded QR code for mobile app wrapped in img tags.
     */
    public function display_sell_qr( $order ) {
      return apply_filters( 'kekspay_sell_qr_code', '<img id="kekspay-qr-code" src="' . $this->get_sell_qr( $order ) . '">' );
    }

    /**
     * Format Keks pay url with html for display
     *
     * @param  object $order Order for which to get the pay url.
     *
     * @return string        Link for payment.
     */
    public function display_sell_url( $order ) {
      $attrs = apply_filters(
        'kekspay_sell_link_attributes',
        array(
          'id'     => 'kekspay-pay-url',
          'class'  => 'button',
          'target' => '_blank',
          'label'  => __( 'Pay via app', 'kekspay' ),
        )
      );

      return apply_filters( 'kekspay_sell_link', '<a id="' . esc_attr( $attrs['id'] ) . '" href="' . $this->get_sell_url( $order ) . '" class="' . esc_attr( $attrs['class'] ) . '" target="' . esc_attr( $attrs['target'] ) . '">' . esc_html( $attrs['label'] ) . '</a>' );
    }

  }
}
