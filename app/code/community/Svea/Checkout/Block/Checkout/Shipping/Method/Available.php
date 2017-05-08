<?php

/**
 * Block for supplying all available shipping method.
 * Extends the core class to add some more functions.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Checkout_Shipping_Method_Available
    extends Mage_Checkout_Block_Onepage_Shipping_Method_Available
{
    /**
     * Returns a boolean response for if the specified shipping rate is
     * currently selected.
     *
     * Fallback on the selected default shipping method from system configuration.
     *
     * @param  string $code
     *
     * @return boolean
     */
    public function isSelected($code)
    {
        $selected = Mage::helper('sveacheckout')
            ->getSelectedMethod($this->getQuote());

        return $code === $selected;
    }

    /**
     * Returns a boolean response for if there is any shipping rates.
     *
     * @return boolean
     */
    public function hasShippingRates()
    {
        return (bool)$this->countShippingRates();
    }

    /**
     * Returns the number of available shipping rates.
     *
     * @return integer
     */
    public function countShippingRates()
    {
        return count($this->getShippingRates());
    }
}
