<?php
/**
 * The following file contains utility functions specific to our PayPal Pro add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for PayPal Pro, etc.
*/

/**
 * Adds actions to the plugins page for the iThemes Exchange PayPal Pro plugin
 *
 * @since 1.0.0
 *
 * @param array $meta Existing meta
 * @param string $plugin_file the wp plugin slug (path)
 * @param array $plugin_data the data WP harvested from the plugin header
 * @param string $context
 * @return array
*/
function it_exchange_paypal_pro_plugin_row_actions( $actions, $plugin_file, $plugin_data, $context ) {

    $actions['setup_addon'] = '<a href="' . get_admin_url( NULL, 'admin.php?page=it-exchange-addons&add-on-settings=paypal_pro' ) . '">' . __( 'Setup Add-on', 'it-l10n-exchange-addon-paypal-pro' ) . '</a>';

    return $actions;

}
add_filter( 'plugin_action_links_exchange-addon-paypal-pro/exchange-addon-paypal-pro.php', 'it_exchange_paypal_pro_plugin_row_actions', 10, 4 );

/**
 * Enqueues any scripts we need on the frontend during a PayPal Pro checkout
 *
 * @since 1.0.0
 *
 * @return void
*/
function it_exchange_paypal_pro_addon_enqueue_script() {
    wp_enqueue_script( 'paypal-pro-addon-js', ITUtility::get_url_from_file( dirname( __FILE__ ) ) . '/js/paypal-pro-addon.js', array( 'jquery' ) );
    wp_localize_script( 'paypal-pro-addon-js', 'PaypalProAddonL10n', array(
            'processing_payment_text' => __( 'Processing payment, please wait...', 'it-l10n-exchange-addon-paypal-pro' ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'it_exchange_paypal_pro_addon_enqueue_script' );

/**
 * Grab the PayPal Pro customer ID for a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP customer ID
 * @return integer
*/
function it_exchange_paypal_pro_addon_get_customer_id( $customer_id ) {
    $settings = it_exchange_get_option( 'addon_paypal_pro' );
    $mode     = ( $settings['paypal_pro_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    return get_user_meta( $customer_id, '_it_exchange_paypal_pro_id' . $mode, true );
}

/**
 * Add the PayPal Pro customer ID as user meta on a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP user ID
 * @param integer $paypal_pro_id the PayPal Pro customer ID
 * @return boolean
*/
function it_exchange_paypal_pro_addon_set_customer_id( $customer_id, $paypal_pro_id ) {
    $settings = it_exchange_get_option( 'addon_paypal_pro' );
    $mode     = ( $settings['paypal_pro_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    return update_user_meta( $customer_id, '_it_exchange_paypal_pro_id' . $mode, $paypal_pro_id );
}

/**
 * Grab a transaction from the PayPal Pro transaction ID
 *
 * @since 1.0.0
 *
 * @param integer $paypal_pro_id id of PayPal Pro transaction
 * @return transaction object
*/
function it_exchange_paypal_pro_addon_get_transaction_id( $paypal_pro_id ) {
    $args = array(
        'meta_key'    => '_it_exchange_transaction_method_id',
        'meta_value'  => $paypal_pro_id,
        'numberposts' => 1, //we should only have one, so limit to 1
    );
    return it_exchange_get_transactions( $args );
}

/**
 * Updates a PayPal Pro transaction status based on PayPal Pro ID
 *
 * @since 1.0.0
 *
 * @param integer $paypal_pro_id id of PayPal Pro transaction
 * @param string $new_status new status
 * @return void
*/
function it_exchange_paypal_pro_addon_update_transaction_status( $paypal_pro_id, $new_status ) {
    $transactions = it_exchange_paypal_pro_addon_get_transaction_id( $paypal_pro_id );
    foreach( $transactions as $transaction ) { //really only one
        $current_status = it_exchange_get_transaction_status( $transaction );
        if ( $new_status !== $current_status )
            it_exchange_update_transaction_status( $transaction, $new_status );
    }
}

/**
 * Adds a refund to post_meta for a PayPal Pro transaction
 *
 * @since 1.0.0
*/
function it_exchange_paypal_pro_addon_add_refund_to_transaction( $paypal_pro_id, $refund ) {

    // PayPal Pro money format comes in as cents. Divide by 100.
    $refund = ( $refund / 100 );

    // Grab transaction
    $transactions = it_exchange_paypal_pro_addon_get_transaction_id( $paypal_pro_id );
    foreach( $transactions as $transaction ) { //really only one

        $refunds = it_exchange_get_transaction_refunds( $transaction );

        $refunded_amount = 0;
        foreach( ( array) $refunds as $refund_meta ) {
            $refunded_amount += $refund_meta['amount'];
        }

        // In PayPal Pro the Refund is the total amount that has been refunded, not just this transaction
        $this_refund = $refund - $refunded_amount;

        // This refund is already formated on the way in. Don't reformat.
        it_exchange_add_refund_to_transaction( $transaction, $this_refund );
    }
}

/**
 * Removes a PayPal Pro Customer ID from a WP user
 *
 * @since 1.0.0
 *
 * @param integer $paypal_pro_id the id of the PayPal Pro transaction
*/
function it_exchange_paypal_pro_addon_delete_id_from_customer( $paypal_pro_id ) {
    $settings = it_exchange_get_option( 'addon_paypal_pro' );
    $mode     = ( $settings['paypal_pro_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    $transactions = it_exchange_paypal_pro_addon_get_transaction_id( $paypal_pro_id );
    foreach( $transactions as $transaction ) { //really only one
        $customer_id = get_post_meta( $transaction->ID, '_it_exchange_customer_id', true );
        if ( false !== $current_paypal_pro_id = it_exchange_paypal_pro_addon_get_customer_id( $customer_id ) ) {

            if ( $current_paypal_pro_id === $paypal_pro_id )
                delete_user_meta( $customer_id, '_it_exchange_paypal_pro_id' . $mode );

        }
    }
}

/**
 * @param IT_Exchange_Customer $it_exchange_customer
 * @param $transaction_object
 * @param $args
 *
 * @return array
 * @throws Exception
 */
function it_exchange_paypal_pro_addon_do_payment( $it_exchange_customer, $transaction_object, $args ) {

	$general_settings = it_exchange_get_option( 'settings_general' );
	$settings = it_exchange_get_option( 'addon_paypal_pro' );

	$charge = array(
		'customer' => $it_exchange_customer->id,
		'amount' => number_format( $transaction_object->total, 2, '', '' ),
		'currency' => $general_settings[ 'default-currency' ],
		'description' => $transaction_object->description,
	);

	$url = 'https://api-3t.paypal.com/nvp';

	if ( $settings[ 'paypal_pro_sandbox_mode' ] ) {
		$url = 'https://api-3t.sandbox.paypal.com/nvp';
	}

	$total = number_format( it_exchange_get_cart_total( false ), 2, '.', '' );

	$post_data = array(
		// Base Cart
		'AMT' => $total,
		'CURRENCYCODE' => $general_settings[ 'default_currency' ],

		// Credit Card information
		'CREDITCARDTYPE' => '', // @todo
		'ACCT' => '', // @todo
		'EXPDATE' => '', // @todo
		'CVV2' => '', // @todo

		// Customer information
		'EMAIL' => $it_exchange_customer->data->user_email,
		'FIRSTNAME' => $it_exchange_customer->data->first_name,
		'LASTNAME' => $it_exchange_customer->data->last_name,
		'STREET' => '', // @todo
		'CITY' => '', // @todo
		'STATE' => '', // @todo
		'ZIP' => '', // @todo
		'COUNTRYCODE' => '', // @todo

		// Shipping information
		'SHIPTONAME' => $it_exchange_customer->data->first_name . ' ' . $it_exchange_customer->data->last_name,
		'SHIPTOSTREET' => '', // @todo
		'SHIPTOSTREET2' => '', // @todo
		'SHIPTOCITY' => '', // @todo
		'SHIPTOSTATE' => '', // @todo
		'SHIPTOZIP' => '', // @todo
		'SHIPTOCOUNTRYCODE' => '', // @todo

		// Cart info (item format is 0+)
		'L_NUMBER0' => 0,
		'L_AMT0' => $total,
		'L_NAME0' => it_exchange_get_cart_description(),
		'L_QTY0' => 1,

		// API settings
		'METHOD' => 'DoDirectPayment',
		'PAYMENTACTION' => 'Sale',
		'USER' => $settings[ 'paypal_pro_api_username' ],
		'PWD' => $settings[ 'paypal_pro_api_password' ],
		'SIGNATURE' => $settings[ 'paypal_pro_api_signature' ],

		// Additional info
		'IPADDRESS' => $_SERVER[ 'REMOTE_ADDR' ],
		'VERSION' => '59.0',
	);

	// @todo Handle multi-item carts
	/*$products = it_exchange_get_cart_products();

	$item_count = 0;

	foreach ( $products as $product ) {
		$post_data[ 'L_NUMBER' . $item_count ] = $item_count;
		$post_data[ 'L_AMT' . $item_count ] = it_exchange_get_cart_product_base_price( $product );
		$post_data[ 'L_NAME' . $item_count ] = it_exchange_get_cart_product_title( $product );
		$post_data[ 'L_QTY' . $item_count ] = $product[ 'count' ];

		// @todo Handle taxes?
		// $post_data[ 'L_TAXAMT' . $item_count ] = 0;

		$item_count++;
	}*/

	$args = array(
		'method' => 'POST',
		'body' => $post_data,
		'user-agent' => 'iThemes Exchange',
		'timeout' => 90,
		'sslverify' => false
	);

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		// @todo Show error message
	}

	$body = wp_remote_retrieve_body( $response );

	if ( empty( $body ) ) {
		// @todo Show error message
	}

	parse_str( $body, $api_response );

	$status = strtolower( $api_response[ 'ACK' ] );

	switch ( $status ) {
		case 'success':
		case 'successwithwarning':
			// @todo Set message

			break;

		case 'failure':
		default:
			$messages = array();

			$message_count = 0;

			while ( isset( $api_response[ 'L_LONGMESSAGE' . $message_count ] ) ) {
				$messages[] = $api_response[ 'L_SHORTMESSAGE' . $message_count ] . ': ' . $api_response[ 'L_LONGMESSAGE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

				$message_count++;
			}

			if ( empty( $messages ) ) {
				$message_count = 0;

				while ( isset( $api_response[ 'L_SHORTMESSAGE' . $message_count ] ) ) {
					$messages[] = $api_response[ 'L_SHORTMESSAGE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

					$message_count++;
				}
			}

			if ( empty( $messages ) ) {
				$message_count = 0;

				while ( isset( $api_response[ 'L_SEVERITYCODE' . $message_count ] ) ) {
					$messages[] = $api_response[ 'L_SEVERITYCODE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

					$message_count++;
				}
			}

			// @todo Set message
			// 'Correlation ID: ' . $api_response[ 'CORRELATIONID' ]

			// @todo Return errors

			break;
	}

	return array( 'id' => $charge->id, 'status' => 'success' );
}