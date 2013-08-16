<?php

abstract class PayPal_Pro
{
    public static $user;
    public static $pass;
    public static $format = "json";
    public static $apiBaseUrl = "https://www.2checkout.com/api/";
    public static $error;
    const VERSION = '0.1.2';

    static function setCredentials($user, $pass)
    {
        self::$user = $user;
        self::$pass = $pass;
    }
}

require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutAccount.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutPayment.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutApi.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutSale.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutProduct.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutCoupon.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutOption.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutUtil.php');
require(dirname(__FILE__) . '/paypal-pro/Api/TwocheckoutError.php');
require(dirname(__FILE__) . '/paypal-pro/TwocheckoutReturn.php');
require(dirname(__FILE__) . '/paypal-pro/TwocheckoutNotification.php');
require(dirname(__FILE__) . '/paypal-pro/TwocheckoutCharge.php');
require(dirname(__FILE__) . '/paypal-pro/TwocheckoutMessage.php');
