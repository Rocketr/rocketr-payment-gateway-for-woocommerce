<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Rocketr_Payments_Exception extends \Exception {

	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);

    }
}

?>