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
     * Set base url of kekspay pay.
     *
     * @var string
     */
    private static $kekspay_pay = 'https://kekspay.hr/';

    /**
     * Set base url of kekspay API.
     *
     * @var string
     */
    private static $kekspay_api = 'https://ewa.erstebank.hr/tps/';

    /**
     * Set base test url of kekspay API.
     *
     * @var string
     */
    private static $test_kekspay_api = 'https://dttlinuxdev.erste.hr/tps/';

    /**
     * Init data class.
     */
    public function __construct() {
      self::load_settings();
    }

    /**
     * Load gateway settings from the database.
     *
     * @return void
     */
    public static function load_settings() {
      self::$settings = get_option( 'woocommerce_' . KEKSPAY_PLUGIN_ID . '_settings', array() );
    }

    /**
     * Save gateway settings to the database.
     *
     * @return void
     */
    public static function set_settings( $settings ) {
      update_option( 'woocommerce_' . KEKSPAY_PLUGIN_ID . '_settings', array_merge( self::get_settings(), $settings ) );
    }

    /**
     * Returns if payment gateway is enabled.
     *
     * @return bool
     */
    public static function enabled() {
      if ( empty( self::$settings ) ) {
        self::load_settings();
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
        self::load_settings();
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
      if ( ! self::get_settings( 'webshop-cid', true ) || ! self::get_settings( 'webshop-tid', true ) ) {
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
        self::load_settings();
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
     * Return auth token or generate if there is none.
     *
     * @return string
     */
    public static function get_auth_token() {
      $token = self::get_settings( 'auth-token' );
      if ( ! $token ) {
        $token = hash_hmac( 'sha256', bin2hex( openssl_random_pseudo_bytes( 64 ) ), site_url() );

        self::set_settings( [ 'auth-token' => $token ] );
      }

      return $token;
    }

    /**
     * Return auth token or generate if there is none.
     *
     * @param bool $absolute Wheter to fetch full url with endpoint or only the endpoint.
     *
     * @return string
     */
    public static function get_svg( $svg, $attrs = [] ) {
      if ( ! $svg || ! file_exists( KEKSPAY_DIR_PATH . '/assets/img/' . $svg . '.svg' ) ) {
        return false;
      }

      return '<img ' . implode( ' ', $attrs ) . ' src="' . KEKSPAY_DIR_URL . '/assets/img/' . $svg . '.svg">';
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
    public static function get_kekspay_pay_base( $trailingslash = false ) {
      $endpoint = self::$kekspay_pay . ( self::test_mode() ? 'sokolpay' : 'pay' );
      return $trailingslash ? trailingslashit( $endpoint ) : $endpoint;
    }

    /**
     * Return gateway url for KEKS Pay servers.
     *
     * @return string
     */
    public static function get_kekspay_api_base() {
      return self::test_mode() ? self::$test_kekspay_api : self::$kekspay_api;
    }

    /**
     * Creates endpoint message for settings.
     *
     * @return string
     */
    public static function get_settings_endpoint_field() {
      return sprintf(
        __( 'Adresa za primanje obavijesti o stanju naplate: %1$s. Nakon pohrane unutar KEKS Pay sustava omogućava ovoj trgovini primanje obavijesti o stanju naplate. %2$s Kontakt %3$s', 'kekspay' ),
        '<b><code class="kekspay-webhook">' . self::get_wc_endpoint( true ) . '</code></b>',
        '<a href="mailto:kekspay@erstebank.hr">',
        '</a>'
      );
    }


    /**
     * Creates endpoint message for settings.
     *
     * @return string
     */
    public static function get_settings_token_field() {
      return sprintf(
        __( 'Sigurnosni token Web trgovine: %1$s. Nakon pohrane unutar KEKS Pay sustava korišten je za autentikaciju Web trgovine unutar KEKS Pay sustava. %2$s Kontakt %3$s', 'kekspay' ),
        '<b><code>' . self::get_auth_token() . '</code></b>',
        '<a href="mailto:kekspay@erstebank.hr">',
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
     * @param  object $order     Order from which to extract data.
     * @param  bool   $callbacks Whether to include callback urls or not.
     *
     * @return array/bool    Extracted data as array, false on failure.
     */
    public static function get_sell_data( $order, $callbacks = false ) {
      if ( ! self::required_keys_set() ) {
        Kekspay_Logger::log( 'Payment gateway setup incomplete, please enter all requested data to gateway settings.', 'error' );
        return false;
      }

      $sell = array(
        'qr_type' => 1,
        'cid'     => self::get_settings( 'webshop-cid', true ),
        'tid'     => self::get_settings( 'webshop-tid', true ),
        'bill_id' => self::get_bill_id_by_order_id( $order->get_id() ),
        'amount'  => $order->get_total(),
        'store'   => self::get_settings( 'store-msg' ),
      );

      if ( $callbacks ) {
        $sell['success_url'] = $order->get_checkout_order_received_url();
        $sell['fail_url']    = $order->get_cancel_order_url_raw();
      }

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
     * Return hash created from the provided data and secret.
     *
     * @param  string $order     Order from which to extract data for hash.
     * @param  string $timestamp Timestamp for creating hash.
     *
     * @return string
     */
    public static function get_hash( $order, $timestamp ) {
      // Get hashing key from the plugins settings.
      $key = self::get_settings( 'webshop-secret-key', true );
      // Concat epochtime + webshop tid + order amount + bill_id for payload.
      $payload = $timestamp . self::get_settings( 'webshop-tid', true ) . $order->get_total() . self::get_bill_id_by_order_id( $order->get_id() );
      // Extract bytes from md5 hex hash.
      $payload_checsum = pack( 'H*', md5( $payload ) );
      // Create simple 8 byte string.
      $iv = str_repeat( pack( 'c', 0 ), 8 );
      // Encrypt data using 3DES CBC algorithm and convert it to hex.
      $hash = bin2hex( openssl_encrypt( $payload_checsum, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $iv ) );

      return strtoupper( $hash );
    }

  }
}
