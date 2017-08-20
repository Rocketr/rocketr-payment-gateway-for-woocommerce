<?php
/**
 * Plugin Name: WooCommerce Rocketr.net Gateway
 * Description: This gateway allows you to easily accept a multitude of payment methods such as Bitcoin, Ethereum, Bitcoin Cash, PayPal, Stripe (Credit cards), Perfect Money, and more.
 * Author: Rocketr
 * Author URI: https://rocketr.net
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
//load the gateway
add_action( 'plugins_loaded', 'init_rocketr_gatway' );

function init_rocketr_gatway() {
	//define the gateway class

	abstract class rocketrOrderStatus {
	    const TIMED_OUT = -1; //This means the buyer did not pay
	    const NEW_ORDER = 0; //Order was just created, the buyer may or may not pay
	    const WAITING_FOR_PAYMENT = 1; //This is exclusive for cryptocurrency payments, this means we are waiting for confirmations
	    const ERROR_PARTIAL_PAYMENT_RECEIVED = 2; //the buyer only paid a partial amount
	    const FULL_PAYMENT_RECEIVED = 3; //this order status signifies that the product delivery failed (e.g. b/c the buyers email was incorrect or out of stock)
	    const PRODUCT_DELIVERED = 4; // AKA success. This signifies product email delivery
	    const REFUNDED = 5; //The order was refunded
	        
	    const UNKNOWN_ERROR = 6;
	    
	    const PAYPAL_PENDING = 8;
	    const PAYPAL_OTHER = 9; //if a paypal dispute is favored to the seller, this is the order status.
	    const PAYPAL_REVERSED = 10; //buyer disputed via paypal
	    
	    const STRIPE_AUTO_REFUND = 20;
	    const STRIPE_DECLINED = 21;
	    const STRIPE_DISPUTED = 22;
	    
	    public static function getName($id) {
			$class = new ReflectionClass(get_class($this));
		    $name = array_search($id, $class->getConstants(), TRUE);   
		    return $name;
	    }
	}


    class WC_Gateway_Rocketr extends WC_Payment_Gateway {

    	private $rocketr_username;
    	private $rocketr_ipn_secret;
    	private $ipn_url;
    	private $logger;
    	private $send_debug_email;
    	private $debug_email;

    	public function __construct() {
    		global $woocommerce;

    		$this->id = 'rocketr';
    		$this->has_fieds = false;
    		$this->icon =  WP_PLUGIN_URL . '/rocketr-payment-gateway-for-woocommerce/assets/images/icon.png';
    		$this->method_title = __('Rocketr.net', 'woocommerce-gateway-rocketr');
    		$this->method_description = __('<a href="https://rocketr.net">Rocketr</a> allows you to easily process Paypal, Stripe (credit cards), Bitcoin, Bitcoin Cash, Ethereum, Perfect Money, and more.', 'woocommerce-gateway-rocketr');
			
    		$this->init_form_fields();
			$this->init_settings();

			$this->rocketr_username = $this->get_option('rocketr_username');
			$this->rocketr_ipn_secret = $this->get_option('rocketr_ipn_secret');
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->send_debug_email = 'yes' === $this->get_option('send_debug_email');
			$this->debug_email = $this->get_option('debug_email');
			
			$this->enabled = $this->is_valid_for_use() ? 'yes': 'no'; 
			$this->ipn_url = add_query_arg('wc-api', 'WC_Gateway_Rocketr', home_url('/'));
			$this->logger = new WC_Logger();

			//save hook for settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			//ipn hook
			add_action( 'woocommerce_api_wc_gateway_rocketr', array( $this, 'check_ipn_response' ) );

			//use form
			add_action( 'woocommerce_receipt_rocketr', array( $this, 'receipt_page' ) );

    	}

    	function init_form_fields() {
	    	$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-gateway-rocketr' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Rocketr.net payment processing', 'woocommerce-gateway-rocketr' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce-gateway-rocketr' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-rocketr' ),
					'default' => __( 'Rocketr.net', 'woocommerce-gateway-rocketr' ),
					'desc_tip'      => true,
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce-gateway-rocketr' ),
					'type' => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-rocketr' ),
					'default' => __( 'Pay with a multitude of payment methods such as Bitcoin, Ethereum, Bitcoin Cash, PayPal, Stripe (Credit cards), Perfect Money, and more.', 'woocommerce-gateway-rocketr' )
				),
				'rocketr_username' => array(
					'title' => __( 'Rocketr Username', 'woocommerce-gateway-rocketr' ),
					'type' 			=> 'text',
					'description' => __( 'Please enter your Rocketr username.', 'woocommerce-gateway-rocketr' ),
					'default' => '',
				),
				'rocketr_ipn_secret' => array(
					'title' => __( 'Rocketr IPN Secret', 'woocommerce-gateway-rocketr' ),
					'type' 			=> 'text',
					'description' => __( 'Please enter your Rocketr IPN Secret. This can be found in your user settings at https://rocketr.net/seller/settings/account', 'woocommerce-gateway-rocketr' ),
					'default' => '',
				),
				'send_debug_email' => array(
					'title'   => __( 'Send Debug Emails', 'woocommerce-gateway-rocketr' ),
					'type'    => 'checkbox',
					'label'   => __( 'Receive email notifications for transactions through Rocketr.', 'woocommerce-gateway-rocketr' ),
					'default' => 'yes',
				),
				'debug_email' => array(
					'title'       => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-rocketr' ),
					'type'        => 'text',
					'description' => __( 'The e-mail address email notifications should be sent to.', 'woocommerce-gateway-rocketr' ),
					'default'     => get_option( 'admin_email' ),
				)
			);
	    }

	    public function process_payment($order_id) {
			$order = wc_get_order($order_id);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url(true),
			);
		}

		/**
		 * @return (string) url to redirect buyer to.
		*/
		public function generate_rocketr_form($order_id) {
			global $woocommerce;
			$orderObject = wc_get_order( $order_id );
			$orderObject->update_status('pending', __('New Order created', 'woocommerce-gateway-rocketr'));

			if($this->send_debug_email) {
				$body = "Hello,\n\nA new Order has been created through Rocketr for your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number();

				wp_mail( $this->debug_email, 'New Order through Rocketr', $body );
			}

			$url = 'https://rocketr.net/order/' . $this->rocketr_username . '/' .$orderObject->get_total();

			$params = [
				'ipn_url' => $this->ipn_url,
				'email' => $orderObject->get_billing_email(),
				'seller' => $this->rocketr_username,
				'title' => get_bloginfo('name') . ' - ' . $orderObject->get_order_number(),
				'customFields' => [
					'blogname' => get_bloginfo('name'),
					'wcorderid' => $orderObject->get_order_number(),
					'source' => 'WooCommerce/' . WC_VERSION . '; ' . get_site_url()
				],
				'price' => $orderObject->get_total(),
				'product_id' => 'order',
				'iframe' => '1'
			];

			$form = '<form method="POST" action="'.$url.'">';
			foreach ($params as $key => $value) {
				if(is_array($value)) {
					foreach ($value as $secondaryKey => $secondaryValue) {
						$form .= '<input type="hidden" name="' . $key . '[' . $secondaryKey . ']" value="' . $secondaryValue .'" />';
					}
				} else {
					$form .= '<input type="hidden" name="' . $key . '" value="' . $value .'" />';
				}
			}
			$form .= '<input type="submit" value="Click here to pay now" />';
			return $form;
		}

		/**
		 * Display text and a button to direct the user to Rocketr.
		 */
		public function receipt_page( $order_id ) {
			echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Rocketr.', 'woocommerce-gateway-rocketr' ) . '</p>';
			echo $this->generate_rocketr_form($order_id);
		}

	    public function check_ipn_response() {
	    	$result = $this->handleIPN($_POST);

	    	if($result[0] === false) {
	    		$error_message = "Error processing IPN:\t" . json_encode($result) . "\t" . json_encode($_REQUEST);
	    		$this->logger->add('rocketr', $error_message);
	    		wp_die($error_message, sizeof($result) === 3 ? intval($result[2]) : 400);
	    	}

	    	http_response_code(200);
	    	die('Success');
	    }

	    /**
		 * Handles the payment notification from Rocketr
		 *
		 * @return [success?, errorMessage (if !success), httpResponseCode (optional)]
	    */
	    public function handleIPN($post) {
	    	global $woocommerce;

	    	if(	!isset($post) || 
	    		sizeof($post) === 0 || 
	    		!isset($_SERVER['HTTP_IPN_HASH']) 
	    	) {
	    		return [false, 'Received Invalid IPN', 400];
			}
			$post['custom_fields'] = stripslashes_deep($post['custom_fields']);

			$hmac = hash_hmac("sha512", json_encode($post), trim($this->rocketr_ipn_secret));
			if ($hmac != $_SERVER['HTTP_IPN_HASH']) {
			    return [false, "IPN Hash does not match\tReceived: " . $_SERVER['HTTP_IPN_HASH'] . "\tExpected: " . $hmac , 401];
			}
			
			$rocketr_order_id = sanitize_text_field($post['order_id']);
			$status = intval($post['status']);
			$custom_fields = json_decode($post['custom_fields'], true);

			if(	!is_array($custom_fields) ||
				sizeof($custom_fields) === 0 ||
				!array_key_exists('wcorderid', $custom_fields) || 
				!is_numeric($custom_fields['wcorderid'])) {

				if($this->send_debug_email) {
					$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The rocketr Order ID is " . $rocketr_order_id . ". However, the WooCommerce Order ID is missing and we are unable to correlate the Rocketr order with the woocommerce order.";
					wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
				}
				return [false, 'Did not receive wcorderid'];
			}

			$order_id = intval($custom_fields['wcorderid']);

			$order = new WC_Order( $order_id );			
			if($order->get_total() != $invoice_amount_usd) {
				$order->add_order_note('Received IPN '. json_encode($post));
				$order->update_status('on-hold', 'The buyer did not pay the full amount. ');

				if($this->send_debug_email) {
					$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number() . "\n\nIt seems that the buyer did not pay the full amount, the buyer only paid " . $invoice_amount_usd . " instead of " . $order->get_total() . "\n\nThe order has been put on hold pending your attention.";

					wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
				}
				return [true, 'Buyer did not pay the full amount'];
			}

			$order->add_order_note('Received IPN '. json_encode($post));

			if($status == rocketrOrderStatus::TIMED_OUT ||
				$status == rocketrOrderStatus::STRIPE_AUTO_REFUND ||
				$status == rocketrOrderStatus::STRIPE_DECLINED
			) {	
				$order->update_status('cancelled', 'The order has been cancelled because the buyer did not pay');
			} else if($status == rocketrOrderStatus::WAITING_FOR_PAYMENT) {	
				$order->update_status('pending', 'The order is marked pending.');
			} else if($status == rocketrOrderStatus::FULL_PAYMENT_RECEIVED) {	
				//DO nothing
			} else if($status == rocketrOrderStatus::PRODUCT_DELIVERED) {	
				$order->payment_complete();
			} else {
				$order->update_status('on-hold', 'The order is on hold with an order status of ' . rocketrOrderStatus::getName($status));

				if($this->send_debug_email) {
					$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number() . "\n\nThe order status is: " . rocketrOrderStatus::getName($status) . " and the order has been put on hold pending your attention";

					wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
				}
			}

			return [true, 'Success'];
	    }

	    public function is_valid_for_use() {
	    	return true;
		}

    }

    //add the gateway to list of gateways
    add_filter( 'woocommerce_payment_gateways', 'rocketr_add_gateway' );
    function rocketr_add_gateway( $methods ) {
    	if (!in_array('WC_Gateway_Rocketr', $methods)) {
			$methods[] = 'WC_Gateway_Rocketr';
		}
		return $methods;
    }
}
<?php
/**
 * Plugin Name: WooCommerce Rocketr.net Gateway
 * Description: This gateway allows you to easily accept a multitude of payment methods such as Bitcoin, Ethereum, Bitcoin Cash, PayPal, Stripe (Credit cards), Perfect Money, and more.
 * Author: Rocketr
 * Author URI: https://rocketr.net
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
//load the gateway
add_action( 'plugins_loaded', 'init_rocketr_gatway' );

function init_rocketr_gatway() {
	//define the gateway class

	abstract class rocketrOrderStatus {
	    const TIMED_OUT = -1; //This means the buyer did not pay
	    const NEW_ORDER = 0; //Order was just created, the buyer may or may not pay
	    const WAITING_FOR_PAYMENT = 1; //This is exclusive for cryptocurrency payments, this means we are waiting for confirmations
	    const ERROR_PARTIAL_PAYMENT_RECEIVED = 2; //the buyer only paid a partial amount
	    const FULL_PAYMENT_RECEIVED = 3; //this order status signifies that the product delivery failed (e.g. b/c the buyers email was incorrect or out of stock)
	    const PRODUCT_DELIVERED = 4; // AKA success. This signifies product email delivery
	    const REFUNDED = 5; //The order was refunded
	        
	    const UNKNOWN_ERROR = 6;
	    
	    const PAYPAL_PENDING = 8;
	    const PAYPAL_OTHER = 9; //if a paypal dispute is favored to the seller, this is the order status.
	    const PAYPAL_REVERSED = 10; //buyer disputed via paypal
	    
	    const STRIPE_AUTO_REFUND = 20;
	    const STRIPE_DECLINED = 21;
	    const STRIPE_DISPUTED = 22;
	    
	    public static function getName($id) {
			$class = new ReflectionClass(get_class($this));
		    $name = array_search($id, $class->getConstants(), TRUE);   
		    return $name;
	    }
	}


    class WC_Gateway_Rocketr extends WC_Payment_Gateway {

    	private $rocketr_username;
    	private $rocketr_ipn_secret;
    	private $ipn_url;
    	private $logger;
    	private $send_debug_email;
    	private $debug_email;

    	public function __construct() {
    		global $woocommerce;

    		$this->id = 'rocketr';
    		$this->has_fieds = false;
    		$this->icon =  WP_PLUGIN_URL . '/rocketr-payment-gateway-for-woocommerce/assets/images/icon.png';
    		$this->method_title = __('Rocketr.net', 'woocommerce-gateway-rocketr');
    		$this->method_description = __('<a href="https://rocketr.net">Rocketr</a> allows you to easily process Paypal, Stripe (credit cards), Bitcoin, Bitcoin Cash, Ethereum, Perfect Money, and more.', 'woocommerce-gateway-rocketr');
			
    		$this->init_form_fields();
			$this->init_settings();

			$this->rocketr_username = $this->get_option('rocketr_username');
			$this->rocketr_ipn_secret = $this->get_option('rocketr_ipn_secret');
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->send_debug_email = 'yes' === $this->get_option('send_debug_email');
			$this->debug_email = $this->get_option('debug_email');
			
			$this->enabled = $this->is_valid_for_use() ? 'yes': 'no'; 
			$this->ipn_url = add_query_arg('wc-api', 'WC_Gateway_Rocketr', home_url('/'));
			$this->logger = new WC_Logger();

			//save hook for settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			//ipn hook
			add_action( 'woocommerce_api_wc_gateway_rocketr', array( $this, 'check_ipn_response' ) );

			//use form
			add_action( 'woocommerce_receipt_rocketr', array( $this, 'receipt_page' ) );

    	}

    	function init_form_fields() {
	    	$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-gateway-rocketr' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Rocketr.net payment processing', 'woocommerce-gateway-rocketr' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce-gateway-rocketr' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-rocketr' ),
					'default' => __( 'Rocketr.net', 'woocommerce-gateway-rocketr' ),
					'desc_tip'      => true,
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce-gateway-rocketr' ),
					'type' => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-rocketr' ),
					'default' => __( 'Pay with a multitude of payment methods such as Bitcoin, Ethereum, Bitcoin Cash, PayPal, Stripe (Credit cards), Perfect Money, and more.', 'woocommerce-gateway-rocketr' )
				),
				'rocketr_username' => array(
					'title' => __( 'Rocketr Username', 'woocommerce-gateway-rocketr' ),
					'type' 			=> 'text',
					'description' => __( 'Please enter your Rocketr username.', 'woocommerce-gateway-rocketr' ),
					'default' => '',
				),
				'rocketr_ipn_secret' => array(
					'title' => __( 'Rocketr IPN Secret', 'woocommerce-gateway-rocketr' ),
					'type' 			=> 'text',
					'description' => __( 'Please enter your Rocketr IPN Secret. This can be found in your user settings at https://rocketr.net/seller/settings/account', 'woocommerce-gateway-rocketr' ),
					'default' => '',
				),
				'send_debug_email' => array(
					'title'   => __( 'Send Debug Emails', 'woocommerce-gateway-rocketr' ),
					'type'    => 'checkbox',
					'label'   => __( 'Receive email notifications for transactions through Rocketr.', 'woocommerce-gateway-rocketr' ),
					'default' => 'yes',
				),
				'debug_email' => array(
					'title'       => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-rocketr' ),
					'type'        => 'text',
					'description' => __( 'The e-mail address email notifications should be sent to.', 'woocommerce-gateway-rocketr' ),
					'default'     => get_option( 'admin_email' ),
				)
			);
	    }

	    public function process_payment($order_id) {
			$order = wc_get_order($order_id);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url(true),
			);
		}

		/**
		 * @return (string) url to redirect buyer to.
		*/
		public function generate_rocketr_form($order_id) {
			global $woocommerce;
			$orderObject = wc_get_order( $order_id );
			$orderObject->update_status('pending', __('New Order created', 'woocommerce-gateway-rocketr'));

			if($this->send_debug_email) {
				$body = "Hello,\n\nA new Order has been created through Rocketr for your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number();

				wp_mail( $this->debug_email, 'New Order through Rocketr', $body );
			}

			$url = 'https://rocketr.net/order/' . $this->rocketr_username . '/' .$orderObject->get_total();

			$params = [
				'ipn_url' => $this->ipn_url,
				'email' => $orderObject->get_billing_email(),
				'seller' => $this->rocketr_username,
				'title' => get_bloginfo('name') . ' - ' . $orderObject->get_order_number(),
				'customFields' => [
					'blogname' => get_bloginfo('name'),
					'wcorderid' => $orderObject->get_order_number(),
					'source' => 'WooCommerce/' . WC_VERSION . '; ' . get_site_url()
				],
				'price' => $orderObject->get_total(),
				'product_id' => 'order',
				'iframe' => '1'
			];

			$form = '<form method="POST" action="'.$url.'">';
			foreach ($params as $key => $value) {
				if(is_array($value)) {
					foreach ($value as $secondaryKey => $secondaryValue) {
						$form .= '<input type="hidden" name="' . $key . '[' . $secondaryKey . ']" value="' . $secondaryValue .'" />';
					}
				} else {
					$form .= '<input type="hidden" name="' . $key . '" value="' . $value .'" />';
				}
			}
			$form .= '<input type="submit" value="Click here to pay now" />';
			return $form;
		}

		/**
		 * Display text and a button to direct the user to Rocketr.
		 */
		public function receipt_page( $order_id ) {
			echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Rocketr.', 'woocommerce-gateway-rocketr' ) . '</p>';
			echo $this->generate_rocketr_form($order_id);
		}

	    public function check_ipn_response() {
	    	$result = $this->handleIPN($_POST);

	    	if($result[0] === false) {
	    		$error_message = "Error processing IPN:\t" . json_encode($result) . "\t" . json_encode($_REQUEST);
	    		$this->logger->add('rocketr', $error_message);
	    		wp_die($error_message, sizeof($result) === 3 ? intval($result[2]) : 400);
	    	}

	    	http_response_code(200);
	    	die('Success');
	    }

	    /**
		 * Handles the payment notification from Rocketr
		 *
		 * @return [success?, errorMessage (if !success), httpResponseCode (optional)]
	    */
	    public function handleIPN($post) {
	    	global $woocommerce;

	    	if(	!isset($post) || 
	    		sizeof($post) === 0 || 
	    		!isset($_SERVER['HTTP_IPN_HASH']) 
	    	) {
	    		return [false, 'Received Invalid IPN', 400];
			}
			$post['custom_fields'] = stripslashes_deep($post['custom_fields']);

			$hmac = hash_hmac("sha512", json_encode($post), trim($this->rocketr_ipn_secret));
			if ($hmac != $_SERVER['HTTP_IPN_HASH']) {
			    return [false, "IPN Hash does not match\tReceived: " . $_SERVER['HTTP_IPN_HASH'] . "\tExpected: " . $hmac , 401];
			}
			
			$rocketr_order_id = sanitize_text_field($post['order_id']);
			$status = intval($post['status']);
			$custom_fields = json_decode($post['custom_fields'], true);

			if(	!is_array($custom_fields) ||
				sizeof($custom_fields) === 0 ||
				!array_key_exists('wcorderid', $custom_fields) || 
				!is_numeric($custom_fields['wcorderid'])) {

				if($this->send_debug_email) {
					$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The rocketr Order ID is " . $rocketr_order_id . ". However, the WooCommerce Order ID is missing and we are unable to correlate the Rocketr order with the woocommerce order.";
					wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
				}
				return [false, 'Did not receive wcorderid'];
			}

			$order_id = intval($custom_fields['wcorderid']);

			$order = new WC_Order( $order_id );			
			if($order->get_total() != $invoice_amount_usd) {
				$order->add_order_note('Received IPN '. json_encode($post));
				$order->update_status('on-hold', 'The buyer did not pay the full amount. ');

				if($this->send_debug_email) {
					$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number() . "\n\nIt seems that the buyer did not pay the full amount, the buyer only paid " . $invoice_amount_usd . " instead of " . $order->get_total() . "\n\nThe order has been put on hold pending your attention.";

					wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
				}
				return [true, 'Buyer did not pay the full amount'];
			}

			$order->add_order_note('Received IPN '. json_encode($post));

			if($status == rocketrOrderStatus::TIMED_OUT ||
				$status == rocketrOrderStatus::STRIPE_AUTO_REFUND ||
				$status == rocketrOrderStatus::STRIPE_DECLINED
			) {	
				$order->update_status('cancelled', 'The order has been cancelled because the buyer did not pay');
			} else if($status == rocketrOrderStatus::WAITING_FOR_PAYMENT) {	
				$order->update_status('pending', 'The order is marked pending.');
			} else if($status == rocketrOrderStatus::FULL_PAYMENT_RECEIVED) {	
				//DO nothing
			} else if($status == rocketrOrderStatus::PRODUCT_DELIVERED) {	
				$order->payment_complete();
			} else {
				$order->update_status('on-hold', 'The order is on hold with an order status of ' . rocketrOrderStatus::getName($status));

				if($this->send_debug_email) {
					$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number() . "\n\nThe order status is: " . rocketrOrderStatus::getName($status) . " and the order has been put on hold pending your attention";

					wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
				}
			}

			return [true, 'Success'];
	    }

	    public function is_valid_for_use() {
	    	return true;
		}

    }

    //add the gateway to list of gateways
    add_filter( 'woocommerce_payment_gateways', 'rocketr_add_gateway' );
    function rocketr_add_gateway( $methods ) {
    	if (!in_array('WC_Gateway_Rocketr', $methods)) {
			$methods[] = 'WC_Gateway_Rocketr';
		}
		return $methods;
    }
}

