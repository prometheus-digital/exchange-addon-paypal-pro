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
