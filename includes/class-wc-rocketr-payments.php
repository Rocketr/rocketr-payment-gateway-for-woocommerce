<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Rocketr_Payments {
	
	//@var String | Application ID
	public static $applicationId;
	
	//@var String | Application Secret
	public static $applicationSecret;

	//@var WC_Rocketr_Api_Handler | Used to make the requests
	public static $apiHandler;

	/**
	 * Sets the API credentials to be used
	 *
	 * @param string $applicationId
	 * @param string $applicationSecret
	 */
	public static function setApiKey($applicationId, $applicationSecret) {
		self::$applicationId = $applicationId;
		self::$applicationSecret = $applicationSecret;
		self::$apiHandler = new WC_Rocketr_Api_Handler($applicationId, $applicationSecret);
	}


	/**
	 * Returns the API Handler
	 *
	 * @throws WC_Rocketr_Payments_Exception
	 */
	public static function getApiHandler() {
		if(!isset(self::$apiHandler)) {
			if(!isset(self::$applicationId) || !isset(self::$applicationSecret))
				throw new WC_Rocketr_Payments_Exception('API Credentials not set');
			
			self::$apiHandler = new WC_Rocketr_Api_Handler(self::$applicationId, self::$applicationSecret);
			return self::$apiHandler;
		} else {
			return self::$apiHandler;
		}
	}
}

?>