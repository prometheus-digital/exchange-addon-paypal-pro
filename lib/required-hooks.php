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
	if ( $status )
		return $status;

	// Verify nonce
	if ( ! empty( $_REQUEST['_paypal_pro_nonce'] ) && ! wp_verify_nonce( $_REQUEST['_paypal_pro_nonce'], 'paypal_pro-checkout' ) ) {
		it_exchange_add_message( 'error', __( 'Transaction Failed, unable to verify security token.', 'it-l10n-exchange-addon-paypal-pro' ) );
		return false;
	}

	// Make sure we have the correct $_POST argument
	if ( ! empty( $_POST['PayPalProToken'] ) ) {

		try {

			$general_settings = it_exchange_get_option( 'settings_general' );
			$settings         = it_exchange_get_option( 'addon_paypal_pro' );

			// Set PayPal Pro customer from WP customer ID
			$it_exchange_customer = it_exchange_get_current_customer();

			/*if ( $paypal_pro_id = it_exchange_paypal_pro_addon_get_customer_id( $it_exchange_customer->id ) )
			 	$it_exchange_customer = Twocheckout_Customer::retrieve( $paypal_pro_id );*/

			// Now that we have a valid Customer ID, charge them!
			$charge = array(
				'customer'    => $it_exchange_customer->id,
				'amount'      => number_format( $transaction_object->total, 2, '', '' ),
				'currency'    => $general_settings['default-currency'],
				'description' => $transaction_object->description,
			);

		}
		catch ( Exception $e ) {
			it_exchange_add_message( 'error', $e->getMessage() );
			return false;
		}
		return it_exchange_add_transaction( 'paypal_pro', $charge->id, 'succeeded', $it_exchange_customer->id, $transaction_object );
	} else {
		it_exchange_add_message( 'error', __( 'Unknown error. Please try again later.', 'it-l10n-exchange-addon-paypal-pro' ) );
	}
	return false;

}
add_action( 'it_exchange_do_transaction_paypal_pro', 'it_exchange_paypal_pro_addon_process_transaction', 10, 2 );

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
        return;

    $general_settings = it_exchange_get_option( 'settings_general' );
    $paypal_pro_settings = it_exchange_get_option( 'addon_paypal_pro' );

    $products = it_exchange_get_cart_data( 'products' );

    $payment_form = '<form class="paypal_pro_form" action="' . esc_attr( it_exchange_get_page_url( 'transaction' ) ) . '" method="post">';
    $payment_form .= '<input type="hidden" name="it-exchange-transaction-method" value="paypal_pro" />';
    $payment_form .= wp_nonce_field( 'paypal_pro-checkout', '_paypal_pro_nonce', true, false );

    $payment_form .= '<div class="hide-if-no-js">';
    $payment_form .= '<input type="submit" class="it-exchange-paypal_pro-payment-button" name="paypal_pro_purchase" value="' . esc_attr( $paypal_pro_settings['paypal_pro_purchase_button_label'] ) .'" />';

    $payment_form .= '<script>' . "\n";
    $payment_form .= '  jQuery(".it-exchange-paypal_pro-payment-button").click(function(){' . "\n";
    $payment_form .= '    var token = function(res){' . "\n";
    $payment_form .= '      var $paypal_proToken = jQuery("<input type=hidden name=PayPalProToken />").val(res.id);' . "\n";
    $payment_form .= '      jQuery("form.paypal_pro_form").append($paypal_proToken).submit();' . "\n";
    $payment_form .= '      it_exchange_paypal_pro_processing_payment_popup();' . "\n";
    $payment_form .= '    };' . "\n";
    $payment_form .= '    PayPalProCheckout.open({' . "\n";
    $payment_form .= '      amount:      "' . esc_js( number_format( it_exchange_get_cart_total( false ), 2, '', '' ) ) . '",' . "\n";
    $payment_form .= '      currency:    "' . esc_js( $general_settings['default-currency'] ) . '",' . "\n";
    $payment_form .= '      name:        "' . empty( $general_settings['company-name'] ) ? '' : esc_js( $general_settings['company-name'] ) . '",' . "\n";
    $payment_form .= '      description: "' . esc_js( it_exchange_get_cart_description() ) . '",' . "\n";
    $payment_form .= '      panelLabel:  "Checkout",' . "\n";
    $payment_form .= '      token:       token' . "\n";
    $payment_form .= '    });' . "\n";
    $payment_form .= '    return false;' . "\n";
    $payment_form .= '  });' . "\n";
    $payment_form .= '</script>' . "\n";

    $payment_form .= '</form>';
    $payment_form .= '</div>';

    return $payment_form;
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
            return __( 'Paid', 'it-l10n-exchange-addon-paypal-pro' );
        case 'refunded':
            return __( 'Refunded', 'it-l10n-exchange-addon-paypal-pro' );
        case 'partial-refund':
            return __( 'Partially Refunded', 'it-l10n-exchange-addon-paypal-pro' );
        case 'needs_response':
            return __( 'Disputed: PayPal Pro needs a response', 'it-l10n-exchange-addon-paypal-pro' );
        case 'under_review':
            return __( 'Disputed: Under review', 'it-l10n-exchange-addon-paypal-pro' );
        case 'won':
            return __( 'Disputed: Won, Paid', 'it-l10n-exchange-addon-paypal-pro' );
        default:
            return __( 'Unknown', 'it-l10n-exchange-addon-paypal-pro' );
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
