<?php

/**
 * Agreements block.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Checkout_Agreements
    extends Mage_Checkout_Block_Agreements
{
    /**
     * Retrieves terms and agreements for the current store.
     * Override to remove active flag in checkout settings.
     *
     * @return string
     */
    public function getAgreements()
    {
        if (!$this->hasAgreements()) {
            $agreements = Mage::getModel('checkout/agreement')->getCollection()
                ->addStoreFilter(Mage::app()->getStore()->getId())
                ->addFieldToFilter('is_active', 1);

            $this->setAgreements($agreements);
        }

        return $this->getData('agreements');
    }
}
