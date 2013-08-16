<?php
/**
 * iThemes Exchange PayPal Pro Add-on
 * @package IT_Exchange_Addon_PayPal_Pro
 * @since 1.0.0
*/

/**
 * Exchange Transaction Add-ons require several hooks in order to work properly.
 * Most of these hooks are called in api/transactions.php and are named dynamically
 * so that individual add-ons can target them. eg: it_exchange_refund_url_for_paypal_pro
 * We've placed them all in one file to help add-on devs identify them more easily
*/
include( 'lib/required-hooks.php' );

/**
 * Exchange will build your add-on's settings page for you and link to it from our add-on
 * screen. You are free to link from it elsewhere as well if you'd like... or to not use our API
 * at all. This file has all the functions related to registering the page, printing the form, and saving
 * the options. This includes the wizard settings. Additionally, we use the Exchange storage API to
 * save / retreive options. Add-ons are not required to do this.
*/
include( 'lib/addon-settings.php' );

/**
 * The following file contains utility functions specific to our PayPal Pro add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for PayPal Pro, etc.
*/
include( 'lib/addon-functions.php' );

/**
 * The following file contains the PayPal Pro PHP Rest API SDK Library
 *
 * @link https://github.com/paypal/rest-api-sdk-php
*/
//include( 'lib/paypal-pro.php' );
