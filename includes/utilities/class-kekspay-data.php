<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

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
    private $settings = array();

    /**
     * Set endpoint for webshop api.
     *
     * @var string
     */
    private static $endpoint = 'wc-kekspay';

    /**
     * Init data class.
     */
    public function __construct() {
      $this->set_settings();
    }

    /**
     * Load gateway settings from the database.
     *
     * @return void
     */
    public function set_settings() {
      $this->settings = get_option( 'woocommerce_' . KEKSPAY_PLUGIN_ID . '_settings', array() );
    }

    /**
     * Fetch settings for use.
     *
     * @return array/string
     */
    public function get_settings( $setting = false ) {
      if ( empty( $this->settings ) ) {
        $this->set_settings();
      }

      return $setting ? isset( $this->settings[ $setting ] ) ? $this->settings[ $setting ] : false : $this->settings;
    }

    /**
     * Return gateway endpoint on wc api.
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
    public function get_kekspay_endpoint() {
      return 'https://kekspay.hr/' . ( $this->get_settings( 'in-test-mode' ) ? 'sokolpay' : 'pay' );
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
     * Delete gateway settings. Return true if option is successfully deleted or
     * false on failure or if option does not exist.
     *
     * @return bool
     */
    public function delete_settings() {
      return delete_option( 'woocommerce_' . KEKSPAY_PLUGIN_ID . '_settings' ) && delete_option( 'kekspay_plugins_check_required' );
    }

    /**
     * Generate signature from order data.
     *
     * @return string
     */
    public function get_signature( $order ) {
      $secret_key = $this->get_settings( 'secret-key' );

      $signed_payload = $order->get_status();

      $signature = hash_hmac( 'sha256', $signed_payload, $secret_key );

      return $signature;
    }

  }
}
