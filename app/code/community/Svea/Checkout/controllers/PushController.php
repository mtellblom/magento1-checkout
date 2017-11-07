<?php

/**
 * Class Svea_Checkout_PushController
 *
 * @package Webbhuset_SveaCheckout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_PushController
    extends Mage_Core_Controller_Front_Action
{
    /**
     * Handle push, create or activate order.
     *
     * @return boolean.
     */
    public function indexAction()
    {
        $svea           = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $sveaOrder      = $svea->setupCommunication();
        $request        = $this->getRequest();
        $quoteId        = (int) $request->getParam('quoteId');
        $orderQueueItem = Mage::getModel('sveacheckout/queue')->load($quoteId, 'quote_id');

        if (!$orderQueueItem->getId()) {

            return $this->reportAndReturn(404, "QueueItem {$quoteId} not found in queue.");
        }
        try {
            $quote = $this->_getQuoteById($quoteId);
            $storeId = $quote->getStoreId();
            Mage::app()->setCurrentStore($storeId);
            $svea->setQuote($quote)->setLocales($sveaOrder);
            $orderId = $quote->getPaymentReference();
        } catch (Exception $ex) {

            return $this->reportAndReturn(424, $quoteId . ' : ' . $ex->getMessage());
        }

        try {
            $response       = $sveaOrder->setCheckoutOrderId((int)$orderId)->getOrder();
            $orderQueueItem
                ->setQueueId($orderQueueItem->getId())
                ->setQuoteId($quote->getId())
                ->setPushResponse($response)
                ->setState($orderQueueItem::SVEA_QUEUE_STATE_WAIT)
                ->save();

            $responseObject = new Varien_Object($response);
            switch (strtolower($responseObject->getData('Status'))) {
                case 'created':
                    return $this->reportAndReturn(402, $quoteId . ' : is only in created state.');
                case 'cancelled':
                    if ($orderQueueItem->getOrderId()) {
                        $order = Mage::getModel('sales/order')->load($orderQueueItem->getOrderId());

                        if ($order->canCancel()) {
                            $order->cancel()
                                ->save();

                            $orderQueueItem->delete()
                                ->save();
                        }
                    }
                    return $this->reportAndReturn(410, $quoteId . ' : is cancelled in Svea:s end.');
            }

            if ($orderQueueItem->getState() == $orderQueueItem::SVEA_QUEUE_STATE_OK) {

                return $this->reportAndReturn(208, "QueueItem {$quoteId} already handled.");
            }


            if ($svea->sveaOrderHasErrors($sveaOrder, $quote, $response)) {

                Mage::throwException("Quote " . intval($quoteId) . " is not valid");
            }

            if (
                !$orderQueueItem->getData('order_id')
                && $orderQueueItem->getData('state') != $orderQueueItem::SVEA_QUEUE_STATE_NEW
                && $orderQueueItem->getData('state') != $orderQueueItem::SVEA_QUEUE_STATE_OK
            ) {
                $createdOrder = Mage::getModel('sveacheckout/Payment_CreateOrder')
                    ->createOrder($quote, $responseObject, $orderQueueItem);
            }

            if (isset($createdOrder) && true != $createdOrder) {

                throw new Mage_Core_Exception('Unable to create order. '. $createdOrder);
            }

            if ('final' == strtolower($responseObject->getData('Status'))) {
                Mage::getModel('sveacheckout/payment_acknowledge')
                    ->acknowledge($quote, $request->getParam('mode'), $request->getParam('sveaId'));
            }

            if ($this->getResponse()->getHttpResponseCode() == 200) {
                return $this->reportAndReturn(
                    201,
                    "Order from SveaId {$orderId} QuoteId {$quoteId} successfully Created."
                );
            }
        } catch (Exception $ex) {
            return $this->reportAndReturn(
                444,
                ' httpStatus: ' . $this->getResponse()->getHttpResponseCode()
                . ' ErrMsg: ' . $ex->getMessage());
        }
    }

    /**
     * Set http status code log event and return.
     *
     * @see https://httpstatuses.com for references
     * @param int    $httpStatus HTTP status code
     * @param string $logMessage
     *
     * @return bool
     */
    protected function reportAndReturn($httpStatus, $logMessage)
    {
        $request = Mage::app()->getRequest();
        $simulation = $request->getParam('Simulation');

        $this->getResponse()
            ->setHeader('HTTP/1.0', $httpStatus, true)
            ->setHttpResponseCode($httpStatus);

        if ('true' == $simulation) {
            print("http {$httpStatus} {$logMessage}");
        }

        Mage::helper('sveacheckout/Debug')->writeToLog($logMessage);

        if ($httpStatus > 399) {

            return false;
        }

        return true;
    }

    /**
     * Retrieves quote from ID.
     *
     * @param  int $quoteId
     *
     * @return Mage_Sales_Model_Quote|bool=false
     * @throws Mage_Core_Exception
     */
    protected function _getQuoteById($quoteId)
    {
        $quote = Mage::getModel('sales/quote')
            ->loadByIdWithoutStore($quoteId);
        if (!$quote->getId()) {

            Mage::throwException('no valid quote');
        }

        return $quote;
    }
}
