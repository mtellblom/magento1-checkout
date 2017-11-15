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

            $pushResponse = array_merge($orderData->getData(), ['lastErrorMessage' => $e->getMessage()]);

            $orderQueueItem
                ->setQuoteId($quote->getId())
                ->setPushResponse($pushResponse)
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
        $billingAddress   = new Varien_Object($data->getData('BillingAddress'));
        $shippingAddress  = new Varien_Object($data->getData('ShippingAddress'));
        $customer         = new Varien_Object($data->getData('Customer'));
        $reference        = ($data->getData('CustomerReference'))
                          ? ($data->getData('CustomerReference'))
                          : false;
        $billingFirstname = ($billingAddress->getData('FirstName'))
                          ? $billingAddress->getData('FirstName')
                          : $notNull;
        $billingFirstname = ($billingFirstname)
                          ? $billingFirstname
                          : $notNull;
        if (true == $customer->getData('IsCompany')) {
            $billingCompany   = $billingAddress->getData('FullName');
            $shippingCompany  = $shippingAddress->getData('FullName');
            $billingFirstname = ($reference)
                              ? $reference
                              : $notNull;
        }
        $billingLastname  = ($billingAddress->getData('LastName'))
                          ? $billingAddress->getData('LastName')
                          : $notNull;

        $street = implode(
            "\n",
            [
                $billingAddress->getData('StreetAddress'),
                $billingAddress->getData('CoAddress'),
            ]
        );
        $street  = ($street) ? $street : $notNull;
        $city    = $billingAddress->getData('City');
        $city    = $city ? $city : $notNull;
        $zip     = $billingAddress->getData('PostalCode');
        $zip     = $zip ? $zip : $notNull;
        $phone   = ($data->getData('PhoneNumber'));
        $phone   = $phone ? $phone : $notNull;
        $country = strtoupper($billingAddress->getData('CountryCode'));
        $country = $country ? $country : $notNull;

        $billingAddressData = [
            'firstname'  => $billingFirstname,
            'lastname'   => $billingLastname,
            'street'     => $street,
            'city'       => $city,
            'postcode'   => $zip,
            'telephone'  => $phone,
            'country_id' => $country,
        ];

        if (true == $customer->getData('IsCompany') && $reference) {
            $shippingFirstname = $reference;
        } else {
            $shippingFirstname = ($shippingAddress->getData('FirstName'))
                               ? $shippingAddress->getData('FirstName')
                               : $notNull;
        }

        $shippingLastname = $shippingAddress->getData('LastName')
                          ? $shippingAddress->getData('LastName')
                          : $notNull;

        $street = implode(
            "\n",
            [
                $shippingAddress->getData('StreetAddress'),
                $shippingAddress->getData('CoAddress'),
            ]
        );

        $street  = ($street) ? $street : $notNull;
        $city    = $shippingAddress->getData('City');
        $city    = ($city) ? $city : $notNull;
        $zip     = $shippingAddress->getData('PostalCode');
        $zip     = ($zip) ? $zip : $notNull;
        $country = strtoupper($shippingAddress->getData('CountryCode'));
        $country = ($country) ? $country : $notNull;

        $shippingAddressData = [
            'firstname'  => $shippingFirstname,
            'lastname'   => $shippingLastname,
            'street'     => $street,
            'city'       => $city,
            'postcode'   => $zip,
            'country_id' => $country,
            'telephone'  => $phone,
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

        $email = $data->getData('EmailAddress');
        $email = ($email) ? $email : $notNull;

        $quote->setCustomerEmail($email);
        $quote->setCustomerFirstname($shippingFirstname);
        $quote->setCustomerLastname($shippingLastname);
    }
}