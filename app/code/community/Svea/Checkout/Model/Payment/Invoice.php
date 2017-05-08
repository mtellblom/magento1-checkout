<?php

/**
 * Basic payment method to be used by Svea Checkout module.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Payment_Invoice
    extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                    = 'sveacheckout';
    protected $_infoBlockType           = 'sveacheckout/adminhtml_info_payment';
    protected $_formBlockType           = 'sveacheckout/form';
    protected $_canCapture              = true;
    protected $_canRefund               = true;
    protected $_canCapturePartial       = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;

    /**
     * Get need for initialisation flag.
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return true;
    }

    /**
     * Instantiate state and set it to state object.
     *
     * @param string        $paymentAction
     * @param Varien_Object $stateObject
     *
     */
    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_NEW;
        $stateObject->setState($state);
        $stateObject->setStatus($state);
        $stateObject->setIsNotified(false);
    }

    /**
     * Validate.
     *
     * @return bool
     */
    public function validate()
    {
        return true;
    }

    /**
     * Set capture transaction ID to invoice for informational purposes.
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return Svea_Checkout_Model_Payment_Invoice
     */
    public function processInvoice($invoice, $payment)
    {
        $order        = $payment->getOrder();
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $environment  = $appEmulation->startEnvironmentEmulation($order->getStoreId());
        $result       = Mage::getModel('sveacheckout/Payment_Api_Invoice')->processInvoice($payment, $invoice);
        $appEmulation->stopEnvironmentEmulation($environment);


        $type        = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
        $id          = $result->getData('Id');
        $i           = 1;
        $txId        = "{$id}-{$type}-{$i}";
        $transaction = $payment->lookupTransaction("{$id}-{$type}-{$i}");

        while ($transaction && $transaction->getId()) {
            $i++;
            $txId        = "{$id}-{$type}-{$i}";
            $transaction = $payment->lookupTransaction($txId);
        }

        Mage::helper('sveacheckout/transaction')->createTransaction(
            $order,
            $result,
            $type,
            $txId
        );
        $invoice->setTransactionId($txId);

        return $this;
    }

    /**
     * Capture payment abstract method.
     *
     * @param  Varien_Object $payment
     * @param  float         $amount
     *
     * @return Svea_Checkout_Model_Payment_Invoice
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setSkipTransactionCreation(true);
        if (!$this->canCapture()) {
            Mage::throwException(
                Mage::helper('payment')->__('Capture action is not available.')
            );
        }

        return $this;
    }

    /**
     * Set transaction ID into creditmemo for informational purposes.
     *
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @param  float                          $amount
     *
     * @return Svea_Checkout_Model_Payment_Invoice
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $order       = $payment->getOrder();
        $creditMemo  = $payment->getCreditmemo();

        $appEmulation    = Mage::getSingleton('core/app_emulation');
        $environmentInfo = $appEmulation->startEnvironmentEmulation($order->getStoreId());
        $result          = Mage::getModel('sveacheckout/Payment_Api_Invoice')->refund($creditMemo);
        $appEmulation->stopEnvironmentEmulation($environmentInfo);

        $type        = Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
        $id          = $result->getData('Id');
        $i           = 1;
        $txId        = "{$id}-{$type}-{$i}";
        $transaction = $payment->lookupTransaction("{$id}-{$type}-{$i}");

        while ($transaction && $transaction->getId()) {
            $i++;
            $txId        = "{$id}-{$type}-{$i}";
            $transaction = $payment->lookupTransaction($txId);
        }

        Mage::helper('sveacheckout/transaction')->createTransaction(
            $order,
            $result,
            $type,
            $txId
        );
        $payment->setTransactionId($txId);

        return $this;
    }

    /**
     * Cancel payment.
     *
     * @param   Mage_Sales_Model_Order_Payment $payment
     *
     * @return  Svea_Checkout_Model_Payment_Invoice
     */
    public function cancel(Varien_Object $payment)
    {
        return $this;
    }

    /**
     * Void payment.
     *
     * @param  Mage_Sales_Model_Order_Payment $payment
     *
     * @return Svea_Checkout_Model_Payment_Invoice
     */
    public function void(Varien_Object $payment)
    {
        return $this->cancel($payment);
    }

    /**
     * Prevents this payment method from being used in other checkouts
     * than sveacheckout.
     * This payment method is only used to "tag" the order that it
     * has been paid with Svea Checkout.
     *
     * Svea Checkout is only to be used if the store is configured with
     * secure URLs.
     *
     * @return boolean
     */
    public function canUseCheckout()
    {
        if (!Mage::helper('sveacheckout')->isInCheckout()) {
            return false;
        }

        return true;
    }

    /**
     * Check whether partial refund could be done.
     *
     * @return bool
     */
    public function canRefundPartialPerInvoice()
    {
        return $this->_canTakePartialActions();
    }

    /**
     * Check whether partial capture could be done.
     *
     * @return bool
     */
    public function canCapturePartial()
    {
        return $this->_canTakePartialActions();
    }

    /**
     * Not all methods within this module
     * supports partial credit memos or invoices.
     *
     * @return bool
     */
    protected function _canTakePartialActions()
    {

        $paymentMethodXpath = 'info_instance/additional_information/reservation/PaymentType';
        $method = strtolower($this->getData($paymentMethodXpath));

        switch ($method) {
            case 'invoice':
            case 'account':
            case 'accountcredit':

                return true;
            default:

                return false;
        }
    }

    /**
     * Adding a row to the (reservation)Order.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @param string                      $prefix
     */
    protected function _processItem($item, $prefix = '')
    {
        $sveaOrder = $this->getSveaOrder();
        if ($item->getQty() > 0) {
            if ($prefix) {
                $prefix = $prefix . '-';
            }

            $orderRowItem = WebPayItem::orderRow()
                ->setAmountIncVat((float)$item->getPriceInclTax())
                ->setVatPercent((int)round($item->getTaxPercent()))
                ->setQuantity((float)round($item->getQty(), 2))
                ->setArticleNumber($prefix . $item->getSku())
                ->setName((string)substr($item->getName(), 0, 40));

            $sveaOrder->addOrderRow($orderRowItem);

            if ((float)$item->getDiscountAmount()) {
                $itemRowDiscount = WebPayItem::fixedDiscount()
                    ->setName(substr(sprintf('discount-%s', $prefix . $item->getId()), 0, 40))
                    ->setVatPercent((int)round($item->getTaxPercent()))
                    ->setAmountExVat((float)$item->getDiscountAmount());

                $sveaOrder->addDiscount($itemRowDiscount);
            }
        }
    }
}
