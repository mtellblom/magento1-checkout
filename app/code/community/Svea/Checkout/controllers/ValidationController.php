<?php

/**
 * Class Svea_Checkout_PushController
 *
 * @package Webbhuset_SveaCheckout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_ValidationController
    extends
    Mage_Core_Controller_Front_Action
{
    /**
     * Handle push, create or activate order.
     *
     * @return boolean.
     */
    public function indexAction()
    {
        $svea            = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $sveaOrder       = $svea->setupCommunication();
        $request         = $this->getRequest();
        $quoteId         = (int)$request->getParam('quoteId');
        $secret          = $request->getParam('secret');
        $decryptedSecret = (int)Mage::getModel('Core/Encryption')->decrypt($secret);
        $orderQueueItem  = Mage::getModel('sveacheckout/queue')->load($quoteId, 'quote_id');

        if (!$orderQueueItem->getId()) {

            return $this->reportAndReturn(204, "QueueItem {$quoteId} not found in queue.");
        }

        if ($decryptedSecret !== $quoteId) {

            return $this->reportAndReturn(204, "Secret does not match on Queue ID {$quoteId}.");
        }

        try {
            $quote   = $this->_getQuoteById($quoteId);
            $storeId = $quote->getStoreId();
            Mage::app()->setCurrentStore($storeId);
            $svea->setQuote($quote)->setLocales($sveaOrder, $quote);
            $orderId = $quote->getPaymentReference();
        } catch (Exception $ex) {

            return $this->reportAndReturn(207, $quoteId . ' : ' . $ex->getMessage());
        }

        try {
            $response = $sveaOrder->setCheckoutOrderId((int)$orderId)->getOrder();
            $orderQueueItem
                ->setQueueId($orderQueueItem->getId())
                ->setQuoteId($quote->getId())
                ->setPushResponse($response)
                ->setState($orderQueueItem::SVEA_QUEUE_STATE_WAIT)
                ->save();

            $responseObject = new Varien_Object($response);


            if (!($quote->getPayment()->getMethod())) {
                $payment = $quote->getPayment();
                $payment->setMethod('sveacheckout');
            }


            $createdOrder = Mage::getModel('sveacheckout/Payment_CreateOrder')
                ->createOrder($quote, $responseObject, $orderQueueItem);


            if (isset($createdOrder) && true !== $createdOrder) {

                throw new Mage_Core_Exception('Unable to create order. ' . $createdOrder);
            }

            if ($this->getResponse()->getHttpResponseCode() == 200) {
                $successMessage = sprintf(
                    "Order with ID %d from SveaId %d QuoteId %d Created.",
                    $orderQueueItem->getOrderId(),
                    $orderId,
                    $quoteId
                );

                return $this->reportAndReturn(
                    201,
                    $successMessage,
                    $orderQueueItem->getOrderId()
                );
            }

        } catch (Exception $ex) {
            return $this->reportAndReturn(
                206,
                ' error code: ' . $this->getResponse()->getHttpResponseCode()
                . ' error msg: ' . $ex->getMessage());
        }
    }

    /**
     * Set http status code log event and return.
     *
     * @see   https://httpstatuses.com for references
     *
     * @param int    $httpStatus HTTP status code
     * @param string $logMessage
     * @param order  \Mage\Sales\Model\Order (optional)
     *
     * @return bool
     */
    protected function reportAndReturn($httpStatus, $logMessage, $order = false)
    {
        $request    = Mage::app()->getRequest();
        $simulation = $request->getParam('Simulation');
        $this->getResponse()
            ->setHeader('HTTP/1.0', $httpStatus, true)
            ->setHttpResponseCode($httpStatus);

        if ('true' == $simulation) {
            print("http {$httpStatus} {$logMessage}");
        }
        Mage::helper('sveacheckout/Debug')->writeToLog($logMessage);

        if ($httpStatus !== 201) {
            print(
                json_encode(
                    ['Valid' => false]
                )
            );

            return false;
        }

        print(
            json_encode(
                [
                    'Valid'             => true,
                    'ClientOrderNumber' => $this->_makeSveaOrderId($order),
                ]
            )
        );

        return true;
    }

    protected function _makeSveaOrderId($orderId)
    {
        $reference = $orderId;
        $useForReference = Mage::getStoreConfig('payment/sveacheckout_dev_settings/reference');
        if (in_array($useForReference, ['plain-increment-id', 'suffixed-increment-id'])) {
            $reference = Mage::getResourceModel('sales/order')
                ->getIncrementId($orderId);
        }
        if (in_array($useForReference, ['plain-increment-id', 'plain-order-id'])) {

            return $reference;
        }

        //To avoid order already being created, if you for example have
        //stageEnv/devEnv and ProductionEnv with quote id in same range.
        $allowedLength = 32;
        $separator = '_';
        $lengthOfHash  = $allowedLength - (strlen((string)$reference) + strlen($separator));
        $hashedBaseUrl = sha1(Mage::getBaseUrl());
        $clientId      = $reference . $separator . mb_substr($hashedBaseUrl, 0, $lengthOfHash);

        return $clientId;
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
