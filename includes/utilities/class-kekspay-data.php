<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

use chillerlan\QRCode\{ QRCode, QROptions };

if ( ! class_exists( 'Kekspay_Data' ) ) {
  /**
   * Kekspay_Data class
   *
   * @since 0.1
   */
  class Kekspay_Data {
    /**
     * Whether or not logging is enabled.
     *
     * @var array
     */
    private static $settings = array();

    /**
     * Set endpoint for webshop api.
     *
     * @var string
     */
    private static $endpoint = 'wc-kekspay';

    /**
     * Set url of kekspay system.
     *
     * @var string
     */
    private static $kekspay = 'https://kekspay.hr/';

    /**
     * Init data class.
     */
    public function __construct() {
      self::set_settings();
    }

    /**
     * Load gateway settings from the database.
     *
     * @return void
     */
    public static function set_settings() {
      self::$settings = get_option( 'woocommerce_' . KEKSPAY_PLUGIN_ID . '_settings', array() );
    }

    /**
     * Returns if payment gateway is enabled.
     *
     * @return bool
     */
    public static function enabled() {
      if ( empty( self::$settings ) ) {
        self::set_settings();
      }

      return 'yes' === self::$settings['enabled'];
    }

    /**
     * Returns true if test mode is turned on, false otherwise.
     *
     * @return bool
     */
    public static function test_mode() {
      if ( empty( self::$settings ) ) {
        self::set_settings();
      }

      return 'yes' === self::$settings['in-test-mode'];
    }

    /**
     * Returns true if order was created in test mode, false otherwise.
     *
     * @return bool
     */
    public static function order_test_mode( $order ) {
      return 'yes' === $order->get_meta( 'kekspay_test_mode' );
    }

    /**
     * Returns true if required keys in gateways settings are set, false otherwise.
     *
     * @return bool
     */
    public static function required_keys_set() {
      if ( ! self::get_settings( 'webshop-cid', true ) || ! self::get_settings( 'webshop-tid', true ) || ! self::get_settings( 'webshop-secret-key', true ) ) {
        return false;
      }

      return true;
    }

    /**
     * Returns true if currency is HRK.
     *
     * @return bool
     */
    public static function currency_supported() {
      return 'HRK' === get_woocommerce_currency();
    }

    /**
     * Fetch settings for use.
     *
     * @param string $name       Name of specific setting to fetch.
     * @param bool   $test_check Whether to check if test mode is on to fetch test version of the setting.
     *
     * @return array/string
     */
    public static function get_settings( $name = false, $test_check = false ) {
      if ( empty( self::$settings ) ) {
        self::set_settings();
      }

      if ( $name ) {
        if ( $test_check ) {
          $name = self::test_mode() ? 'test-' . $name : $name;
        }

        return isset( self::$settings[ $name ] ) ? self::$settings[ $name ] : null;
      }

      return self::$settings;
    }

    /**
     * Return gateway endpoint on wc api.
     *
     * @param bool $absolute Wheter to fetch full url with endpoint or only the endpoint.
     *
     * @return string
     */
    public static function get_wc_endpoint( $absolute = false ) {
      return $absolute ? untrailingslashit( WC()->api_request_url( self::$endpoint ) ) : self::$endpoint;
    }

    /**
     * Return gateway url for KEKS Pay servers.
     *
     * @return string
     */
    public static function get_kekspay_endpoint( $trailingslash = false ) {
      $endpoint = self::$kekspay . ( self::test_mode() ? 'sokolpay' : 'pay' );
      return $trailingslash ? trailingslashit( $endpoint ) : $endpoint;
    }

    /**
     * Creates endpoint message for settings.
     *
     * @return string
     */
    public static function get_settings_endpoint_field() {
      return sprintf(
        __( 'Please add this webhook endpoint %1$s to your %2$s KEKS Pay account settings %3$s, which will enable your webshop to recieve payment notifications from KEKS Pay.', 'kekspay' ),
        '<strong><code class="kekspay-webhook">' . self::get_wc_endpoint( true ) . '</code></strong>',
        '<a href="https://kekspay.hr" target="_blank">',
        '</a>'
      );
    }

    /**
     * Creates unique bill id using webshop cid and order id.
     *
     * @return string
     */
    public static function get_bill_id_by_order_id( $order_id ) {
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        Kekspay_Logger::log( 'Could not fetch key for order ' . $order_id . ' while generating bill_id.', 'error' );
        return false;
      }

      return str_replace( 'wc_order_', self::get_settings( 'webshop-cid', true ), $order->get_order_key() );
    }

    /**
     * Extract order id from kekspay bill id.
     *
     * @return string
     */
    public static function get_order_id_by_bill_id( $bill_id ) {
      return wc_get_order_id_by_order_key( str_replace( self::get_settings( 'webshop-cid', true ), 'wc_order_', $bill_id ) );
    }

    /**
     * Gathers all data needed for payment and formats it as array.
     *
     * @param  object $order Order from which to extract data.
     *
     * @return array/bool    Extracted data as array, false on failure.
     */
    public static function get_sell_data( $order ) {
      if ( ! self::required_keys_set() ) {
        Kekspay_Logger::log( 'Payment gateway setup incomplete, please enter all requested data to gateway settings.', 'error' );
        return false;
      }

      $sell = array(
        'qr_type'          => 1,
        'cid'              => self::get_settings( 'webshop-cid', true ),
        'tid'              => self::get_settings( 'webshop-tid', true ),
        'bill_id'          => self::get_bill_id_by_order_id( $order->get_id() ),
        'amount'           => $order->get_total(),
        'store'            => self::get_settings( 'store-msg' ),
        'success_callback' => $order->get_checkout_order_received_url(),
        'failed_callback'  => $order->get_cancel_order_url_raw(),
      );

      return $sell;
    }

    /**
     * Delete gateway settings. Return true if option is successfully deleted or
     * false on failure or if option does not exist.
     *
     * @return bool
     */
    public static function delete_settings() {
      return delete_option( 'woocommerce_' . KEKSPAY_PLUGIN_ID . '_settings' ) && delete_option( 'kekspay_plugins_check_required' );
    }

    /**
     * Return signature created from the provided data and secret.
     *
     * @param  string $order Order from which to extract data for signature.
     *
     * @return string
     */
    public static function get_signature( $order ) {
      $secret = self::get_settings( 'webshop-secret-key', true );

      $payload = wp_json_encode( self::get_sell_data( $order ) );

      $expected_signature = hash_hmac( 'sha256', $payload, $secret );

      return $expected_signature;
    }

  }
}
