<?php

/**
 * Helper class for checkout payment information.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Helper_Payment_Order_Information
    extends Svea_Checkout_Helper_Payment_Information_Abstract
{
    /**
     * Holds the quote to process.
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * Holds the payment model.
     *
     * @var Mage_Sales_Model_Order_Payment
     */
    protected $_payment;

    /**
     * Retrieves current quote or order payment.
     *
     * @return Mage_Sales_Model_Order_Payment
     */
    protected function _getPayment()
    {
        if (!$this->_payment) {
            $this->_payment = $this->_getOrder()->getPayment();
        }

        return $this->_payment;
    }

    /**
     * Sets the payment.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return Svea_Checkout_Helper_Payment_Order_Information
     */
    public function setPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $this->_payment = $payment;

        return $this;
    }

    /**
     * Retrieves current order.
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrder()
    {
        return $this->_order;
    }

    /**
     * Sets the order to process.
     *
     * @param Mage_Sales_Model_Quote $order
     *
     * @return Svea_Checkout_Helper_Payment_Order_Information
     */
    public function setOrder(Mage_Sales_Model_Quote $order)
    {
        $this->_order = $order;

        return $this;
    }

    /**
     * Overrides validator.
     *
     */
    protected function _preValidate()
    {
        return;
    }
}
