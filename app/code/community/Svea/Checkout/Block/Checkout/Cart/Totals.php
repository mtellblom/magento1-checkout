<?php

/**
 * Shopping cart block.
 *
 * Extends to add compatibility with older Magento versions.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Checkout_Cart_Totals
    extends Mage_Checkout_Block_Cart_Totals
{
    /**
     * Check if can apply msrp. to totals.
     *
     * Compatibility override due to function does not exist in older versions
     * of Magento.
     *
     * @return boolean
     */
    public function canApplyMsrp()
    {
        $rc = new ReflectionClass(new Mage_Checkout_Block_Cart_Totals);

        if ($rc->hasMethod('canApplyMsrp')) {
            return parent::canApplyMsrp();
        }

        return false;
    }
}