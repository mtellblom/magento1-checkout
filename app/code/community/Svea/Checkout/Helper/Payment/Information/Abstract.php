<?php

/**
 * Abstract helper method for payment information handling.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
abstract class Svea_Checkout_Helper_Payment_Information_Abstract
    extends Mage_Core_Helper_Abstract
{
    /**
     * Holds the array key handle for Svea Checkout information.
     *
     * @var string
     */
    const SVEA_CHECKOUT_HANDLE         = 'sveacheckout';

    /**
     * Retrieves the set Svea Order ID form payment information.
     *
     * @return string
     */
    public function getSveaOrderId()
    {
        return $this->_getSveaInfo('order_id');
    }

    /**
     * Retrieves handle for Svea Checkout information.
     *
     * @return string
     */
    protected function _getHandle()
    {
        return self::SVEA_CHECKOUT_HANDLE;
    }

    /**
     * Retrieves current Svea Checkout information from current payment.
     *
     * @param string      $key
     * @param string|bool $getCurrent specify current handle
     *
     * @return string
     */
    protected function _getSveaInfo($key)
    {
        $handle = $this->_getHandle();
        $info = $this->_getPayment()->getAdditionalInformation();

        if (isset($info[$handle]) && isset($info[$handle][$key])) {
            return $info[$handle][$key];
        } else {
            return "";
        }
    }

    /**
     * Sets Svea Checkout information to the set payment.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  boolean $useCurrent
     *
     * @return Svea_Checkout_Helper_Payment_Information_Abstract
     */
    protected function _setSveaInfo($key, $value, $useCurrent = false)
    {
        $this->_preValidate();
        $payment = $this->_getPayment();
        $info = $payment->getAdditionalInformation();
        $handle = $this->_getHandle();

        if (isset($info[$handle])) {
            $data = (array)$info[$handle];
        } else {
            $data = [];
        }

        $payment->setAdditionalInformation(
            array_merge(
                $payment->getAdditionalInformation(),
                [$handle => $data,]
            )
        );

        $payment->save();

        return $this;
    }
}
