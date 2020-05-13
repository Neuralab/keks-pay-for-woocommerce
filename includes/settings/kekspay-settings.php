<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

return apply_filters(
  'wc_kekspay_settings',
  array(
    'enabled'          => array(
      'title'    => __( 'Enable', 'kekspay' ),
      'type'     => 'checkbox',
      'label'    => __( 'Enable KEKS Pay Payment Gateway', 'kekspay' ),
      'default'  => 'no',
      'desc_tip' => false,
    ),
    'title'            => array(
      'title'       => __( 'Title', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'This controls the title which the user sees during the checkout.', 'kekspay' ),
      'default'     => __( 'KEKS Pay', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'description-msg'  => array(
      'title'       => __( 'Description', 'kekspay' ),
      'type'        => 'textarea',
      'description' => __( 'Payment method description that the customer will see on the checkout page.', 'kekspay' ),
      'default'     => __( 'Launch the app directly if using mobile device or simply scan the QR code to pay.', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'confirmation-msg' => array(
      'title'       => __( 'Confirmation', 'kekspay' ),
      'type'        => 'textarea',
      'description' => __( 'Confirmation message that will be added to the "thank you" page.', 'kekspay' ),
      'default'     => __( 'Your account has been charged and your transaction is successful.', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'webhook'          => array(
      'title'       => __( 'Webhook Endpoint', 'kekspay' ),
      'type'        => 'title',
      'description' => Kekspay_Payment_Gateway::settings_webhook(),
    ),
    'webshop-options' => array(
      'title'       => __( 'Webshop data', 'kekspay' ),
      'type'        => 'title',
      'description' => '',
    ),
    'webshop-cid'      => array(
      'title'       => __( 'Webshop CID', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Unique id for Webshop within KEKS system. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'webshop-tid'      => array(
      'title'       => __( 'Webshop TID', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Unique id for point of service within Webshop system. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'advanced-options' => array(
      'title'       => __( 'Advanced options', 'kekspay' ),
      'type'        => 'title',
      'description' => '',
    ),
    'in-test-mode'     => array(
      'title'       => __( 'KEKS Pay Test Mode', 'kekspay' ),
      'type'        => 'checkbox',
      'label'       => __( 'Enable KEKS Pay Test Mode', 'kekspay' ),
      'description' => __( 'Mode used for testing purposes, disable this for live web shops.', 'kekspay' ),
      'default'     => 'no',
      'desc_tip'    => true,
    ),
    'use-logger'       => array(
      'title'       => __( 'Debug log', 'kekspay' ),
      'type'        => 'checkbox',
      'label'       => __( 'Enable logging', 'kekspay' ),
      'description' => sprintf( __( 'Log gateway events, stored in %s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'kekspay' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'kekspay' ) . '</code>' ),
      'default'     => 'no',
    ),
  )
);
