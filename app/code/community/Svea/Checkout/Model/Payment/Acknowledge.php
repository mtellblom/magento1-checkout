<?php

/**
 * Svea response handler.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Payment_Acknowledge
    extends Mage_Core_Model_Abstract
{
    /**
     * Acknowledge order (in response to a push).
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string                 $mode
     * @param integer                $sveaId
     *
     * @return void
     * @throws Exception
     */
    public function acknowledge($quote, $mode, $sveaId)
    {
        $logger           = Mage::helper('sveacheckout/debug');
        $helper           = Mage::helper('sveacheckout');
        $wasSuccess       = false;

        $adapter          = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();

        try {
            $magentoOrders = Mage::getModel('sales/order')
                ->getCollection()
                ->addAttributeToFilter('quote_id', $quote->getId())
                ->addAttributeToSelect('payment_reference');

            $indicationOfError = false;
            if ($magentoOrders->count() >= 2) {
                $indicationOfError = true;
            }

            $orderThatMatch = [];
            foreach ($magentoOrders as $order) {
                if ($order->getPaymentReference() == $sveaId) {
                    $orderThatMatch[] = $order;
                }
            }

            if (sizeof($orderThatMatch) >= 2) {

                throw new Mage_Core_Exception("There were multiple orders that matched SveaOrder with ID {$sveaId}.");
            } elseif($indicationOfError && sizeof($orderThatMatch)) {
                $magentoOrder = $orderThatMatch[0]->load();
            }

            if(!isset($magentoOrder)) {
                $magentoOrder = Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
            }

            if(!isset($magentoOrder)) {

                throw new Mage_Core_Exception("Found no orders that matches SveaOrder with ID {$sveaId}.");
            }

            $storeId      = $magentoOrder->getStoreId();

            $appEmulation           = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            $svea         = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
            $sveaOrder    = $svea->setupCommunication();
            $sveaData     = new Varien_Object(
                $sveaOrder->setCheckoutOrderId((int)$quote->getPaymentReference())->getOrder()
            );

            if (empty(trim($magentoOrder->getCustomerEmail())) && !empty($sveaData->getData('EmailAddress'))) {
                $magentoOrder->setCustomerEmail($sveaData->getData('EmailAddress'));
            }

            $status       = Mage::getStoreConfig('payment/SveaCheckout/order_status_after_acknowledge');
            $message      = $helper->__("Order was acknowledged by Svea.");

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

            $magentoOrder
                ->setState(Mage_Sales_Model_Order::STATE_NEW, $status, $message, false)
                ->save();
            $magentoOrder->getSendConfirmation(null);

            $magentoOrder->sendNewOrderEmail();

            $orderQueueItem = Mage::getModel('sveacheckout/queue')->load($quote->getId(), 'quote_id');

            $orderQueueItem
                ->setQueueId($orderQueueItem->getId())
                ->setQuoteId($quote->getId())
                ->setOrderId($magentoOrder->getId())
                ->setPushResponse($sveaData->getData())
                ->setState($orderQueueItem::SVEA_QUEUE_STATE_OK)
                ->save();

            $id   = $sveaData->getData('OrderId');
            $type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;

            Mage::helper('sveacheckout/transaction')->createTransaction(
                $magentoOrder,
                $sveaData,
                $type,
                "{$id}-{$type}"
            );

            $payment = $magentoOrder->getPayment();
            $info = [
                'reservation'  => $sveaData->getData(),
                'sveacheckout' => [
                    'mode'      => $mode,
                    'order_id'  => $orderQueueItem->getId(),
                ]
            ];

            $payment->setAdditionalInformation($info)->save();
            $payment->setTransactionId("{$id}-{$type}");
            $magentoOrder->save();

            $adapter->commit();
            $logger->writeToLog('Payment transaction was created for order ' . $magentoOrder->getId());
            $wasSuccess = true;
        } catch (Exception $e) {
            $adapter->rollback();
            Mage::logException($e);
            $message = 'Failed to create paymentTransaction, error:' . $e->__toString();
            $logger->writeToLog($message);

            throw $e;
        }

        if ($wasSuccess) {
            $magentoOrder->afterCommitCallback();
            $payment->afterCommitCallback();
        }
    }
}
