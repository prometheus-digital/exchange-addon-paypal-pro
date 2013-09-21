<?php
/**
 * Exchange Transaction Add-ons require several hooks in order to work properly.
 * Most of these hooks are called in api/transactions.php and are named dynamically
 * so that individual add-ons can target them. eg: it_exchange_refund_url_for_paypal_pro
 * We've placed them all in one file to help add-on devs identify them more easily
*/

/**
 * PayPal Pro URL to perform refunds
 *
 * The it_exchange_refund_url_for_[addon-slug] filter is
 * used to generate the link for the 'Refund Transaction' button
 * found in the admin under Customer Payments
 *
 * @since 1.0.0
 *
 * @param string $url passed by WP filter.
 * @param string $url transaction URL
*/
function it_exchange_refund_url_for_paypal_pro( $url ) {
	return 'https://www.paypal.com/';
}
add_filter( 'it_exchange_refund_url_for_paypal_pro', 'it_exchange_refund_url_for_paypal_pro' );

/**
 * This proccesses a PayPal Pro transaction.
 *
 * The it_exchange_do_transaction_[addon-slug] action is called when
 * the site visitor clicks a specific add-ons 'purchase' button. It is
 * passed the default status of false along with the transaction object
 * The transaction object is a package of data describing what was in the user's cart
 *
 * Exchange expects your add-on to either return false if the transaction failed or to
 * call it_exchange_add_transaction() and return the transaction ID
 *
 * @since 1.0.0
 *
 * @param string $status passed by WP filter.
 * @param object $transaction_object The transaction object
*/
function it_exchange_paypal_pro_addon_process_transaction( $status, $transaction_object ) {

	// If this has been modified as true already, return.
	if ( $status || !isset( $_REQUEST[ 'ite-paypal_pro-purchase-dialog-nonce' ] ) ) {
		return $status;
	}

	// Verify nonce
	if ( empty( $_REQUEST[ 'ite-paypal_pro-purchase-dialog-nonce' ] ) || !wp_verify_nonce( $_REQUEST[ 'ite-paypal_pro-purchase-dialog-nonce' ], 'paypal_pro-checkout' ) ) {
		it_exchange_add_message( 'error', __( 'Transaction Failed, unable to verify security token.', 'LION' ) );

		return false;
	}

	$it_exchange_customer = it_exchange_get_current_customer();

	try {
		// Set / pass additional info
		$args = array();

		// Make payment
		$payment = it_exchange_paypal_pro_addon_do_payment( $it_exchange_customer, $transaction_object, $args );
	}
	catch ( Exception $e ) {
		it_exchange_flag_purchase_dialog_error( 'paypal_pro' );
		it_exchange_add_message( 'error', $e->getMessage() );

		return false;
	}

	return it_exchange_add_transaction( 'paypal_pro', $payment[ 'id' ], 'succeeded', $it_exchange_customer->id, $transaction_object );

}
add_filter( 'it_exchange_do_transaction_paypal_pro', 'it_exchange_paypal_pro_addon_process_transaction', 10, 2 );

/**
 * Returns the button for making the payment
 *
 * Exchange will loop through activated Payment Methods on the checkout page
 * and ask each transaction method to return a button using the following filter:
 * - it_exchange_get_[addon-slug]_make_payment_button
 * Transaction Method add-ons must return a button hooked to this filter if they
 * want people to be able to make purchases.
 *
 * @since 1.0.0
 *
 * @param array $options
 * @return string HTML button
*/
function it_exchange_paypal_pro_addon_make_payment_button( $options ) {

    if ( 0 >= it_exchange_get_cart_total( false ) )
        return '';

    return it_exchange_generate_purchase_dialog( 'paypal_pro' );

}
add_filter( 'it_exchange_get_paypal_pro_make_payment_button', 'it_exchange_paypal_pro_addon_make_payment_button', 10, 2 );

/**
 * Gets the interpretted transaction status from valid PayPal Pro transaction statuses
 *
 * Most gateway transaction stati are going to be lowercase, one word strings.
 * Hooking a function to the it_exchange_transaction_status_label_[addon-slug] filter
 * will allow add-ons to return the human readable label for a given transaction status.
 *
 * @since 1.0.0
 *
 * @param string $status the string of the PayPal Pro transaction
 * @return string translaction transaction status
*/
function it_exchange_paypal_pro_addon_transaction_status_label( $status ) {
    switch ( $status ) {
        case 'succeeded':
            return __( 'Paid', 'LION' );
        case 'refunded':
            return __( 'Refunded', 'LION' );
        case 'partial-refund':
            return __( 'Partially Refunded', 'LION' );
        case 'needs_response':
            return __( 'Disputed: PayPal Pro needs a response', 'LION' );
        case 'under_review':
            return __( 'Disputed: Under review', 'LION' );
        case 'won':
            return __( 'Disputed: Won, Paid', 'LION' );
        default:
            return __( 'Unknown', 'LION' );
    }
}
add_filter( 'it_exchange_transaction_status_label_paypal_pro', 'it_exchange_paypal_pro_addon_transaction_status_label' );

/**
 * Returns a boolean. Is this transaction a status that warrants delivery of any products attached to it?
 *
 * Just because a transaction gets added to the DB doesn't mean that the admin is ready to give over
 * the goods yet. Each payment gateway will have different transaction stati. Exchange uses the following
 * filter to ask transaction-methods if a current status is cleared for delivery. Return true if the status
 * means its okay to give the download link out, ship the product, etc. Return false if we need to wait.
 * - it_exchange_[addon-slug]_transaction_is_cleared_for_delivery
 *
 * @since 1.0.0
 *
 * @param boolean $cleared passed in through WP filter. Ignored here.
 * @param object $transaction
 * @return boolean
*/
function it_exchange_paypal_pro_transaction_is_cleared_for_delivery( $cleared, $transaction ) {
    $valid_stati = array( 'succeeded', 'partial-refund', 'won' );

    return in_array( it_exchange_get_transaction_status( $transaction ), $valid_stati );
}
add_filter( 'it_exchange_paypal_pro_transaction_is_cleared_for_delivery', 'it_exchange_paypal_pro_transaction_is_cleared_for_delivery', 10, 2 );




/**
 * Returns the Unsubscribe button for PayPal Pro
 *
 * @since 1.1.0
 *
 * @param string $output PayPal Pro output (should be empty)
 * @param array $options Recurring Payments options
 * @return string
*/
function it_exchange_paypal_pro_unsubscribe_action( $output, $options ) {
	$output  = '<a class="button" href="' .  add_query_arg( 'it-exchange-paypal_pro-action', 'unsubscribe' ) . '">';
	$output .= $options['label'];
	$output .= '</a>';

	return $output;
}
add_filter( 'it_exchange_paypal_pro_unsubscribe_action', 'it_exchange_paypal_pro_unsubscribe_action', 10, 2 );

/**
 * Performs user requested unsubscribe
 *
 * @since 1.3.0
 *
 * @return void
*/
function it_exchange_paypal_pro_unsubscribe_action_submit() {
	if ( !empty( $_REQUEST['it-exchange-paypal_pro-action'] ) ) {

		$settings = it_exchange_get_option( 'addon_paypal_pro' );

		$secret_key = ( $settings['paypal_pro-test-mode'] ) ? $settings['paypal_pro-test-secret-key'] : $settings['paypal_pro-live-secret-key'];
		Stripe::setApiKey( $secret_key );

		switch( $_REQUEST['it-exchange-paypal_pro-action'] ) {

			case 'unsubscribe' :
				try {
					$current_user_id = get_current_user_id();
					$paypal_pro_customer_id = it_exchange_paypal_pro_addon_get_paypal_pro_customer_id( $current_user_id );
					$cu = Stripe_Customer::retrieve( $paypal_pro_customer_id );
					$cu->cancelSubscription();
				}
				catch( Exception $e ) {
					it_exchange_add_message( 'error', sprintf( __( 'Error: Unable to unsubscribe user %s', 'LION' ), $e->getMessage() ) );
				}
				break;

			case 'unsubscribe-user' :
				if ( is_admin() && current_user_can( 'administrator' ) ) {
					if ( !empty( $_REQUEST['it-exchange-paypal_pro-customer-id'] ) && $paypal_pro_customer_id = $_REQUEST['it-exchange-paypal_pro-customer-id'] ) {
						try {
							$cu = Stripe_Customer::retrieve( $paypal_pro_customer_id );
							$cu->cancelSubscription();
						}
						catch( Exception $e ) {
							it_exchange_add_message( 'error', sprintf( __( 'Error: Unable to unsubscribe user %s', 'LION' ), $e->getMessage() ) );
						}
					}
				}
				break;

		}

	}
}
add_action( 'init', 'it_exchange_paypal_pro_unsubscribe_action_submit' );


/**
 * Output the Cancel URL for the Payments screen
 *
 * @since 1.3.1
 *
 * @param object $transaction iThemes Transaction object
 * @return void
*/
function it_exchange_paypal_pro_after_payment_details_cancel_url( $transaction ) {
	$cart_object = get_post_meta( $transaction->ID, '_it_exchange_cart_object', true );
	foreach ( $cart_object->products as $product ) {
		$autorenews = $transaction->get_transaction_meta( 'subscription_autorenew_' . $product['product_id'], true );
		if ( $autorenews ) {
			$customer_id = it_exchange_get_transaction_customer_id( $transaction->ID );
			//$paypal_pro_customer_id = it_exchange_paypal_pro_addon_get_paypal_pro_customer_id( $customer_id );
			$status = $transaction->get_transaction_meta( 'subscriber_status', true );
			switch( $status ) {

				case 'deactivated':
					$output = __( 'Recurring payment has been deactivated', 'LION' );
					break;

				case 'cancelled':
					$output = __( 'Recurring payment has been cancelled', 'LION' );
					break;

				case 'active':
				default:
					$output  = '<a href="' .  add_query_arg( array( 'it-exchange-paypal_pro-action' => 'unsubscribe-user', 'it-exchange-paypal_pro-customer-id' => $paypal_pro_customer_id ) ) . '">' . __( 'Cancel Recurring Payment', 'LION' ) . '</a>';
					break;
			}
			?>
			<div class="transaction-autorenews clearfix spacing-wrapper">
				<div class="recurring-payment-cancel-options left">
					<div class="recurring-payment-status-name"><?php echo $output; ?></div>
				</div>
			</div>
			<?php
			continue;
		}
	}
}
add_action( 'it_exchange_after_payment_details_cancel_url_for_paypal_pro', 'it_exchange_paypal_pro_after_payment_details_cancel_url' );