<?php

/**
 * Order payment transaction object creation helper.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Helper_Payment_Order_Transaction
    extends Mage_Core_Helper_Abstract
{
    /**
     * Order to process.
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * Order payment object.
     *
     * @var Mage_Sales_Model_Order_Payment
     */
    protected $_payment;

    /**
     * Type of transaction.
     *
     * @var string
     */
    protected $_type;

    /**
     * Current transaction ID.
     *
     * @var string
     */
    protected $_id;

    /**
     * Current parent transaction ID.
     *
     * @var string
     */
    protected $_parentId;

    /**
     * Data array that will be populated within transaction.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Transaction is set as closed.
     *
     * @var boolean
     */
    protected $_isClosed;

    /**
     * Data should be merged with payment additional information.
     *
     * @var boolean
     */
    protected $_mergeWithPaymentInformationData = false;

    /**
     * Creates transaction for the set payment object with the set data.
     *
     * @throws Mage_Core_Exception
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    public function createTransaction()
    {
        try {
            $transaction = $this->_getTransaction()
                ->setOrderPaymentObject($this->_getPayment())
                ->setTxnType($this->_getType())
                ->setTxnId($this->_getId())
                ->setIsClosed(false);

            if ($this->_getData()) {
                $transaction->setAdditionalInformation(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                        $this->_getData()
                );
            }

            /**
             * Needs to be stored at this time due to later it will be the same
             * as the transaction that is currently created.
             */
            $parentTransId = $this->_getParentId();

            /**
             * Links transaction to the payment object.
             */
            $this->_linkTransaction();
            $this->_linkParentTransaction($parentTransId);

            /**
             * Needed for transaction to be saved and the last trans id to be set
             * on payment object.
             */
            $transaction->save();
            $this->_getPayment()->save();
        } catch (Exception $e) {
            Mage::throwException($e);
        }

        return $transaction;
    }

    /**
     * Retrieves transaction model.
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _getTransaction()
    {
        return Mage::getModel('sales/order_payment_transaction');
    }

    /**
     * Retrieves current payment.
     *
     * @return Mage_Sales_Model_Order_Payment
     */
    protected function _getPayment()
    {
        return $this->_getOrder()->getPayment();
    }

    /**
     * Sets the quote to process.
     *
     * @param  Mage_Sales_Model_Order_Payment $payment
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
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
     * @param Mage_Sales_Model_Order $order
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;

        return $this;
    }

    /**
     * Get current transaction type.
     *
     * @return string
     */
    protected function _getType()
    {
        return $this->_type;
    }

    /**
     * Set transaction type.
     *
     * @param  string $type
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
     */
    public function setType($type)
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * Retrieves current transaction id.
     * Fallbacks on the id set in payments addition information.
     *
     * @return string
     */
    protected function _getId()
    {
        if (!$this->_id) {
            $this->_id = Mage::helper('sveacheckout/payment_order_information')
                ->setPayment($this->_getPayment())
                ->getSveaOrderId();
        }

        return implode(
            '-',
            [
                $this->_id,
                ($this->_getTransaction()->getCollection()->getSize() + 1),
                $this->_getType()
            ]
        );
    }

    /**
     * Set current transaction id.
     *
     * @param string $id
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
     */
    public function setId($id)
    {
        $this->_id = $id;

        return $this;
    }

    /**
     * Retrieves current data information array.
     * Adds data from payment additional information if merged is set to true.
     *
     * @return array
     */
    protected function _getData()
    {
        if ($this->_getMergeWithPaymentInformationData()) {
            $information = Mage::helper('sveacheckout/payment_order_information')
                ->setPayment($this->_getPayment())
                ->getCurrentInformationArray();

            $handle = Svea_Checkout_Helper_Payment_Information_Abstract::SVEA_CHECKOUT_HANDLE;

            $this->_data = array_merge(
                (array)$this->_data,
                [$handle => $information]
            );
        }

        $this->_data = $this->_flattenDataArray($this->_data);

        return $this->_data;
    }

    /**
     * Sets current data information array.
     *
     * @param  array $data
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
     */
    public function setData($data)
    {
        $this->_data = $data;

        return $this;
    }

    /**
     * Retrieves boolean for if data should be merged with payment additional
     * information.
     *
     * @return boolean
     */
    protected function _getMergeWithPaymentInformationData()
    {
        return $this->_mergeWithPaymentInformationData;
    }

    /**
     * Sets boolean for if data information array should be merged with
     * payment additional information.
     *
     * @param boolean $value
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
     */
    public function setMergeWithPaymentInformationData($value)
    {
        if (is_bool($value)) {
            $this->_mergeWithPaymentInformationData = $value;
        }

        return $this;
    }

    /**
     * Turns a multi dimensional array into a single dimension.
     *
     * @param  array  $array
     * @param  string $prefix
     *
     * @return array  $result
     */
    protected function _flattenDataArray($array, $prefix = '')
    {
        $result = [];

        foreach ((array)$array as $key => $value) {
            if (is_array($value)) {
                if (!empty($prefix)) {
                    $index = sprintf(
                        "%s-%s",
                        $prefix,
                        $key
                    );
                } else {
                    $index = $key;
                }

                $result += $this->_flattenDataArray($value, $index);
            } else {
                if (!empty($prefix)) {
                    $index = sprintf(
                        "%s-%s",
                        $prefix,
                        $key
                    );
                } else {
                    $index = $key;
                }

                $result[$index] = $value;
            }
        }

        return $result;
    }

    /**
     * Retrieves current parent transaction id.
     * Fallbacks on the last trans id set on payments object.
     *
     * @return string
     */
    protected function _getParentId()
    {
        return $this->_getPayment()->getLastTransId();
    }

    /**
     * Sets the current transactions parent id.
     *
     * @param string $parentId
     *
     * @return Svea_Checkout_Helper_Payment_Order_Transaction
     */
    public function setParentId($parentId)
    {
        $this->_parentId = $parentId;

        return $this;
    }

    /**
     * Links transaction to the payment and order object.
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _linkTransaction()
    {
        $this->_getPayment()->setLastTransId($this->_getId());
        $this->_getPayment()->setCreatedTransaction($this->_getTransaction());
        $this->_getPayment()->getOrder()->addRelatedObject($this->_getTransaction());

        return $this;
    }

    /**
     * Links transaction to a parent transaction.
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _linkParentTransaction($parentTransId)
    {
        if ($parentTransId && $this->_getParentId() != $this->_getId()) {
            $this->_getTransaction()->setParentTxnId($parentTransId);
            $parentTransaction = $this->_getPayment()->getTransaction($parentTransId);

            if ($parentTransaction) {
                $this->_getPayment()->getOrder()->addRelatedObject($parentTransaction);
            }
        }

        return $this;
    }

    /**
     * Retrieves all possible transaction types.
     *
     * @return array
     */
    protected function _getTransactionTypes()
    {
        return Mage::getModel('sales/order_payment_transaction')->getTransactionTypes();
    }
}
