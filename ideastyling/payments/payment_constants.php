<?php
/**
 * Gate Wey Access Management System
 * Payment Gateway API Constants
 * 
 * This file contains all the payment gateway API constants used in the system.
 * In a production environment, these should be stored securely.
 */

// Paystack API Credentials
define('PAYSTACK_SECRET_KEY', 'sk_live_e73989ba9dd5d574caab776244cbc30571c15804');
define('PAYSTACK_PUBLIC_KEY', 'pk_live_cf7b15b3e75a73201b6786c2c0445a6df61d65d9');

// Flutterwave API Credentials
define('FLUTTERWAVE_SECRET_KEY', 'FLWSECK_TEST-your_flutterwave_secret_key');
define('FLUTTERWAVE_PUBLIC_KEY', 'FLWPUBK_TEST-your_flutterwave_public_key');

// Monnify API Credentials
define('MONNIFY_SECRET_KEY', 'your_monnify_secret_key');
define('MONNIFY_PUBLIC_KEY', 'your_monnify_public_key');
define('MONNIFY_MERCHANT_CODE', 'your_monnify_merchant_code');

// Bank Transfer Details (for manual transfers)
define('BANK_NAME', 'Example Bank');
define('BANK_ACCOUNT_NAME', 'Gate Wey Inc.');
define('BANK_ACCOUNT_NUMBER', '1234567890');
define('BANK_ROUTING_NUMBER', '987654321');
define('BANK_SWIFT_CODE', 'EXAMPLEXXX');
?>