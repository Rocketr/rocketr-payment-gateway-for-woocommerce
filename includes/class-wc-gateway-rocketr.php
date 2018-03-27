<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		
		// Don't hook anything else in the plugin if we're in an incompatible environment.
		if ( self::get_environment_warning() ) {
			return;
		}
		
		$this->id = 'rocketr';
		$this->has_fieds = false;
		$this->icon =  plugins_url( 'assets/images/icon.png', WC_ROCKETR_MAIN_FILE );
		$this->method_title = __('Rocketr - Paypal, BTC, BCH, ETH, LTC, Stripe, Perfect Money, and more', 'woocommerce-gateway-rocketr');
		$this->method_description = __('<a href="https://rocketr.net">Rocketr</a> allows you to easily process Paypal, Stripe (credit cards), Bitcoin, Bitcoin Cash, Ethereum, Perfect Money, and more.<br><strong> Please read our knowledgebase article <a href="https://support.rocketr.net">here</a> to learn how to get started.</strong>', 'woocommerce-gateway-rocketr');
		
		$this->init_form_fields();
		$this->init_settings();
		
		$this->rocketr_api_application_id = $this->get_option('rocketr_api_application_id');
		$this->rocketr_api_application_secret = $this->get_option('rocketr_api_application_secret');
		$this->rocketr_ipn_secret = $this->get_option('rocketr_ipn_secret');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->send_debug_email = 'yes' === $this->get_option('send_debug_email');
		$this->debug_email = $this->get_option('debug_email');
		$this->rocketr_payment_methods = $this->get_option('rocketr_payment_methods');
		
		$this->enabled = $this->is_valid_for_use() ? 'yes': 'no'; 
		$this->ipn_url = add_query_arg('wc-api', 'WC_Gateway_Rocketr', home_url('/'));
		$this->logger = new WC_Logger();
		
		
		WC_Rocketr_Payments::setApiKey($this->rocketr_api_application_id, $this->rocketr_api_application_secret);
		
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action( 'woocommerce_api_wc_gateway_rocketr', array( $this, 'check_ipn_response' ) );
		add_action( 'woocommerce_receipt_rocketr', array( $this, 'receipt_page' ) );
		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'woocommerce_thankyou', array($this, 'rocketr_order_received_page'));

	}
	
	//modified from stripe's plugin
	public function check_environment() {
		$environment_warning = $this->get_environment_warning();

		if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
		}
	}
	
	//from stripe's plugin
	public function get_environment_warning() {
		if ( version_compare( phpversion(), WC_ROCKETR_MIN_PHP_VER, '<' ) ) {
			/* translators: 1) int version 2) int version */
			$message = __( 'WooCommerce Rocketr - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-rocketr' );

			return sprintf( $message, WC_ROCKETR_MIN_PHP_VER, phpversion() );
		}

		if ( ! defined( 'WC_VERSION' ) ) {
			return __( 'WooCommerce Rocketr requires WooCommerce to be activated to work.', 'woocommerce-gateway-rocketr' );
		}

		if ( version_compare( WC_VERSION, WC_ROCKETR_MIN_WC_VER, '<' ) ) {
			/* translators: 1) int version 2) int version */
			$message = __( 'WooCommerce Rocketr - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-rocketr' );

			return sprintf( $message, WC_ROCKETR_MIN_WC_VER, WC_VERSION );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'WooCommerce Rocketr - cURL is not installed.', 'woocommerce-gateway-rocketr' );
		}

		return false;
	}

	public function init_form_fields() {
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
			'rocketr_api_application_id' => array(
				'title' => __( 'Rocketr API Application ID', 'woocommerce-gateway-rocketr' ),
				'type' 			=> 'text',
				'description' => __( 'Please enter your Rocketr <a href="https://rocketr.net/merchants/api" target="_blank">API Application ID</a>.', 'woocommerce-gateway-rocketr' ),
				'default' => '',
			),
			'rocketr_api_application_secret' => array(
				'title' => __( 'Rocketr API Application Secret', 'woocommerce-gateway-rocketr' ),
				'type' 			=> 'text',
				'description' => __( 'Please enter your Rocketr <a href="https://rocketr.net/merchants/api" target="_blank">API Application Secret</a>.', 'woocommerce-gateway-rocketr' ),
				'default' => '',
			),
			'rocketr_ipn_secret' => array(
				'title' => __( 'Rocketr IPN Secret', 'woocommerce-gateway-rocketr' ),
				'type' 			=> 'text',
				'description' => __( 'Please enter your Rocketr IPN Secret. This can be found in your user settings <a href="https://rocketr.net/merchants/settings/account" target="_blank">here</a>.', 'woocommerce-gateway-rocketr' ),
				'default' => '',
			),
			'rocketr_payment_methods' => array(
				'title' => __( 'Rocketr Payment methods', 'woocommerce-gateway-rocketr' ),
				'type' 			=> 'multiselect',
				'description' => __( 'Please select the payment methods you would like to accept.', 'woocommerce-gateway-rocketr' ),
				'default' => '',
				'options' => array(
					  'paypal' => 'Paypal',
					  'btc' => 'Bitcoin',
					  'bch' => 'Bitcoin Cash',
					  'eth' => 'Ethereum',
					  'ltc' => 'Litecoin',
					  'pm' => 'Perfect Money'
				 )
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
	
	/**
	 * Return icons based on which payment methods are enabled
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_string = '';
		$c = 0;
		foreach($this->rocketr_payment_methods as $method) {
			$icon_string .= '<img src="' . plugins_url( 'assets/images/' . esc_attr($method) . '.png', WC_ROCKETR_MAIN_FILE ) . '" alt="' . esc_attr($method) . '"  />';
			if($c == 3)
				$icon_string .= '<br>';
			$c++;
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon_string, $this->id );
	}

	/**
	 * https://docs.woocommerce.com/wc-apidocs/source-class-WC_Payment_Gateway.html#334-346
	 * 
	 * @return void
	 */
	public function payment_fields() {
		echo 
			'<div>' .
				'<label>Payment Method: </label>' .
				'<select class="select " name="rocketr-select-payment-method" id="rocketr-select-payment-method" style="">';
				foreach($this->rocketr_payment_methods as $pm) {
					if(strlen($pm) > 3)
						echo '<option value="' . esc_attr($pm) . '">' . esc_attr(ucfirst($pm)) . '</option>';
					else
						echo '<option value="' . esc_attr($pm) . '">' . esc_attr(strtoupper($pm)) . '</option>';
				}
		echo '</select></div>';
	}

	/**
	 * 
	 * 
	 * More info: https://docs.woocommerce.com/wc-apidocs/source-class-WC_Payment_Gateway.html#292-308
	 * @return
	 */
	public function process_payment($order_id) {
		$order = wc_get_order($order_id);
		if ( ! empty( $_POST['rocketr-select-payment-method'] ) ) {
			
			$payment_method = wc_clean($_POST['rocketr-select-payment-method']);
			
			wc_update_order_item_meta($order_id, '_rocketr_payment_method', $payment_method);
			
			if($payment_method == WC_Rocketr_Payment_Methods::StripePayment['name'] || $payment_method == WC_Rocketr_Payment_Methods::PerfectMoneyPayment['name']) {
				
				$result = $this->create_rocketr_order($order_id);
				
				if(!is_array($result) || sizeof($result) < 2 || $result[0] === false) {
					return array(
						'result'   => 'fail',
						'redirect' => '',
						
					);
				}
				
				$result = $result[1];
				return array(
					'result' => 'success',
					'redirect' => esc_html__($result['links']['invoice'], 'woocommerce-gateway-rocketr')
				);
			} else {
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);	
			}
		} else {
			wc_add_notice( __('Error: Payment Method not found', 'woocommerce-gateway-rocketr'), 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
				
			);
		}
	}
	
	
	public function receipt_page( $order_id ) {
		wp_register_script( 'wc_rocketr_payment_payment_instruction', plugins_url( 'assets/js/rocketr-payment-instructions.min.js', WC_ROCKETR_MAIN_FILE), array( 'jquery' ), WC_STRIPE_VERSION, true );
		$result = $this->show_rocketr_payment_instructions($order_id);
		if($result === false) {
			wc_add_notice( __('There was an error creating your order. Please try again', 'woocommerce-gateway-rocketr'), 'error' );
			return;	
		}
	}

	public function show_rocketr_payment_instructions($order_id) {
		global $woocommerce;
		$orderObject = wc_get_order( $order_id );
		
		$payment_method = wc_get_order_item_meta($order_id, '_rocketr_payment_method');
		if($payment_method === null || strlen($payment_method) === 0)
			return false;
		
		$rocketr_order = $this->create_rocketr_order($order_id);
		
		if(!is_array($rocketr_order) || sizeof($rocketr_order) < 2 || $rocketr_order[0] === false) {
			if(sizeof($rocketr_order) > 1)
				wc_add_notice( esc_attr__($rocketr_order[1], 'woocommerce-gateway-rocketr'), 'error');
			
			return false;
		}
		
		$result = $rocketr_order[1];
		
		$rocketr_payment_method = WC_Rocketr_Payment_Methods::getConstFromName($payment_method);
		
		switch($rocketr_payment_method) {
			case WC_Rocketr_Payment_Methods::BitcoinPayment:
			case WC_Rocketr_Payment_Methods::BitcoinCashPayment:
			case WC_Rocketr_Payment_Methods::LitecoinPayment:
			case WC_Rocketr_Payment_Methods::EtherPayment:
				echo '<h3>Please send <code>' . esc_html__($result['paymentInstructions']['amount'], 'woocommerce-gateway-rocketr') . '</code> ' . esc_html__($result['paymentInstructions']['currencyText'], 'woocommerce-gateway-rocketr') . ' to <code>' . esc_html__($result['paymentInstructions']['address'], 'woocommerce-gateway-rocketr') . '</code></h3>';
				echo '<ul class="order_details">';
					echo '<li>' . esc_html__($rocketr_payment_method['prettyName'], 'woocommerce-gateway-rocketr') . ' Amount<strong>' . esc_html__($result['paymentInstructions']['amount'], 'woocommerce-gateway-rocketr') . '</strong></li>';
					echo '<li>' . esc_html__($rocketr_payment_method['prettyName'], 'woocommerce-gateway-rocketr') . ' Address To Send To<strong>' . esc_html__($result['paymentInstructions']['address'], 'woocommerce-gateway-rocketr') . '</strong></li>';
					echo '<li>QR Code<strong><img src="' . esc_html__($result['paymentInstructions']['qrImage'], 'woocommerce-gateway-rocketr') . '" /></strong></li>';
					echo '<li>Wallet Link<strong><a href="'. esc_html__($result['paymentInstructions']['qrText'], 'woocommerce-gateway-rocketr') .'">Click here to pay with your wallet</a>';
					echo '<li>Powered By <a href="https://rocketr.net" target="_blank">Rocketr</a></li>';
				echo '</ul>';
				
				wp_enqueue_script('wc_rocketr_payment_payment_instruction');
				$data_to_pass = [
					'rocketr_api_application_id' => $this->rocketr_api_application_id,
					'rocketr_order_id' => wc_get_order_item_meta($order_id, '_rocketr_order_id'),
					'redirect_url' => $this->get_return_url($orderObject)
				];
				wp_localize_script('wc_rocketr_payment_payment_instruction', 'wc_rocketr_passed_data', $data_to_pass);
				return true;
				break;
			case WC_Rocketr_Payment_Methods::PaypalMarketplacePayment:
			case WC_Rocketr_Payment_Methods::PaypalPayment:
				echo '<h3>' . __('Please click the button below to continue to the payment', 'woocommerce-gateway-rocketr') . '</h3>';
				echo '<a href="' . esc_html__($result['paymentInstructions']['urlToRedirect'], 'woocommerce-gateway-rocketr') . '" class="checkout-button button alt wc-forward">Pay now</a>';
				return true;
				break;
			default:
				$this->logger->add('rocketr', $rocketr_payment_method . ' not found');
				return false;
				break;
		}
	}
	

	/**
	 * Creates a Rocketr order from the woocommerce order or returns instructions of an order if it was already previously created.
	 * 
	 * @return [bool? success, (error message || rocketr api result)]
	 */
	public function create_rocketr_order($order_id) {
		global $woocommerce;
		$orderObject = wc_get_order( $order_id );
		
		$payment_method = wc_get_order_item_meta($order_id, '_rocketr_payment_method');
		if($payment_method === null || strlen($payment_method) === 0)
			return [false, 'Payment method not found'];
		
			
		
		try {
			
			$possible_order_id = wc_get_order_item_meta($order_id, '_rocketr_order_id');
			if(isset($possible_order_id) && strlen($possible_order_id) > 0) {
				$instructions = WC_Rocketr_Order::getPaymentInstructionsForOrder($possible_order_id);
				if($instructions['paymentMethod'] == $payment_method)
					return [true, $instructions];
			}
			
			
			$o = new WC_Rocketr_Order();
			
			$rocketr_payment_method = WC_Rocketr_Payment_Methods::getConstFromName($payment_method);
			
			$o->setPaymentMethod(WC_Rocketr_Payment_Methods::getConstFromName($payment_method));
			$o->setAmount($orderObject->get_total());
			$billing_email = (version_compare( WC_VERSION, '2.7', '<' )) ? $orderObject->billing_email : $orderObject->get_billing_email(); 

			$o->setBuyerEmail($billing_email);
			$o->addCustomField('wc_order_id', $orderObject->get_order_number());
			$o->addCustomField('wc_blog_url', get_site_url());
			$o->addCustomField('wc_blog_name', get_bloginfo('name'));
			$o->setShippingAddress($this->get_rocketr_shipping_info($orderObject));
			$o->setBuyerIp($_SERVER['REMOTE_ADDR']);
			
			$o->setIpnUrl($this->ipn_url);
		
			$result = $o->createOrder();
			
			$orderObject->update_status('pending', __('New Order created', 'woocommerce-gateway-rocketr'));
			$orderObject->add_order_note(esc_html__('Rocketr Order #' . $result['orderIdentifier'] . ' created', 'woocommerce-gateway-rocketr'));
			
			wc_update_order_item_meta($order_id, '_rocketr_order_id', esc_html__($result['orderIdentifier'], 'woocommerce-gateway-rocketr'));
			
			
			if($this->send_debug_email) {
				$body = "Hello,\n\nA new Order has been created through Rocketr for your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number();
				wp_mail( $this->debug_email, 'New Order through Rocketr', $body );
			}
			
			return [true, $result];
			
		} catch (WC_Rocketr_Payments_Exception $e) {
			return [false, $e->getMessage()];
		} catch (WC_Rocketr_Payments_API_Exception $e) {
			return [false, $e->getMessage()];
		} catch(Exception $e) {
			return [false, 'An unknown exception occured'];
		}
		
	}
	
	public function rocketr_order_received_page($order_id) {
		echo '<h3>Rocketr Order ID:</h3> ' . wc_get_order_item_meta($order_id, '_rocketr_order_id');
	}
	
	/**
	 * Hook method for the ipn
	 * 
	 * @return
	 */
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
			!array_key_exists('wc_order_id', $custom_fields) || 
			!is_numeric($custom_fields['wc_order_id'])) {
			if($this->send_debug_email) {
				$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The rocketr Order ID is " . $rocketr_order_id . ". However, the WooCommerce Order ID is missing and we are unable to correlate the Rocketr order with the woocommerce order.";
				wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
			}
			return [false, 'Did not receive wc_order_id'];
		}
		$order_id = intval($custom_fields['wc_order_id']);
		$invoice_amount_usd = floatval($post['invoice_amount_usd']);
		
		$order = new WC_Order( $order_id );		
		
		if($order->get_total() > $invoice_amount_usd) {
			$order->add_order_note('Received IPN '. json_encode($post));
			$order->update_status('on-hold', 'The buyer did not pay the full amount. The buyer only paid ' . $invoice_amount_usd . ' instead of' . $order->get_total());
			if($this->send_debug_email) {
				$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The order ID is: " . $order->get_order_number() . "\n\nIt seems that the buyer did not pay the full amount, the buyer only paid " . $invoice_amount_usd . " instead of " . $order->get_total() . "\n\nThe order has been put on hold pending your attention.";
				wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
			}
			return [true, 'Buyer did not pay the full amount'];
		}
		$order->add_order_note('Received IPN '. json_encode($post));
		if($status == WC_Rocketr_Order_Status::UNPAID ||
			$status == WC_Rocketr_Order_Status::STRIPE_AUTO_REFUND ||
			$status == WC_Rocketr_Order_Status::STRIPE_DECLINED
		) {	
			$order->update_status('cancelled', 'The order has been cancelled because the buyer did not pay');
		} else if($status == WC_Rocketr_Order_Status::WAITING_FOR_PAYMENT) {	
			$order->update_status('pending', 'The order is marked pending.');
		} else if($status == WC_Rocketr_Order_Status::FULL_PAYMENT_RECEIVED) {	
			//DO nothing
		} else if($status == WC_Rocketr_Order_Status::PRODUCT_DELIVERED) {	
			$order->payment_complete();
		} else {
			$order->update_status('on-hold', 'The order is on hold with an order status of ' . WC_Rocketr_Order_Status::getName($status));
			if($this->send_debug_email) {
				$body = "Hello,\n\n There is a problem with a Rocketr order from your blog: " . get_bloginfo('name') . ". The order ID is: " . $orderObject->get_order_number() . "\n\nThe order status is: " . WC_Rocketr_Order_Status::getName($status) . " and the order has been put on hold pending your attention";
				wp_mail( $this->debug_email, 'Problem with an order through Rocketr', $body );
			}
		}
		return [true, 'Success'];
	}


	/**
	 * If the order has shipping information, it is sent to Rocketr. This is for maxmind fraud factors
	 * 
	 * @return array
	 */
	//https://github.com/angelleye/paypal-woocommerce/blob/fa2fa2b11b1d2f170fb12f42f6256be2b7448f76/angelleye-includes/paypal-rest-api-utility.php
	public function get_rocketr_shipping_info($order) {
		if ($order->needs_shipping_address()) {
			$shipping_first_name = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_first_name : $order->get_shipping_first_name();
			$shipping_last_name = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_last_name : $order->get_shipping_last_name();
			$shipping_address_1 = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_address_1 : $order->get_shipping_address_1();
			$shipping_address_2 = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_address_2 : $order->get_shipping_address_2();
			$shipping_city = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_city : $order->get_shipping_city();
			$shipping_state = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_state : $order->get_shipping_state();
			$shipping_postcode = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_postcode : $order->get_shipping_postcode();
			$shipping_country = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_country : $order->get_shipping_country();
			
			return array(
				'addressName' => $shipping_first_name . $shipping_last_name,
				'addressLine1' => $shipping_address_1,
				'addressLine2' => $shipping_address_2,
				'addressCity' => $shipping_city,
				'addressState' => $shipping_state,
				'addressZip' => $shipping_postcode,
				'addressCountry' => $shipping_country
			);
		}
		return array();
	}
	
	public function is_valid_for_use() {
		if(strlen($this->rocketr_api_application_id) > 0 && strlen($this->rocketr_api_application_secret) > 0&& strlen($this->rocketr_ipn_secret) > 0)
			return true;
		else
			return false;
	}
	
	/**
	 * unused for now
	 */
	public function show_rocketr_order_button() {
		if(get_post_meta( $order->id, '_payment_method', true ) == 'rocketr') {
			echo '<div><h4>' . __('Rocketr Payment Information', 'woocommerce-gateway-rocketr') . '</h4>';
			$possible_rocketr_order = esc_html__(wc_get_order_item_meta($order->id, '_rocketr_order_id'), 'woocommerce-gateway-rocketr') ;
				if($possible_rocketr_order !== null) {
					echo '<a href="https://rocketr.net/merchants/payments/' . $possible_rocketr_order . '">' . $possible_rocketr_order . '</a>';
				}
			echo '</div>';
		}
	}
}