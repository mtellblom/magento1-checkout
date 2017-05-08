<?php
use Svea\WebPay\WebPay;

/**
 * Class Svea_Checkout_Model_Checkout_Api_Init
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Checkout_Api_Init
    extends Mage_Core_Model_Abstract
{

    /**
     * Setup communication.
     *
     * @return \Svea\Checkout\CheckoutClient
     */
    public function setupCommunication()
    {
        $sveaConfig = new Svea_Checkout_Model_Checkout_Api_Configuration();
        $this->setSveaOrder(WebPay::checkout($sveaConfig));

        return $this->getSveaOrder();
    }
}
