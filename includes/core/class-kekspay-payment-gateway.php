<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
  return;
}

/**
 * Register payment gateway's class as a new method of payment.
 *
 * @param array $methods
 * @return array
 */
function kekspay_add_gateway( $methods ) {
  $methods[] = 'Kekspay_Payment_Gateway';
  return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'kekspay_add_gateway' );

if ( ! class_exists( 'Kekspay_Payment_Gateway' ) ) {
  /**
   * Kekspay_Payment_Gateway class
   */
  class Kekspay_Payment_Gateway extends WC_Payment_Gateway {
    /**
     * Logger.
     *
     * @var Kekspay_Logger
     */
    private $logger;

    /**
     * Checkout handler.
     *
     * @var Kekspay_Payment_Gateway_Checkout_Handler
     */
    private $checkout_handler;

    /**
     * Class constructor with basic gateway's setup.
     *
     * @param bool $init Should the class attributes be initialized.
     */
    public function __construct() {
      require_once( KEKSPAY_DIR_PATH . '/includes/utilities/class-kekspay-logger.php' );
      require_once( KEKSPAY_DIR_PATH . '/includes/core/class-kekspay-payment-gateway-checkout-handler.php' );

      $this->id                 = KEKSPAY_PLUGIN_ID;
      $this->method_title       = __( 'KEKS Pay', 'kekspay' );
      $this->method_description = __( 'Take payments using KEKS pay app. Customers pay by launching it directly if on mobile device or by scanning the QR code.', 'kekspay' );
      $this->has_fields         = true;

      $this->init_form_fields();
      $this->init_settings();

      $this->supports = array( 'products' );

      $this->logger           = new Kekspay_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );
      $this->checkout_handler = new Kekspay_Payment_Gateway_Checkout_Handler();

      $this->title = esc_attr( $this->settings['title'] );

      $this->add_actions();
    }

    /**
     * Register different actions.
     */
    private function add_actions() {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'do_receipt_page' ) );
      add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'do_order_confirmation' ) );

      add_filter( 'woocommerce_gateway_icon', array( $this, 'do_gateway_checkout_icon' ), 10, 2 );
    }


    /**
     * Trigger 'kekspay_gateway_checkout_icon' hook.
     *
     * @param  string $icon
     * @param  string $id
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
      if ( isset( $this->settings['description-msg'] ) && ! empty( $this->settings['description-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['description-msg'] ) . '</p>';
      }

      if ( 'yes' === $this->settings['in-test-mode'] ) {
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
      $order = wc_get_order( $order_id );

      if ( $order ) {
        $order->update_meta_data( 'kekspay_status', 'pending_approval' );
        $order->save();
      } else {
        $this->logger->log( 'Failed to find order with ID ' . $order_id . ' while displaying order confirmation.', 'warning' );
      }

      if ( isset( $this->settings['confirmation-msg'] ) && ! empty( $this->settings['confirmation-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['confirmation-msg'] ) . '</p>';
      }
    }

    /**
     * Echo redirect message on the 'receipt' page.
     */
    private function show_receipt_message() {
      if ( isset( $this->settings['receipt-redirect-msg'] ) && ! empty( $this->settings['receipt-redirect-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['receipt-redirect-msg'] ) . '</p>';
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
        $this->logger->log( 'Failed to find order ' . $order_id . ' while trying to show receipt page.', 'warning' );
        return false;
      }

      $order->add_meta_data( 'kekspay_status', 'pending', true );
      $order->save();

      $is_test_mode = 'yes' === $this->settings['in-test-mode'];
      if ( $is_test_mode ) {
        $order->add_order_note( __( 'Order was done in <b>test mode</b>.', 'kekspay' ) );
        $order->add_meta_data( 'in_test_mode', 'yes', true );
        $order->save();
      }
      $this->show_receipt_message();

      echo $this->checkout_handler->generate_url( $order );
      echo $this->checkout_handler->generate_qr( $order );
    }

    /**
     * Process the payment and return the result.
     *
     * @override
     * @param string $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while trying to process payment.', 'critical' );
        return;
      }

      // Remove cart.
      WC()->cart->empty_cart();

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true ),
      );
    }

    /**
     * Creates endpoint message for settings.
     *
     * @return string
     */
    public static function settings_webhook() {
      return sprintf(
        __( 'You will need to add this webhook endpoint %1$s %2$s %3$s to your %4$s KEKS Pay account settings %5$s, which will enable your webshop to recieve charge notifications from KEKS Pay.', 'kekspay' ),
        '<strong style="background-color:#ddd;">',
        Kekspay_Payment_Gateway_IPN::get_webhook_url(),
        '</strong>',
        '<a href="https://dashboard.stripe.com/account/webhooks" target="_blank">',
        '</a>'
      );
    }
  }
}
