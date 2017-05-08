<?php

/**
 * Helper class for checkout payment information.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Helper_Payment_Quote_Information
    extends Svea_Checkout_Helper_Payment_Information_Abstract
{
    /**
     * Holds the quote to process.
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;
    /**
     * Holds the payment model.
     *
     * @var Mage_Sales_Model_Quote_Payment
     */
    protected $_payment;

    /**
     * Validates the general data.
     *
     * @throws Mage_Core_Exception
     */
    protected function _preValidate()
    {
        if (!$this->_getQuote() || !$this->_getQuote()->getId()) {
            Mage::throwException('Unable to set quote information on a non existing quote');
        }
        if (!$this->_getPayment() || !$this->_getPayment()->getId()) {
            Mage::throwException('Unable to set quote information without a set payment');
        }
    }

    /**
     * Retrieves current quote.
     * If no quote is already set the session quote will be used.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = Mage::getSingleton('sales/quote');
        }

        return $this->_quote;
    }

    /**
     * Sets the quote to process.
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return Svea_Checkout_Helper_Payment_Quote_Information
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;

        return $this;
    }

    /**
     * Retrieves current quote or order payment.
     *
     * @return Mage_Sales_Model_Quote_Payment
     */
    protected function _getPayment()
    {
        if (!$this->_payment) {
            $this->_payment = $this->_getQuote()->getPayment();
        }

        return $this->_payment;
    }

    /**
     * Sets the quote to process.
     *
     * @param Mage_Sales_Model_Quote_Payment $payment
     *
     * @return Svea_Checkout_Helper_Payment_Quote_Information
     */
    public function setPayment(Mage_Sales_Model_Quote_Payment $payment)
    {
        $this->_payment = $payment;

        return $this;
    }
}
