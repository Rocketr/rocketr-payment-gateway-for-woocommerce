<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Rocketr_Webhook {

	public static function constructWebhook($postVariables, $headerSignature, $ipnSecret) {

		if(!isset($postVariables) || sizeof($postVariables) === 0 || !isset($headerSignature)) {
			throw new WC_Rocketr_Payments_Exception('Received invalid webhook');
		}

		if(isset($postVariables['custom_fields']))
			$postVariables['custom_fields'] = html_entity_decode($postVariables['custom_fields']);

		$hmac = hash_hmac("sha512", json_encode($postVariables), trim($ipnSecret));
		if ($hmac !== $headerSignature) {
			throw new WC_Rocketr_Payments_Exception('IPN Hash does not match'); 
		}
		
		return new WC_Rocketr_Order($postVariables);
	}

}

?>