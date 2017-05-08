<?php

/**
 * Svea one page checkout block.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Onepage extends Mage_Checkout_Block_Onepage
{
    /**
     * Svea_Checkout_Block_Onepage constructor.
     * Override OPC.
     */
    public function __construct()
    {
        if (!Mage::getStoreConfig('payment/SveaCheckout/active')) {

            return false;
        }

        /**
         * When we encounter an error, we redirect to checkout.
         */
        if (Mage::getSingleton('checkout/session')->getSveaEncounteredError() != true) {

            $redirectUrl = Mage::getUrl('sveacheckout/index');
            $this->getRequest()->setDispatched(true);

            Mage::app()->getResponse()
                ->setRedirect($redirectUrl)
                ->sendResponse();

            return;
        } else {

            Mage::getSingleton('checkout/session')->setSveaEncounteredError(false);
        }
    }
}
