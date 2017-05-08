<?php

/**
 * Shopping cart block.
 * Extends to change some fixed values.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Checkout_Cart
    extends Mage_Checkout_Block_Cart
{
    /**
     * Get URL to checkout.
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        return Mage::helper('sveacheckout')->getCheckoutUrl();
    }

    /**
     * Get form post URL.
     *
     * @return string
     */
    public function getPostUrl()
    {
        return $this->getUrl('checkout/cart/updatePost');
    }
}
