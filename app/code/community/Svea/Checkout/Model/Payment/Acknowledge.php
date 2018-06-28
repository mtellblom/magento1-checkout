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
            $svea         = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
            $sveaOrder    = $svea->setupCommunication();
            $sveaData     = new Varien_Object( $sveaOrder->setCheckoutOrderId((int)$quote->getPaymentReference())->getOrder() );
            $sveaReference = $sveaData->getData('ClientOrderNumber');

            $useForReference = Mage::getStoreConfig('payment/sveacheckout_dev_settings/reference');
            if (in_array($useForReference , ['suffixed-increment-id','suffixed-order-id'])) {
                $reference = $sveaReference;
                $separator = '_';
                $lastChar = strrpos ($reference , $separator,0);
                $reference = substr($reference,0, $lastChar);
                if ($useForReference == 'suffixed-increment-id') {
                    $magentoOrder = Mage::getModel('sales/order')->load($reference, 'increment_id');
                } else {
                    $magentoOrder = Mage::getModel('sales/order')->load($reference, 'entity_id');
                }
            } elseif ($useForReference == 'plain-increment-id') {
                $magentoOrder = Mage::getModel('sales/order')->load($sveaReference, 'increment_id');
            } else {
                $magentoOrder = Mage::getModel('sales/order')->load($sveaReference, 'entity_id');
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

            if (
                $magentoOrder->getCustomerEmail() == html_entity_decode('&#8203;')
                && !empty($sveaData->getData('EmailAddress'))
            ) {

                $magentoOrder->setCustomerEmail($sveaData->getData('EmailAddress'));
            }

            $statusText   = Mage::getStoreConfig('payment/SveaCheckout/order_status_after_acknowledge');
            $message      = $helper->__("Order was acknowledged by Svea.");

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

            $state = Mage::getModel('sales/order_status')
                ->getCollection()
                ->joinStates()
                ->addFieldToFilter('main_table.status', $statusText)
                ->getFirstItem();

            $magentoOrder
                ->setState($state->getState(), $statusText, $message, false)
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
