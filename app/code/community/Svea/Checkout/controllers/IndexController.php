<?php
require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'OnepageController.php';

/**
 * Svea Checkout IndexController.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_IndexController
    extends Mage_Checkout_OnepageController
{
    const ERROR_MESSAGE_SUFFIX = ', please note the error-code and contact store owner.';

    /**
     * Inactivate Svea Checkout.
     *
     * @param $action
     *
     * @return bool
     */
    public function hasAction($action)
    {
        if (!Mage::getStoreConfig('payment/SveaCheckout/active')) {

            return false;
        }

        return method_exists($this, $this->getActionMethodName($action));
    }

    protected function _getPaymentSettings() {
        return [
            'view_options_on_invoice' => Mage::getStoreConfig('payment/SveaCheckout/view_options_on_invoice')
        ];
    }

    /**
     * Handles return to payment window with failed payment.
     *
     * @param $id
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _restoreQuote($id, $cancelOrderReference = null)
    {
        $svea        = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $session     = Mage::getSingleton('checkout/session');
        $oldQuote    = Mage::getModel('sales/quote')->load($id);
        $sveaOrderId = $oldQuote->getPaymentReference();
        $hasOrder    = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSelect('id')
            ->addAttributeToFilter('quote_id', $id)
            ->getSize() > 0;
            if (
               !$hasOrder &&
               $oldQuote->getIsActive() &&
               $oldQuote->hasItems() &&
               !$oldQuote->getHasError()
        ) {

            return $oldQuote;
        } elseif($hasOrder && $cancelOrderReference) {
            $order = Mage::getModel('sales/order')->load($cancelOrderReference, 'increment_id');
            if (
                'new' == $order->getState()
                && $order->canCancel()
                && ($order->getPayment() && !sizeof($order->getPayment()->getAdditionalInformation()))
            ) {
                $order->cancel()
                    ->save();
                Mage::helper('sveacheckout/Debug')->writeToLog(
                    'cancelled duplicate order id'. $order->getId()
                );
            }
        }

        $quote = Mage::getModel('sales/quote')->setStore(Mage::app()->getStore());

        $quote->merge($oldQuote)
            ->setIsActive(1)
            ->setReservedOrderId(null)
            ->setPaymentReference($sveaOrderId)
            ->collectTotals()
            ->unsLastRealOrderId()
            ->save();

        $sveaOrder = $svea->createSveaOrderFromQuote($quote);

        //Create a new order in Sveas end, to reset URIs.
        if (!$sveaOrderId) {
            $response = $sveaOrder->createOrder();
            $sveaOrderId = $response['OrderId'];
            $quote->setPaymentReference($sveaOrderId)->save();
        }

        //Fetch Order
        $response = $sveaOrder->setCheckoutOrderId((int)$sveaOrderId)->getOrder();

        //Run update.
        $hasErrors = $svea->sveaOrderHasErrors($sveaOrder, $quote, $response);
        if(!$hasErrors) {

            return $quote;
        } else {
            $session->addError(
                sprintf(
                    $this->__("Error code 421-q%d - Could not restore quote and load Svea Ekonomi checkout%s"),
                    $quote->getId(),
                    $this::ERROR_MESSAGE_SUFFIX
                )
            );
            $this->_redirect('sveacheckout/index');

            return;
        }
    }

    public function getLastErrorAction()
    {
        $session         = Mage::getSingleton('checkout/session');
        $quoteId         = $session->getQuote()->getId();
        $orderQueueItem  = Mage::getModel('sveacheckout/queue')->load($quoteId, 'quote_id');
        $response        = json_decode($orderQueueItem->getPushResponse());

        if (isset($response->lastErrorMessage)) {

            print json_encode($response->lastErrorMessage);
        }
    }

    /**
     * Index endpoint, collect data, and go through steps to validate and create an order.
     *
     */
    public function indexAction()
    {
        $requestParams = $this->getRequest()->getParams();
        $session       = Mage::getSingleton('checkout/session');

        if (isset($requestParams['reactivate']) && isset($requestParams['quoteId'])) {
            $quoteId =(int)$requestParams['quoteId'];
            $cancelOrderId = null;
            $quote   = Mage::getModel('sales/quote')->load($quoteId);

            $reservedOrderId = $quote->getReservedOrderId();
            if ($reservedOrderId && $reservedOrderId !== $quoteId) {
                $orderExists = Mage::getModel('sales/order')->getCollection()
                    ->addAttributeToFilter('increment_id', $reservedOrderId)->getSize();
                $cancelOrderReference = $orderExists ? $reservedOrderId : null;
            }

            $quote = $this->_restoreQuote($quoteId, $cancelOrderReference);


            return $this->_redirect('sveacheckout/index');
        } else {
            $quote = $session->getQuote();
            $svea  = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
            if ($quote->getPaymentReference() > 0) {
                $sveaOrderId  = $quote->getData('payment_reference');
                $sveaOrder    = $svea->createSveaOrderFromQuote($quote);
                $sveaOrder    = $sveaOrder->setCheckoutOrderId((int)$sveaOrderId)->getOrder();
                $quote->setReservedOrderId(null)->save();
                $session->unsLastRealOrderId();
            }
        }

        if (!$quote->hasItems() || $quote->getHasError()) {

            return $this->_redirect('checkout/cart');
        }

        if (!$quote->validateMinimumAmount()) {
            $error = Mage::getStoreConfig('sales/minimum_order/error_message')
                   ? Mage::getStoreConfig('sales/minimum_order/error_message')
                   : Mage::helper('checkout')->__('Subtotal must exceed minimum order amount');

            Mage::getSingleton('checkout/session')->addError($error);

            return $this->_redirect('checkout/cart');
        }

        if (!$quote->getBillingAddress()->getCountryId()) {
            $this->_addBasicAddressToQuote($quote);
            $quote->collectTotals()->save();
        }

        try {
            $checkout = Mage::getSingleton('checkout/type_onepage');
            $checkout->initCheckout()->savePayment(
                [
                    'method' => 'sveacheckout',
                    'checks' => Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                              | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                              | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                              | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                              | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL
                ]
            );
        } catch (Exception $ex) {
            Mage::helper('sveacheckout/Debug')->writeToLog($ex->getMessage());
            Mage::logException($ex);
            Mage::getSingleton('checkout/session')->setSveaEncounteredError(true);
            Mage::getSingleton('checkout/session')->addError($ex->getMessage());

            return $this->_redirect('checkout/onepage');
        }

        $svea      = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $sveaOrder = $svea->createSveaOrderFromQuote($quote);

        try {
            $response = $this->_getSveaResponse($sveaOrder);

            $sveaOrderId = $response['OrderId'];
            $snippet     = $response['Gui']['Snippet'];

            $this->_renderSnippet($snippet);
            $settings = serialize($this->_getPaymentSettings());
            $quote->setPaymentReference($sveaOrderId)->setPaymentInformation($settings)->save();

            //Save Potential order in queue.
            $this->_insertIntoQueue($quote->getId());
        } catch (Exception $ex) {
            Mage::helper('sveacheckout/Debug')->writeToLog($ex->getMessage());
            Mage::logException($ex);
            Mage::getSingleton('checkout/session')->setSveaEncounteredError(true);
            Mage::getSingleton('checkout/session')->addError($ex->getMessage());

            return $this->_redirect('checkout/onepage');
        }
    }

    /**
     * Add a basic address to quote if missing.
     *
     * @param  Mage_Sales_Model_Quote $quote
     *
     * @return Svea_Checkout_IndexController
     */
    protected function _addBasicAddressToQuote($quote)
    {
        $defaultCountry = Mage::helper('core')->getDefaultCountry();

        $settings = serialize($this->_getPaymentSettings());
        $quote->setPaymentInformation($settings);

        if (!$quote->getBillingAddress()->getCountryId()) {
            $quote->getBillingAddress()
                ->setCountryId($defaultCountry)
                ->setCollectShippingRates(true);
        }
        if (!$quote->getShippingAddress()->getCountryId()) {
            $quote->getShippingAddress()
                ->setCountryId($defaultCountry)
                ->setCollectShippingRates(true);
        }

        return $this;
    }

    /**
     * Perform request, validation, create the order, return response.
     *
     * @throws Mage_Core_Exception
     *
     * @return string
     */
    protected function _getSveaResponse($sveaOrder)
    {
        $svea  = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $quote = Mage::getSingleton('checkout/Session')->getQuote();

        /**
         * Fetch sveaOrder from sveaId.
         */
        if ($quote->getPaymentReference()) {
            $sveaId   = (int)$quote->getPaymentReference();
            $response = $sveaOrder->setCheckoutOrderId((int)$sveaId)->getOrder();
        }

        /**
         * No order was found, create one.
         */
        if (!isset($response) || !is_array($response)) {
            try {
                $response = $sveaOrder->createOrder();
            } catch (Exception $e) {
                Mage::logException($e);
                $response = $this->_invalidateQuote($sveaOrder, $quote);
            }
        } else if (
            $response['Status'] !== 'Created'
            && $response['Status'] !== 'Confirmed'
        ) {

            $response = $this->_invalidateQuote($sveaOrder, $quote);
        }

        if ($response['Status'] == 'Cancelled') {

            $response = $this->_invalidateQuote($sveaOrder, $quote);
        }

        if ($svea->sveaOrderHasErrors($sveaOrder, $quote, $response)) {
            $message = sprintf(
                $this->__('Error code 205-q%d - Could not load Svea Ekonomi checkout%s'),
                $quote->getId(),
                $this::ERROR_MESSAGE_SUFFIX
            );

            Mage::throwException($message);
        }

        if (!isset($response) || !is_array($response)) {
            $message = sprintf(
                $this->__('Error code 204-q%d - Could not load Svea Ekonomi checkout%s'),
                $quote->getId(),
                $this::ERROR_MESSAGE_SUFFIX

            );
            Mage::throwException($message);
        }

        return $response;
    }

    /**
     * Throws the quote out the window and replaces it.
     *
     * @param Svea\WebPay\BuildOrder  $sveaOrder SveaOrder Object.
     * @param  Mage_Sales_Model_Quote $quote
     *
     * @return array                  Svea order.
     */
    protected function _invalidateQuote($sveaOrder, $quote)
    {
        if ($quote->getId()) {
            $quote = Mage::helper('sveacheckout')->replaceQuote();

            $buildOrderModel = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
            $sveaOrder = $buildOrderModel
                ->createSveaOrderFromQuote($quote)
                ->setCheckoutOrderId(intval($quote->getId()));

            return $sveaOrder->createOrder();
        }

        return $sveaOrder->getOrder();
    }

    /**
     * Wrapps magento layout and renders snippet.
     *
     * @param $snippet
     */
    protected function _renderSnippet($snippet)
    {
        $this->loadLayout()
            ->_initLayoutMessages('checkout/session')
            ->_initLayoutMessages('customer/session');

        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->setTitle(
                Mage::getStoreConfig('payment/SveaCheckout/title')
            );
        }

        $block = $this->getLayout()
            ->createBlock(
                'Mage_Core_Block_Template',
                'svea_checkout'
            )
            ->setData('area', 'frontend')
            ->setData('snippet', $snippet)
            ->setTemplate('sveacheckout_snippet_renderer.phtml');

        $this->getLayout()
            ->getBlock('content')
            ->append($block);

        $this->renderLayout();

        return;
    }

    /**
     * Creates queueitem from quote.
     *
     * @param $quoteId
     */
    protected function _insertIntoQueue($quoteId)
    {
        $orderQueue = Mage::getModel('sveacheckout/queue');
        $queueItemExists = $orderQueue->getCollection()
            ->addFieldToFilter('quote_id', $quoteId)
            ->count();

        if (!$queueItemExists) {
            $orderQueue->setData('quote_id', $quoteId)
                ->save();
        }
    }

    /**
     * Wrapps magento layout and renders Thank you-page (snippet).
     *
     */
    public function successAction()
    {
        $quote   = Mage::getSingleton('checkout/session')->getQuote();
        $orderId = Mage::getSingleton('checkout/session')->getPaymentReference();
        $quoteId = (int)$this->getRequest()->getParam('quoteId');
        $session = Mage::getSingleton('checkout/session');
        if (!$quote->getId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
        }

        $orderIDs = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('increment_id', ['eq' => $quote->getReservedOrderId()])
            ->getAllIds();

        $this->loadLayout();
        $analytics = $this->getLayout()
            ->getBlock('google_analytics')
            ->setOrderIds($orderIDs)->toHtml();


        if (!$orderId && !$quote->getId()) {
            $session->setSveaEncounteredError(true);
            $session->addError(
                sprintf(
                    $this->__('Error code 404 - Svea Ekonomi Checkout Could not load cart or order%s'),
                    $this::ERROR_MESSAGE_SUFFIX
                )
            );

            return $this->_redirect('checkout/onepage');
        } else if ($quote->getId()) {

            $orderId = $quote->getPaymentReference();
            $session->setPaymentReference($orderId);
        }

        $svea      = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $sveaOrder = $svea->setupCommunication();

        try {
            $svea->setLocales($sveaOrder, $quote);
            $response = $sveaOrder
                ->setCheckoutOrderId((int)$orderId)
                ->getOrder();
            if (isset($response['Gui'])) {
                if (isset($response['Gui']['Snippet'])) {
                    $html = $response['Gui']['Snippet'] . $analytics;
                    $this->_renderSnippet($html);
                }
            }
        } catch (Exception $ex) {
            $session->setSveaEncounteredError(true);
            $session->addError(
                sprintf(
                    $this->__('Error code 502 - Svea Ekonomi Checkout Could not load success window%s'),
                    $this::ERROR_MESSAGE_SUFFIX
                )
            );

           return $this->_redirect('checkout/onepage');
        }

        if ($quote->getId()) {
            $quote->setIsActive(0)->save();
        }
    }

    /**
     * Wrapps magento layout and renders terms block.
     *
     */
    public function termsAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
