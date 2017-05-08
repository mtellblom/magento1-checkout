<?php

/**
 * General helper class for providing some common functions.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Helper_Transaction
    extends Mage_Core_Helper_Abstract
{
    /**
     * Creates Transaction.
     *
     * @see Mage_Sales_Model_Order_Payment_Transaction for constant reference
     *
     * @param Mage_Sales_Model_Order  $order
     * @param \Svea\WebPay\BuildOrder $sveaOrder Svea Order Object
     * @param string                  $type Constant transaction type
     * @param int                     $id   Transaction ID
     *
     */
    public function createTransaction($order, $sveaOrder, $type, $id)
    {
        $transaction = Mage::getModel('sales/order_payment_transaction');

        $flatData = $this->_flattenDataArray($sveaOrder->getData());
        $payment = $order->getPayment();
        $transaction
            ->setTxnType($type)
            ->setTxnId($id)
            ->setMergeWithPaymentInformationData(true)
            ->setOrderPaymentObject($payment)
            ->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                $flatData
            );

        $parentTransId = $payment->getLastTransId();
        $payment->setLastTransId($id);
        $payment->setCreatedTransaction($transaction);
        $order->addRelatedObject($transaction);

        if ($parentTransId) {
            $transaction->setParentTxnId($parentTransId);
            $parentTransaction = $payment->getTransaction($parentTransId);
            if ($parentTransaction) {
                $order->addRelatedObject($parentTransaction);
            }
        }

        $transaction->save();
        $payment->save();
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
                    $index = sprintf("%s-%s", $prefix, $key);
                } else {
                    $index = $key;
                }
                $result += $this->_flattenDataArray($value, $index);
            } else {
                if (!empty($prefix)) {
                    $index = sprintf("%s-%s", $prefix, $key);
                } else {
                    $index = $key;
                }
                $result[$index] = $value;
            }
        }

        return $result;
    }
}
