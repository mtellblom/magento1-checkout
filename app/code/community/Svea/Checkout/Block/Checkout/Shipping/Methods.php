<?php

/**
 * Shipping method block.
 * Extends the core class to add some more functions.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Checkout_Shipping_Methods
    extends Mage_Checkout_Block_Onepage_Shipping_Method
{
    /**
     * Returns the default shipping method from system configuration.
     *
     * @return string
     */
    public function getDefaultShippingMethod()
    {
        return Mage::helper('sveacheckout')
            ->getConfigData('sveacheckout_layout/shipping_method_default');
    }

    /**
     * Returns a boolean response for if the shipping method block should be
     * hidden.
     *
     * @return boolean
     */
    public function isShippingMethodsHidden()
    {
        return Mage::helper('sveacheckout')
            ->getConfigData('sveacheckout_layout/shipping_method_hide');
    }
}
