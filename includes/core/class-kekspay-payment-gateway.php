<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
  return;
}

if ( ! class_exists( 'Kekspay_Payment_Gateway' ) ) {
  /**
   * Kekspay_Payment_Gateway class
   */
  class Kekspay_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * App data handler.
     *
     * @var Kekspay_Sell
     */
    private $sell;

    /**
     * Class constructor with basic gateway's setup.
     */
    public function __construct() {
      require_once( KEKSPAY_DIR_PATH . '/includes/core/class-kekspay-connector.php' );
      require_once( KEKSPAY_DIR_PATH . '/includes/core/class-kekspay-sell.php' );

      $this->id                 = KEKSPAY_PLUGIN_ID;
      $this->method_title       = __( 'KEKS Pay', 'kekspay' );
      $this->method_description = __( 'Allow customers to complete payments using KEKS Pay mobile app.', 'kekspay' );
      $this->has_fields         = true;

      $this->init_form_fields();
      $this->init_settings();

      $this->supports = array( 'products' );

      $this->connector = new Kekspay_Connector();
      $this->sell      = new Kekspay_Sell();

      $this->title = esc_attr( Kekspay_Data::get_settings( 'title' ) );

      $this->add_hooks();
    }

    /**
     * Register different hooks.
     */
    private function add_hooks() {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'do_receipt_page' ) );
      add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'do_order_confirmation' ) );

      add_filter( 'woocommerce_gateway_icon', array( $this, 'do_gateway_checkout_icon' ), 10, 2 );
    }

    /**
     * Check if we need to make gateways available.
     *
     * @override
     */
    public function is_available() {
      if ( ! Kekspay_Data::required_keys_set() || ! Kekspay_Data::currency_supported() ) {
        return false;
      }

      return parent::is_available();
    }

    /**
     * Trigger 'kekspay_gateway_checkout_icon' hook.
     *
     * @param  string $icon
     * @param  string $id
     *
     * @return string
     */
    public function do_gateway_checkout_icon( $icon, $id ) {
      if ( $this->id !== $id ) {
        return;
      }

      $icon = (string) apply_filters( 'kekspay_gateway_checkout_icon', 0 );
      if ( ! empty( $icon ) ) {
        return $icon;
      }
    }

    /**
     * Echoes gateway's options (Checkout tab under WooCommerce's settings).
     *
     * @override
     */
    public function admin_options() {
      ?>
      <h2><?php esc_html_e( 'KEKSPay Payment Gateway', 'kekspay' ); ?></h2>

      <table class="form-table">
        <?php $this->generate_settings_html(); ?>
      </table>
      <?php
    }

    /**
     * Define gateway's fields visible at WooCommerce's Settings page and
     * Checkout tab.
     *
     * @override
     */
    public function init_form_fields() {
      $this->form_fields = include( KEKSPAY_DIR_PATH . '/includes/settings/kekspay-settings.php' );
    }


    /**
     * Display description of the gateway on the checkout page.
     *
     * @override
     */
    public function payment_fields() {
      $gateway_desc = Kekspay_Data::get_settings( 'description-msg' );

      if ( isset( $gateway_desc ) && ! empty( $gateway_desc ) ) {
        echo '<p>' . wptexturize( $gateway_desc ) . '</p>';
      }

      if ( Kekspay_Data::test_mode() ) {
        $test_mode_notice = '<p><b>' . __( 'Kekspay is currently in sandbox/test mode, disable it for live web shops.', 'kekspay' ) . '</b></p>';
        $test_mode_notice = apply_filters( 'kekspay_payment_description_test_mode_notice', $test_mode_notice );

        if ( ! empty( $test_mode_notice ) ) {
          echo $test_mode_notice;
        }
      }
    }

    /**
     * Echo confirmation message on the 'thank you' page.
     */
    public function do_order_confirmation( $order_id ) {
      $order             = wc_get_order( $order_id );
      $confirmation_desc = Kekspay_Data::get_settings( 'confirmation-msg' );

      if ( $order ) {
        $order->update_meta_data( 'kekspay_status', 'pending_approval' );
        $order->save();
      } else {
        Kekspay_Logger::log( 'Failed to find order with ID ' . $order_id . ' while displaying order confirmation.', 'warning' );
      }

      if ( isset( $confirmation_desc ) && ! empty( $confirmation_desc ) ) {
        echo '<p>' . wptexturize( $confirmation_desc ) . '</p>';
      }
    }

    /**
     * Echo redirect message on the 'receipt' page.
     */
    private function show_receipt_message() {
      $receipt_desc = Kekspay_Data::get_settings( 'receipt-msg' );

      if ( isset( $receipt_desc ) && ! empty( $receipt_desc ) ) {
        echo '<p>' . wptexturize( $receipt_desc ) . '</p>';
      }
    }

    /**
     * Trigger actions for 'receipt' page.
     *
     * @param int $order_id
     */
    public function do_receipt_page( $order_id ) {
      $order = wc_get_order( $order_id );

      if ( ! $order ) {
        Kekspay_Logger::log( 'Failed to find order ' . $order_id . ' while trying to show receipt page.', 'warning' );
        return false;
      }

      $order->add_meta_data( 'kekspay_status', 'pending', true );
      $order->save();

      if ( Kekspay_Data::test_mode() ) {
        $order->add_order_note( __( 'Order was done in <b>test mode</b>.', 'kekspay' ) );
        $order->add_meta_data( 'in_test_mode', 'yes', true );
        $order->save();
      }

      $this->show_receipt_message();

      do_action( 'kekspay_receipt_before_payment_data', $order, Kekspay_Data::get_settings() );

      ?>
        <div class="kekspay-url">
          <div class="div">
            <?php echo $this->sell->display_sell_url( $order ); ?>
          </div>
          <small><a href="#" qr-code-trigger><?php esc_html_e( 'Having troubles with the link? Click here to show QR code.', 'kekspay' ); ?></a></small>
        </div>
        <div class="kekspay-qr">
          <?php echo $this->sell->display_sell_qr( $order ); ?>
        </div>
      <?php

      do_action( 'kekspay_receipt_after_payment_data', $order, Kekspay_Data::get_settings() );
    }

    /**
     * Process the payment and return the result.
     *
     * @override
     * @param string $order_id
     *
     * @return array
     */
    public function process_payment( $order_id ) {
      $order = wc_get_order( $order_id );

      if ( ! $order ) {
        Kekspay_Logger::log( 'Failed to find order ' . $order_id . ' while trying to process payment.', 'critical' );
        return;
      }

      WC()->cart->empty_cart();

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true ),
      );
    }

  }
}
