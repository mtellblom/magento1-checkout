<?php

class Svea_Checkout_Model_Quote_Total_Fee
    extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('svea_fee');
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);

        $this->_setAmount(0)
            ->_setBaseAmount(0);

        $collection = $address->getQuote()->getPaymentsCollection();
        if ($collection->count() <= 0
            || $address->getQuote()->getPayment()->getMethod() == null) {
            return $this;
        }

        \dahbug::Dump($address->getQuote()->getPayment());

        if ($address->getQuote()->getPayment()->getMethod() !== 'svea_invoice') {
            return $this;
        }

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        $methodInstance = $address->getQuote()
            ->getPayment()
            ->getMethodInstance();

        $basePaymentFee = (float)$methodInstance->getConfigData('handling_fee');
        if (empty($basePaymentFee)) {
            return $this;
        }

        $paymentFee = $address->getQuote()->getStore()->convertPrice($basePaymentFee, false);
        $address->setSveaPaymentFeeAmount($paymentFee);
        $address->setBaseSveaPaymentFeeAmount($basePaymentFee);

        $this->_setAmount($paymentFee)
            ->_setBaseAmount($basePaymentFee);

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getSveaPaymentFeeAmount() > 0) {
            $method = $address->getQuote()
                ->getPayment()
                ->getMethodInstance();

            $fee = $address->getSveaPaymentFeeAmount();
            if ($method->getConfigData('handling_fee_includes_tax')) {
                $fee += $address->getSveaPaymentFeeTaxAmount();
            }
            $total = array(
                'code' => $this->getCode(),
                'title' => Mage::helper('svea_webpay')->__('invoice_fee'),
                'value' => $fee
            );
            $address->addTotal($total);
        }

        return $this;
    }
}