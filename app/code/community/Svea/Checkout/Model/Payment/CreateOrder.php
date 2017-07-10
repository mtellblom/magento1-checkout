<?php

/**
 * Svea Checkout create order model.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Payment_CreateOrder
    extends Mage_Core_Model_Abstract
{
    /**
     * Create the Magento order after response from Svea.
     *
     * @param  Mage_Sales_Model_Quote    $quote
     * @param  Varien_Object             $orderData
     * @param  Svea_Checkout_Model_Queue $orderQueueItem
     *
     * @throws Mage_Core_Exception
     *
     * @return string error message.
     */
    public function createOrder($quote, $orderData, $orderQueueItem)
    {
        $order  = false;
        $logger = Mage::helper('sveacheckout/debug');

        $svea           = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $sveaOrder      = $svea->setupCommunication();
        $hasErrors      = $svea->sveaOrderHasErrors($sveaOrder, $quote, $orderData);

        if ($hasErrors) {

            throw new Mage_Core_Exception('Quote has errors.');
        }

        $wasSuccess = false;
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();
        try {
            $this->_addAddressToQuote($quote, $orderData);
            $quote->collectTotals();
            $quoteService = Mage::getModel('sales/service_quote', $quote);
            $quoteService->submitAll();
            $order = $quoteService->getOrder();

            $order->setPaymentReference($quote->getPaymentReference())
                ->save();
            $quote->setIsActive(0)->save();
            $type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER;
            $id   = $orderData->getData('OrderId');
            Mage::helper('sveacheckout/transaction')->createTransaction(
                $order,
                $orderData,
                $type,
                "{$id}-{$type}"
            );

            $orderQueueItem
                ->setQuoteId($quote->getId())
                ->setPushResponse($orderData->getData())
                ->setOrderId($order->getId())
                ->setState($orderQueueItem::SVEA_QUEUE_STATE_NEW)
                ->save();

            $profiles = $quoteService->getRecurringPaymentProfiles();
            $adapter->commit();
            $logger->writeToLog('Magento order created from ' . $quote->getPaymentReference() . ' ' . $order->getId());
            $wasSuccess = true;
        } catch (Exception $e) {
            $adapter->rollback();

            $orderQueueItem
                ->setQuoteId($quote->getId())
                ->setPushResponse($orderData->getData())
                ->setState($orderQueueItem::SVEA_QUEUE_STATE_ERR)
                ->save();

            Mage::logException($e);
            $error = $e->getMessage();
        }

        if ($order && $wasSuccess) {
            $order->afterCommitCallback();
            $quote->afterCommitCallback();
            Mage::getSingleton('checkout/session')
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastSuccessQuoteId($quote->getId())
                ->setLastQuoteId($quote->getId());
            Mage::dispatchEvent(
                'checkout_submit_all_after',
                ['order' => $order, 'quote' => $quote, 'recurring_profiles' => $profiles]
            );

            return true;
        }

        return $error;
    }
    /**
     * Add address from Svea to quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Varien_Object          $data
     *
     */
    protected function _addAddressToQuote($quote, $data)
    {
        //ZeroWidthSpace
        $notNull = html_entity_decode('&#8203;');

        $billingAddress = new Varien_Object($data->getData('BillingAddress'));
        $shippingAddress = new Varien_Object($data->getData('ShippingAddress'));

        $customer = new Varien_Object($data->getData('Customer'));

        if ($customer->getData('IsCompany') == true) {
            $billingCompany  = $billingAddress->getData('FullName');
            $shippingCompany = $shippingAddress->getData('FullName');
        }

        $billingFirstname = ($billingAddress->getData('FirstName'))
            ? $billingAddress->getData('FirstName')
            : $billingAddress->getData('FullName');

        $billingFirstname = ($billingFirstname)
            ? $billingFirstname
            : $notNull;

        $billingLastname = ($billingAddress->getData('LastName'))
            ? $billingAddress->getData('LastName')
            : $notNull;

        $billingAddressData = [
            'firstname'  => $billingFirstname,
            'lastname'   => $billingLastname,
            'street'     => implode(
                "\n",
                [
                    $billingAddress->getData('StreetAddress'),
                    $billingAddress->getData('CoAddress'),
                ]
            ),
            'city'       => $billingAddress->getData('City'),
            'postcode'   => $billingAddress->getData('PostalCode'),
            'telephone'  => $data->getData('PhoneNumber'),
            'country_id' => strtoupper($billingAddress->getData('CountryCode')),
        ];

        $shippingFirstname = ($shippingAddress->getData('FirstName'))
            ? $shippingAddress->getData('FirstName')
            : $shippingAddress->getData('FullName');

        $shippingFirstname = ($shippingFirstname)
            ? $shippingFirstname
            : $notNull;

        $shippingLastname = $shippingAddress->getData('LastName')
            ? $shippingAddress->getData('LastName')
            : $notNull;

        $shippingAddressData = [
            'firstname' => $shippingFirstname,
            'lastname'  => $shippingLastname,

            'street'     => implode(
                "\n",
                [
                    $shippingAddress->getData('StreetAddress'),
                    $shippingAddress->getData('CoAddress'),
                ]
            ),
            'city'       => $shippingAddress->getData('City'),
            'postcode'   => $shippingAddress->getData('PostalCode'),
            'country_id' => strtoupper($shippingAddress->getData('CountryCode')),
            'telephone'  => $data->getData('PhoneNumber'),
        ];

        if (isset($billingCompany)) {
            $billingAddressData['company'] = $billingCompany;
        }
        if (isset($shippingCompany)) {
            $shippingAddressData['company'] = $shippingCompany;
        }

        $quote->getBillingAddress()->addData($billingAddressData);
        $quote->getShippingAddress()->addData($shippingAddressData)
            ->setCollectShippingRates(true);

        $quote->setCustomerEmail($data->getData('EmailAddress'));
        $quote->setCustomerFirstname($shippingAddress->getFirstName());
        $quote->setCustomerLastname($shippingAddress->getLastName());
    }
}
