<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

return apply_filters(
  'wc_kekspay_settings',
  array(
    'enabled'                 => array(
      'title'    => __( 'Enable', 'kekspay' ),
      'type'     => 'checkbox',
      'label'    => __( 'Enable KEKS Pay Payment Gateway', 'kekspay' ),
      'default'  => 'no',
      'desc_tip' => false,
    ),
    'title'                   => array(
      'title'       => __( 'Title', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'This controls the title which the user sees during the checkout.', 'kekspay' ),
      'default'     => __( 'KEKS Pay', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'store-msg'               => array(
      'title'       => __( 'Store description', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Description of the store (will be displayed to customer on wallet).', 'kekspay' ),
      'default'     => get_bloginfo( 'name' ),
      'desc_tip'    => true,
    ),
    'description-msg'         => array(
      'title'       => __( 'Checkout description', 'kekspay' ),
      'type'        => 'textarea',
      'description' => __( 'Payment method description that the customer will see on the checkout page.', 'kekspay' ),
      'default'     => __( 'Launch the app directly if using mobile device or simply scan the QR code to pay.', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'receipt-msg'             => array(
      'title'       => __( 'Payment description', 'kekspay' ),
      'type'        => 'textarea',
      'description' => __( 'Payment method description showed to the customer with the QR code.', 'kekspay' ),
      'default'     => __( 'Use your KEKS Pay mobile app to complete the payment.', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'confirmation-msg'        => array(
      'title'       => __( 'Confirmation', 'kekspay' ),
      'type'        => 'textarea',
      'description' => __( 'Confirmation message that will be added to the "thank you" page.', 'kekspay' ),
      'default'     => __( 'Your account has been charged and your transaction is successful.', 'kekspay' ),
      'desc_tip'    => true,
    ),
    'webhook'                 => array(
      'title'       => __( 'Webhook Endpoint', 'kekspay' ),
      'type'        => 'title',
      'description' => Kekspay_Data::get_settings_endpoint_field(),
    ),
    'webshop-options'         => array(
      'title'       => __( 'Webshop data', 'kekspay' ),
      'type'        => 'title',
      'description' => '',
    ),
    'webshop-cid'             => array(
      'title'       => __( 'Webshop CID', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Unique id for Webshop within KEKS system. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
      'required'    => true,
    ),
    'webshop-tid'             => array(
      'title'       => __( 'Webshop TID', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Unique id for point of service within Webshop system. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'webshop-secret-key'      => array(
      'title'       => __( 'Webshop Secret Key', 'kekspay' ),
      'type'        => 'password',
      'description' => __( 'Unique key used for Webshop authentication. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'test-webshop-cid'        => array(
      'title'       => __( 'TEST Webshop CID', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Unique id for Webshop within KEKS system. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'test-webshop-tid'        => array(
      'title'       => __( 'TEST Webshop TID', 'kekspay' ),
      'type'        => 'text',
      'description' => __( 'Unique id for point of service within Webshop system. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'test-webshop-secret-key' => array(
      'title'       => __( 'TEST Webshop Secret Key', 'kekspay' ),
      'type'        => 'password',
      'description' => __( 'Unique key used for Webshop authentication. Will be given in advance by KEKS', 'kekspay' ),
      'default'     => '',
      'desc_tip'    => true,
    ),
    'advanced-options'        => array(
      'title'       => __( 'Advanced options', 'kekspay' ),
      'type'        => 'title',
      'description' => '',
    ),
    'in-test-mode'            => array(
      'title'       => __( 'KEKS Pay Test Mode', 'kekspay' ),
      'type'        => 'checkbox',
      'label'       => __( 'Enable KEKS Pay Test Mode', 'kekspay' ),
      'description' => __( 'Mode used for testing purposes, disable this for live web shops.', 'kekspay' ),
      'default'     => 'no',
      'desc_tip'    => true,
    ),
    'use-logger'              => array(
      'title'       => __( 'Debug log', 'kekspay' ),
      'type'        => 'checkbox',
      'label'       => __( 'Enable logging', 'kekspay' ),
      'description' => sprintf( __( 'Log gateway events, stored in %s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'kekspay' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'kekspay' ) . '</code>' ),
      'default'     => 'no',
    ),
  )
);
