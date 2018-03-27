<?php
/**
 * Plugin Name: WooCommerce Rocketr.net Gateway
 * Description: This gateway allows you to easily accept a multitude of payment methods such as Bitcoin, Ethereum, Bitcoin Cash, PayPal, Litecoin, and more.
 * Author: Rocketr
 * Author URI: https://rocketr.net
 * Version: 2.0.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//FROM Stripe's plugin
define( 'WC_ROCKETR_VERSION', '2.0.1' );
define( 'WC_ROCKETR_MIN_PHP_VER', '5.6.0' );
define( 'WC_ROCKETR_MIN_WC_VER', '2.6.0' );
define( 'WC_ROCKETR_MAIN_FILE', __FILE__ );
define( 'WC_ROCKETR_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_ROCKETR_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


//load the gateway
add_action( 'plugins_loaded', 'init_rocketr_gatway' );
add_action( 'wp_enqueue_scripts', 'scripts');
function init_rocketr_gatway() {
	
	require_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-rocketr.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-api-handler.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-order-status.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-order.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-payment-methods.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-payments-api-exception.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-payments-exception.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-payments.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-webhook-exception.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-shipping-address.php');
	require_once( dirname( __FILE__ ) . '/includes/class-wc-rocketr-webhook.php');
	
	add_filter( 'woocommerce_payment_gateways', 'rocketr_add_gateway' );
	function rocketr_add_gateway( $methods ) {
		if (!in_array('WC_Gateway_Rocketr', $methods)) {
			$methods[] = 'WC_Gateway_Rocketr';
		}
		return $methods;
	}
}

function scripts() {
	wp_register_script( 'wc_rocketr_payment', plugins_url( 'assets/js/rocketr-payment.min.js', __FILE__), array( 'jquery' ));
	wp_enqueue_script('wc_rocketr_payment');
}