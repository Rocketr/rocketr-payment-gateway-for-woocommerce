<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Rocketr_Order {

	//@var String | This is an identifier for the order.
	private $orderIdentifier;

	//@var String | This is the email of the buyer. If the send_buyer_emails flag is set true on order creation, the buyer will receive an email about the order to this email address
	private $buyerEmail;

	//@var String | The IP Address associated with the order.
	private $buyerIp;

	//@var PaymentMethod | The Payment Method associated with this order. Please see the PaymentMethod specifications for details.
	private $paymentMethod;

	//@var decimal | The amount of the order. The amount is in the currency of the order.
	private $amount;

	//@var String | Any notes about the order.
	private $notes;

	//@var JSON Array | Any Custom Fields associated with the order.
	private $customFields;

	//@var int | The status of the order. Please see the Status specifications for details.
	private $status;

	//@var timestamp | The timestamp when the order was created.
	private $purchasedAt;

	//@var String | The country code of the IP used to create the order.
	private $countryCode;

	//@var String | The URL where webhooks will be sent at for this order
	private $ipnUrl;

	//@var JSON Array | The shipping address associated with the order
	private $shippingAddress;

	//@var String | The invoice identifier associated with the order; Only filled after order has been created.
	private $invoiceIdentifier;
	//@var JSON Array | Contains instruction on how to pay for the order; Only filled after order has been created.
	private $paymentInsructions;

	private $apiHandler;

	/**
	 * Retreives previous orders. By default this is fetches 25 orders at a time
	 */
	public static function getOrders($additionalQuery = '') {
		$apiHandler = WC_Rocketr_Payments::getApiHandler();
		$response = $apiHandler->performGetRequest('/orders/list' . ((strlen($additionalQuery) > 0 && substr($additionalQuery, 0, 1) !== '?') ? '?' . $additionalQuery : $additionalQuery));

		return $response[1];
	}
	
	/**
	 * Retreives payment instructions for a particular order
	 */
	public static function getPaymentInstructionsForOrder($rocketrOrderId) {
		$apiHandler = WC_Rocketr_Payments::getApiHandler();
		$response = $apiHandler->performGetRequest('/orders/' . $rocketrOrderId .'/payment-instructions');

		return $response[1];
	}
	
	public function __construct($row = []) {

		if(isset($row) && is_array($row) && sizeof($row) > 0) {
			$this->createFromArray($row);
		} else {
			$this->apiHandler = WC_Rocketr_Payments::getApiHandler();
			$this->orderIdentifier = '';
			$this->buyerEmail = '';
			$this->buyerIp = '';
			$this->paymentMethod = '';
			$this->amount = '';
			$this->notes = '';
			$this->customFields = [];
			$this->status = '';
			$this->purchasedAt = '';
			$this->countryCode = '';
			$this->ipnUrl = '';
			$this->shippingAddress = [];

			$this->invoiceIdentifier = null;
			$this->paymentInstructions = ['error' => 'Order has not been created yet'];
		}
	}

	/**
	 * Creates an order
	 *
	 * @return associative array, response 
	 */
	public function createOrder() {

		if(strlen($this->orderIdentifier) > 0)
			throw new WC_Rocketr_Payments_Exception('Error creating an order, this order object has already been created');

		$response = $this->apiHandler->performPostRequest('/orders/create', $this->serializeJSON());

		if(isset($response['success']) && $response['success'] == true) {
			$this->orderIdentifier = $response['orderIdentifier'];
			$this->invoiceIdentifier = $response['invoiceIdentifier'];
			$this->paymentInstructions = $response['paymentInstructions'];
		}

		return $response[1];
	}
	
	private function serializeJSON() {
		$toEncode = [
			'buyerEmail' => $this->buyerEmail,
			'buyerIp' => $this->buyerIp,
			'paymentMethod' => $this->paymentMethod,
			'amount' => $this->amount,
			'notes' => $this->notes,
			'customFields' => $this->customFields,
			'countryCode' => $this->countryCode,
			'ipnUrl' => $this->ipnUrl,
			'shippingAddress' => $this->shippingAddress
		];
		if(strlen($this->invoiceIdentifier) > 0)
			$toEncode['invoiceIdentifier'] = $this->invoiceIdentifier;
		return json_encode($toEncode);
	}

	/**
	 * Converts a JSON object to an Order object
	**/
	private function createFromArray($arr) {
		$this->orderIdentifier = isset($arr['orderIdentifier']) ? $arr['orderIdentifier'] : null;
		$this->buyerEmail = isset($arr['buyerEmail']) ? $arr['buyerEmail'] : null;
		$this->buyerIp = isset($arr['buyerIp']) ? $arr['buyerIp'] : null;
		$this->paymentMethod = isset($arr['paymentMethod']) ? WC_Rocketr_Payment_Methods::getConstFromId(intval($arr['paymentMethod'])) : null;
		$this->amount = isset($arr['amount']) ? floatval($arr['amount']) : null;
		$this->notes = isset($arr['notes']) ? $arr['notes'] : null;
		$this->customFields = isset($arr['customFields']) ? $arr['customFields'] : null;
		$this->status = isset($arr['status']) ? intval($arr['status']) : null;
		$this->purchasedAt = isset($arr['purchasedAt']) ? intval($arr['purchasedAt']) : null;
		$this->countryCode = isset($arr['countryCode']) ? $arr['countryCode'] : null;
		
		$this->ipnUrl = isset($arr['ipnUrl']) ? $arr['ipnUrl'] : null;
		$this->shippingAddress = isset($arr['shippingAddress']) ? $arr['shippingAddress'] : null;
		$this->invoiceIdentifier = isset($arr['invoiceIdentifier']) ? $arr['invoiceIdentifier'] : null;
		$this->paymentInsructions = isset($arr['paymentInsructions']) ? $arr['paymentInsructions'] : null;
	}


	public function addCustomField($key, $value){
		$this->customFields[$key] = $value;
	}
	
	public function setPaymentMethod($paymentMethod){
		if(is_array($paymentMethod)) {
			$this->paymentMethod = $paymentMethod['name'];
			return true;
		}

		if(is_int($paymentMethod))
			$this->paymentMethod = WC_Rocketr_Payment_Methods::getConstFromId($paymentMethod)['name'];
		elseif(is_string($paymentMethod))
			$this->paymentMethod = WC_Rocketr_Payment_Methods::getConstFromName($paymentMethod)['name'];
		else
			return false;

		return true;
	}

	public function setShippingAddress($shippingAddress) {
		if($shippingAddress instanceof WC_Shipping_Address)
			$this->shippingAddress = $shippingAddress->asArray();
		elseif(is_array($shippingAddress))
			$this->shippingAddress = $shippingAddress;
		else
			throw new WC_Rocketr_Payments_Exception('WC_Shipping_Address is not valid');
	}

	public function getOrderIdentifier(){
		return $this->orderIdentifier;
	}

	public function getBuyerEmail(){
		return $this->buyerEmail;
	}

	public function setBuyerEmail($buyerEmail){
		$this->buyerEmail = $buyerEmail;
	}

	public function getBuyerIp(){
		return $this->buyerIp;
	}

	public function setBuyerIp($buyerIp){
		$this->buyerIp = $buyerIp;
	}

	public function getPaymentMethod(){
		return $this->paymentMethod;
	}

	public function getAmount(){
		return $this->amount;
	}

	public function setAmount($amount){
		$this->amount = $amount;
	}

	public function getNotes(){
		return $this->notes;
	}

	public function setNotes($notes){
		$this->notes = $notes;
	}

	public function getCustomFields(){
		return $this->customFields;
	}

	public function getStatus(){
		return $this->status;
	}

	public function setStatus($status){
		$this->status = $status;
	}

	public function getPurchasedAt(){
		return $this->purchasedAt;
	}

	public function getCountryCode(){
		return $this->countryCode;
	}

	public function setCountryCode($countryCode){
		$this->countryCode = $countryCode;
	}

	public function getIpnUrl(){
		return $this->ipnUrl;
	}

	public function setIpnUrl($ipnUrl){
		$this->ipnUrl = $ipnUrl;
	}

	public function getShippingAddress(){
		return $this->shippingAddress;
	}

}

?>