<?php

/**
 * Svea checkout block info.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Info_SveaCheckout
    extends Mage_Payment_Block_Info
{
    /**
     * @var string $_payableTo
     */
    protected $_payableTo;

    /**
     * @var string $_mailingAddress
     */
    protected $_mailingAddress;

    /**
     * Get variable _paymentTo.
     *
     * @return string
     */
    public function getPayableTo()
    {
        if (is_null($this->_payableTo)) {
            $this->_convertAdditionalData();
        }

        return $this->_payableTo;
    }

    /**
     * Add data to info block.
     *
     * @return Svea_Checkout_Block_Info_SveaCheckout
     */
    protected function _convertAdditionalData()
    {
        $details = @unserialize($this->getInfo()->getAdditionalData());
        if (is_array($details)) {
            $this->_payableTo      = isset($details['payable_to']) ? (string)$details['payable_to'] : '';
            $this->_mailingAddress = isset($details['mailing_address']) ? (string)$details['mailing_address'] : '';
        } else {
            $this->_payableTo      = '';
            $this->_mailingAddress = '';
        }

        return $this;
    }

    /**
     * Get mailing Address.
     *
     * @return string
     */
    public function getMailingAddress()
    {
        if (is_null($this->_mailingAddress)) {
            $this->_convertAdditionalData();
        }

        return $this->_mailingAddress;
    }
}
