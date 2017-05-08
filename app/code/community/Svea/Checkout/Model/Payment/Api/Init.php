<?php

/**
 * Class Svea_Checkout_Model_Payment_Api_Init
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Payment_Api_Init
    extends Mage_Core_Model_Abstract
{
    /**
     * Setup communication.
     *
     * @return object Svea_Checkout_Model_Checkout_Api_Configuration
     */
    public function getSveaConfig()
    {
        $sveaConfig = new Svea_Checkout_Model_Checkout_Api_Configuration();

        return $sveaConfig;
    }
}
