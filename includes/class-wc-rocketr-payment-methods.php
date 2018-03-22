<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Rocketr_Payment_Methods {
    const PaypalPayment = [
        "id" => 0,
        "name" => "paypal",
        "prettyName" => "Paypal"
    ];
    const BitcoinPayment = [
        "id" => 1,
        "name" => "btc",
        "prettyName" => "Bitcoin"
    ];
    const EtherPayment = [
        "id" => 2,
        "name" => "eth",
        "prettyName" => "Ethereum"
    ];
    
    const PerfectMoneyPayment = [
        "id" => 3,
        "name" => "pm",
        "prettyName" => "Perfect Money"
    ];
    
    const StripePayment = [
        "id" => 4,
        "name" => "stripe",
        "prettyName" => "Stripe (Credit Card)"
    ];
    
    /**
     * Only used internally. In case id 5 needs to be resolved back to paypal
     *
     */
    const PaypalMarketplacePayment = [
        "id" => 5,
        "name" => "paypal",
        "prettyName" => "Paypal"
    ];
    
    const BitcoinCashPayment = [
        "id" => 6,
        "name" => "bch",
        "prettyName" => "Bitcoin Cash"
    ];
    
    const LitecoinPayment = [
        "id" => 7,
        "name" => "ltc",
        "prettyName" => "Litecoin"
    ];

    public static function getConstFromId($id){
        switch($id){
            case WC_Rocketr_Payment_Methods::PaypalPayment['id']:
                return WC_Rocketr_Payment_Methods::PaypalPayment;
            break;
            case WC_Rocketr_Payment_Methods::BitcoinPayment['id']:
                return  WC_Rocketr_Payment_Methods::BitcoinPayment;
            break;
            case WC_Rocketr_Payment_Methods::EtherPayment['id']:
                return  WC_Rocketr_Payment_Methods::EtherPayment;
            break;
            case WC_Rocketr_Payment_Methods::PerfectMoneyPayment['id']:
                return  WC_Rocketr_Payment_Methods::PerfectMoneyPayment;
            break;
            case WC_Rocketr_Payment_Methods::StripePayment['id']:
                return  WC_Rocketr_Payment_Methods::StripePayment;
            break;
            case WC_Rocketr_Payment_Methods::BitcoinCashPayment['id']:
                return  WC_Rocketr_Payment_Methods::BitcoinCashPayment;
            case WC_Rocketr_Payment_Methods::PaypalMarketplacePayment['id']:
                return WC_Rocketr_Payment_Methods::PaypalMarketplacePayment;
            break;
            default:
                throw new WC_Rocketr_Payments_Exception("PaymentMethod not found" . $id);
        }
    }
    
    public static function getConstFromName($name){
        switch($name){
            case WC_Rocketr_Payment_Methods::PaypalPayment['name']:
                return WC_Rocketr_Payment_Methods::PaypalPayment;
            break;
            case WC_Rocketr_Payment_Methods::BitcoinPayment['name']:
                return  WC_Rocketr_Payment_Methods::BitcoinPayment;
            break;
            case WC_Rocketr_Payment_Methods::EtherPayment['name']:
                return  WC_Rocketr_Payment_Methods::EtherPayment;
            break;
            case WC_Rocketr_Payment_Methods::PerfectMoneyPayment['name']:
                return  WC_Rocketr_Payment_Methods::PerfectMoneyPayment;
            break;
            case WC_Rocketr_Payment_Methods::StripePayment['name']:
                return  WC_Rocketr_Payment_Methods::StripePayment;
            break;
            case WC_Rocketr_Payment_Methods::PaypalMarketplacePayment['name']:
                return WC_Rocketr_Payment_Methods::PaypalMarketplacePayment;
            case WC_Rocketr_Payment_Methods::BitcoinCashPayment['name']:
                return  WC_Rocketr_Payment_Methods::BitcoinCashPayment;
            break;
            default:
                throw new WC_Rocketr_Payments_Exception("PaymentMethod not found" . $name);
        }
    }
    
}

?>