<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Kekspay_Order_Admin' ) ) {
  /**
   * Kekspay_Order_Admin class
   *
   * @since 0.1
   */
  class Kekspay_Order_Admin {

    /**
     * Class constructor.
     */
    public function __construct() {
      add_action( 'admin_notices', array( $this, 'display_test_order_notice' ), 100 );
      add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'check_order_status' ), 20, 1 );
      add_action( 'wp_ajax_check_kekspay_status', array( $this, 'check_kekspay_status' ) );

      $this->connector = new Kekspay_Connector();
    }

    /**
     * Display WordPress warning notice if current order (admin view) is processed in sandbox/test mode.
     *
     * @return void
     */
    public function display_test_order_notice() {
      if ( ! is_admin() ) {
        return;
      }

      $screen = get_current_screen();
      if ( 'post' === $screen->base && 'shop_order' === $screen->id ) {
        $order = wc_get_order( get_the_ID() );
        if ( ! is_a( $order, 'WC_Order' ) ) {
          return;
        }

        if ( Kekspay_Data::order_test_mode( $order ) ) {
          $class   = 'notice notice-warning';
          $message = __( 'Narudžba kreirana koristeći KEKS Pay u testnom načinu rada.', 'kekspay' );

          printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }

        if ( 'HRK' === $order->get_currency() ) {
          $class   = 'notice notice-warning';
          $message = __( 'KEKS Pay - Narudžba naplaćena u valuti HRK koja više nije podržana od strane KEKS Pay sustava. Ako želite napraviti povrat novca kroz KEKS Pay sustav, iznos za povrat prije povrata preračunati će se u EUR prema tečaju 7.5345 te biti vraćen u valuti EUR.', 'kekspay' );

          printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
      }

    }

    /**
     * Display check order status button. Should be used in admin's
     * order view.
     *
     * @param WC_Order $order
     */
    public function check_order_status( $order ) {
      if ( 'erste-kekspay-woocommerce' !== $order->get_payment_method() ) {
        return;
      }

      $status = $order->get_meta( 'kekspay_status', true );

      if ( ! $status ) {
        Kekspay_Logger::log( 'Missing status for order with order ID ' . $order->get_id(), 'notice' );
        return;
      }
      ?>

      <div class="form-field form-field-wide kekspay-status">
        <div><a class="button kekspay-status-refresh" href="#"><?php esc_html_e( 'Check order status', 'kekspay' ); ?></a></div>
        <?php wp_nonce_field( 'kekspay-status-action', 'kekspay_status_refresh' ); ?>
      </div>
      <?php
    }

    /**
     * Fetch status from Kekspay and update order data
     *
     * @return void
     */
    public function check_kekspay_status() {
      $nonce = isset( $_POST['kekspay_status_refresh'] ) ? sanitize_key( $_POST['kekspay_status_refresh'] ) : false;
      if ( ! wp_verify_nonce( $nonce, 'kekspay-status-action' ) ) {
        Kekspay_Logger::log( 'Failed to verify nonce while trying to fetch status via ajax.', 'error' );
        wp_send_json(
          array(
              'success' => false,
              'message' => __( 'Invalid nonce.', 'kekspay' ),
          )
        );
      }

      $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false;
      if ( empty( $order_id ) ) {
        Kekspay_Logger::log( 'Missing order ID while trying to fetch order status via ajax.', 'error' );
        wp_send_json(
          array(
              'success' => false,
              'message' => __( 'Invalid order ID, please try again.', 'kekspay' ),
          )
        );
      }

      $order = wc_get_order( $order_id );

      if ( empty( $order ) ) {
        Kekspay_Logger::log( 'Failed to find order with ID ' . $order_id . ' while trying to fetch order status via ajax.', 'error' );
        wp_send_json(
          array(
              'success' => false,
              'message' => __( 'Missing order, please try again.', 'kekspay' ),
          )
        );
      }

      $status = $this->connector->get_kekspay_status( $order );

      if ( ! $status ) {
        Kekspay_Logger::log( 'No status returned by Kekspay for order ' . $order->get_id(), 'error' );
        wp_send_json(
          array(
              'success' => false,
              'message' => __( 'Status request failed.', 'kekspay' ),
          )
        );
      }

      Kekspay_Logger::log( 'Refresh status received order status: ' . $status . ' for order ' . $order->get_id(), 'info' );

      switch ( $status ) {
        case '200':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '300':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '400':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '500':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '600':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '700':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '800':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '900':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        case '950':
          // $order->update_meta_data( 'kekspay_status', $status );
          // $order->set_status( 'cancelled', __( 'Order cancelled by Kekspay.', 'kekspay' ) );
          break;

        default:
          $order->update_meta_data( 'kekspay_status', $status );
          break;
      }

      $order->save();

      wp_send_json_success( $status );
      wp_die();
    }
  }
}

new Kekspay_Order_Admin();
