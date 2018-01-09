<?php
/*
 * Plugin Name: ExchangeWP - PayPal Pro Add-on
 * Version: 1.2.4
 * Description: Adds the ability for users to checkout with PayPal Pro.
 * Plugin URI: https://exchangewp.com/downloads/paypal-pro/
 * Author: ExchangeWP
 * Author URI: https://exchangewp.com
 * ExchangeWP Package: exchange-addon-paypal-pro

 * Installation:
 * 1. Download and unzip the latest release zip file.
 * 2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
 * 3. Upload the entire plugin directory to your `/wp-content/plugins/` directory.
 * 4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
 *
*/

/**
 * This registers our plugin as a PayPal Pro addon
 *
 * @since 1.0.0
*/
function it_exchange_register_paypal_pro_addon() {
	$options = array(
		'name'              => __( 'PayPal Pro', 'LION' ),
		'description'       => __( 'Process transactions via PayPal Pro.', 'LION' ),
		'author'            => 'ExchangeWP',
		'author_url'        => 'https://exchangewp.com',
		'icon'              => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/paypal50px.png' ),
		'wizard-icon'       => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/wizard-paypal-pro.png' ),
		'file'              => dirname( __FILE__ ) . '/init.php',
		'category'          => 'transaction-methods',
		'settings-callback' => 'it_exchange_paypal_pro_addon_settings_callback',
	);
	it_exchange_register_addon( 'paypal_pro', $options );
}
add_action( 'it_exchange_register_addons', 'it_exchange_register_paypal_pro_addon' );

/**
 * Require other add-ons that may be needed
 *
 * @since 1.0.0
*/
function it_exchange_paypal_pro_required_addons() {
	add_filter( 'it_exchange_billing_address_purchase_requirement_enabled', '__return_true' );
}
add_action( 'it_exchange_enabled_addons_loaded', 'it_exchange_paypal_pro_required_addons' );

/**
 * Loads the translation data for WordPress
 *
 * @since 1.0.0
*/
function it_exchange_paypal_pro_set_textdomain() {
	load_plugin_textdomain( 'LION', false, dirname( plugin_basename( __FILE__  ) ) . '/lang/' );
}
add_action( 'plugins_loaded', 'it_exchange_paypal_pro_set_textdomain' );

function ithemes_exchange_paypal_pro_deactivate() {
	if ( empty( $_GET['remove-gateway'] ) || 'yes' !== $_GET['remove-gateway'] ) {
		$title = __( 'Payment Gateway Warning', 'LION' );
		$yes = '<a href="' . esc_url( add_query_arg( 'remove-gateway', 'yes' ) ) . '">' . __( 'Yes', 'LION' ) . '</a>';
		$no  = '<a href="javascript:history.back()">' . __( 'No', 'LION' ) . '</a>';
		$message = '<p>' . sprintf( __( 'Deactivating a payment gateway can cause customers to lose access to any membership products they have purchased using this payment gateway. Are you sure you want to proceed? %s | %s', 'LION' ), $yes, $no ) . '</p>';
		$args = array(
			'response'  => 200,
			'back_link' => false,
		);
		wp_die( $message, $title, $args );
	}
}
register_deactivation_hook( __FILE__, 'ithemes_exchange_paypal_pro_deactivate' );

/**
 * Registers Plugin with iThemes updater class
 *
 * @since 1.0.0
 * @param object $updater ithemes updater object
*/
function exchange_paypal_pro_plugin_updater() {

	$license_check = get_transient( 'exchangewp_license_check' );

	if ($license_check->license == 'valid' ) {
		$license_key = it_exchange_get_option( 'exchangewp_licenses' );
		$license = $license_key['exchange_license'];

		$edd_updater = new EDD_SL_Plugin_Updater( 'https://exchangewp.com', __FILE__, array(
				'version' 		=> '1.2.4', 				// current version number
				'license' 		=> $license, 				// license key (used get_option above to retrieve from DB)
				'item_id' 		=> 402,					 	  // name of this plugin
				'author' 	  	=> 'ExchangeWP',    // author of this plugin
				'url'       	=> home_url(),
				'wp_override' => true,
				'beta'		  	=> false
			)
		);
	}

}

add_action( 'admin_init', 'exchange_paypal_pro_plugin_updater', 0 );
