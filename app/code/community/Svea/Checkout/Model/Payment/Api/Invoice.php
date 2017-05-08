<?php
use Svea\WebPay\WebPayItem;
use Svea\WebPay\WebPayAdmin;
use Svea\WebPay\Constant\DistributionType;

/**
 * Class Svea_Checkout_Model_Payment_Api_Invoice
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Payment_Api_Invoice
    extends Svea_Checkout_Model_Payment_Api_Init
{
    const SVEA_IS_INVOICEABLE               = 'CanDeliverOrder';
    const SVEA_IS_PARTIALLY_INVOICEABLE     = 'CanDeliverPartially';
    const SVEA_ROW_IS_IS_INVOICEABLE        = 'CanDeliverRow';
    const SVEA_CAN_ADD_ORDER_ROW            = 'CanAddOrderRow';
    const SVEA_ROW_IS_IS_UPDATEABLE         = 'CanUpdateOrderRow';
    const SVEA_CURRENT_ROW_IS_IS_UPDATEABLE = 'CanUpdateRow';
    const SVEA_CAN_CREDIT_ORDER_ROWS        = 'CanCreditRow';

    /**
     * Create invoice.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return Varien_Object
     */
    public function processInvoice($payment, $invoice)
    {
        $order                  = $payment->getOrder();

        $sveaOrderId            = (int)$order->getPaymentReference();
        $sveaOrder              = $this->_getCheckoutOrder($order);

        $locale                 = $this->_getLocale($order);
        $paymentItems           = $invoice->getItemsCollection();
        $shippingMethod         = '';
        $canPartiallyProcess    = in_array($this::SVEA_IS_PARTIALLY_INVOICEABLE, $sveaOrder['Actions']);

        if (!in_array('CanDeliverOrder', $sveaOrder['Actions'])) {
            throw new Mage_Adminhtml_Exception(
                'Svea responded: order not billable. '.
                'Order status: ' . $sveaOrder['OrderStatus']
            );
        }

        $shippingAmount = (float)$invoice->getOrder()->getShippingAmount();
        if ($shippingAmount > 0 && !$payment->getShippingCaptured()) {
            $shippingMethod = $order->getShippingDescription();
        }

        $invoiceIncrementId = Mage::getSingleton('eav/config')
                ->getEntityType(Mage_Sales_Model_Order_Invoice::HISTORY_ENTITY_NAME)
                ->fetchNewIncrementId($invoice->getStore()->getId());
        $invoice->setIncrementId($invoiceIncrementId);

        $deliverItems = $this->_getActionRows(
            $paymentItems,
            $sveaOrder['OrderRows'],
            $shippingMethod,
            ['CanDeliverRow', 'CanUpdateRow']
        );

        if (!sizeof($sveaOrder['OrderRows'])) {
            throw new Mage_Adminhtml_Exception(
                'Could not save invoice, No more rows to invoice'
            );
        } 

        foreach ($deliverItems as $key => $item) {
            $actionQty = round($item['action_qty']);
            if ($actionQty < 1) {
                if (!$canPartiallyProcess) {
                    throw new Mage_Adminhtml_Exception('Order cannot be partially processed.');
                }
                //Row should not be delivered, continue.
                unset($deliverItems[$key]);
                continue;
            }

            $this->_adjustQty(
                $item,
                $key,
                $sveaOrderId,
                $locale,
                $sveaOrder['Actions'],
                $invoiceIncrementId
            );
        }

        $request = WebPayAdmin::deliverOrderRows($this->getSveaConfig())
            ->setCheckoutOrderId($sveaOrderId)
            ->setCountryCode($locale['purchase_country'])
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setRowsToDeliver(array_keys($deliverItems));
        $request->deliverCheckoutOrderRows()->doRequest();

        $sveaOrder = $this->_getCheckoutOrder($order);
        return new Varien_Object($sveaOrder);
    }

    /**
     * Adjusts quantity and adds new row with the rest of your quantity.
     * Used when you do partial deliveries.
     *
     * @param array  $item
     * @param int    $key
     * @param int    $sveaOrderId
     * @param string $locale
     * @param bool   $canPartiallyProcess
     *
     * @throws \Mage_Adminhtml_Exception
     */
    protected function _adjustQty(
        $item,
        $key,
        $sveaOrderId,
        $locale,
        $orderActions,
        $referenceNumber
    )
    {
        $adjustQty = $item['action_qty'];
        $qty      = $item['Quantity'];

        if ($adjustQty > $qty) {
            throw new Mage_Adminhtml_Exception('Cannot process more than ordered quantity.');
        }

        if ($adjustQty < $qty) {
            $rest = $item['Quantity'] - $adjustQty;

            if (!in_array($this::SVEA_CURRENT_ROW_IS_IS_UPDATEABLE, $item['Actions'])) {
                throw new Mage_Adminhtml_Exception('Cannot adjust row.');
            }

            if (!in_array($this::SVEA_ROW_IS_IS_UPDATEABLE, $orderActions)) {
                throw new Mage_Adminhtml_Exception('Cannot adjust row.');
            }

            if ($rest && !in_array($this::SVEA_CAN_ADD_ORDER_ROW, $orderActions)) {
                throw new Mage_Adminhtml_Exception('Cannot add rows to this order.');
            }

            $partialActionRow = WebPayItem::numberedOrderRow()
                ->setRowNumber($key)
                ->setArticleNumber($referenceNumber .'-'. $item['ArticleNumber'])
                ->setAmountIncVat((float) $item['UnitPrice'])
                ->setVatPercent((int) $item['VatPercent'])
                ->setQuantity($adjustQty);

            $restOfRowQty     = WebPayItem::orderRow()
                ->setArticleNumber($item['ArticleNumber'])
                ->setName($item['Name'])
                ->setAmountIncVat((float)$item['UnitPrice'])
                ->setVatPercent((int)$item['VatPercent'])
                ->setQuantity((int)$rest);

            $updateRows       = WebPayAdmin::updateOrderRows($this->getSveaConfig())
                ->setCheckoutOrderId($sveaOrderId)
                ->setCountryCode($locale)
                ->updateOrderRow($partialActionRow);

            $addRows          = WebPayAdmin::addOrderRows($this->getSveaConfig())
                ->setCheckoutOrderId($sveaOrderId)
                ->setCountryCode($locale['purchase_country'])
                ->addOrderRow($restOfRowQty);
        }

        if (isset($updateRows)) {
            $updateRows->updateCheckoutOrderRows()->doRequest();
        }
        if (isset($addRows)) {
            $addRows->addCheckoutOrderRows()->doRequest();
        }
    }

    /**
     * Create a credit memo in Svea.
     *
     * @param  Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return Varien_Object
     */
    public function refund($creditMemo)
    {
        $order                  = $creditMemo->getOrder();
        $invoiceNo              = $creditMemo->getInvoice()->getIncrementId();
        $sveaOrderId            = (int)$order->getPaymentReference();
        $sveaConfig             = $this->getSveaConfig();
        $sveaOrder              = $this->_getCheckoutOrder($order);
        $creditMemoItems        = $creditMemo->getItemsCollection();
        $shippingMethod         = '';
        $shippingAmount         = $creditMemo->getOrder()->getShippingAmount();
        $shippingCredit         = $creditMemo->getShippingAmount();

        if ($shippingCredit > 0 && ($shippingCredit == $shippingAmount)) {
            $shippingMethod = $order->getShippingDescription();
        }

        foreach ($sveaOrder['Deliveries'] as $key => $deliveries) {
            foreach ($deliveries['OrderRows'] as $item) {
                if (stristr($item['ArticleNumber'], $invoiceNo) !== false) {
                    $deliveryKey = $key;
                    break;
                }
            }
        }

        if (isset($deliveryKey)) {
            $tmpRefundItems[$sveaOrder['Deliveries'][$deliveryKey]['Id']] = $this->_getActionRows(
                $creditMemoItems,
                $sveaOrder['Deliveries'][$deliveryKey]['OrderRows'],
                $shippingMethod,
                [$this::SVEA_CAN_CREDIT_ORDER_ROWS],
                $invoiceNo
            );
        } else {
             foreach ($sveaOrder['Deliveries'] as $deliveries) {
                 $tmpRefundItems[$deliveries['Id']] = $this->_getActionRows(
                     $creditMemoItems,
                     $deliveries['OrderRows'],
                     $shippingMethod,
                     [$this::SVEA_CAN_CREDIT_ORDER_ROWS]
                 );
             }
         }

        $tmpRefundItems = array_filter($tmpRefundItems);
        $deliveryId     = (int)implode('',array_keys($tmpRefundItems));
        $refundItems    = array_pop($tmpRefundItems);
        $locale         = $this->_getLocale($order);

        foreach ($refundItems as $key => $refundItem) {
            if ($refundItem['action_qty'] == $refundItem['Quantity']) {
                $fullyRefunded[$key] = $refundItem;
            } else {
                $partialRefundedItems[$key] = $refundItem;
            }
        }

        if (isset($fullyRefunded) && sizeof($fullyRefunded)) {
            $creditOrder = WebPayAdmin::creditOrderRows($sveaConfig)
                ->setCheckoutOrderId($sveaOrderId)
                ->setDeliveryId($deliveryId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode($locale['purchase_country'])
                ->setRowsToCredit(array_keys($fullyRefunded));
            $creditOrder->creditCheckoutOrderRows()->doRequest();
        }
        if (isset($partialRefundedItems) && sizeof($partialRefundedItems)) {
            foreach ($partialRefundedItems as $refundItem) {
                $creditOrder = WebPayAdmin::creditOrderRows($sveaConfig)
                    ->setCheckoutOrderId($sveaOrderId)
                    ->setDeliveryId($deliveryId)
                    ->setInvoiceDistributionType(DistributionType::POST)
                    ->setCountryCode($locale['purchase_country']);
                $refundRow = WebPayItem::orderRow()
                    ->setAmountIncVat(($refundItem['UnitPrice'] * $refundItem['action_qty']))
                    ->setName('-' . $refundItem['action_qty'] . 'x ' . $refundItem['ArticleNumber'])
                    ->setVatPercent($refundItem['VatPercent']);
                $creditOrder->addCreditOrderRow($refundRow);
                $creditOrder->creditCheckoutOrderWithNewOrderRow()->doRequest();
            }
        }

        $sveaOrder = $this->_getCheckoutOrder($order);

        return new Varien_Object($sveaOrder);
    }

    /**
     * Fetch order from Svea.
     *
     * @param  Mage_Sales_Model_Order $order.
     *
     * @return string
     */
    protected function _getCheckoutOrder($order)
    {
        $sveaConfig  = $this->getSveaConfig();
        $sveaOrderId = (int)$order->getPaymentReference();
        $locale      = $this->_getLocale($order);

        $request = WebPayAdmin::queryOrder($sveaConfig)
            ->setCheckoutOrderId($sveaOrderId)
            ->setCountryCode($locale['purchase_country']);

        return $request->queryCheckoutOrder()->doRequest();
    }

    /**
     * Get locale from order.
     *
     * @param  Mage_Sales_Model_Order $order
     *
     * @return string|bool=false
     */
    protected function _getLocale($order)
    {
        if ($order->getPayment()) {
            $transactionDetails = $order->getPayment()->getAdditionalInformation();
            if (!is_array($transactionDetails)) {

                return false;
            }
            $transactionDetails = new Varien_Object($transactionDetails);
            $locale = $transactionDetails->getData('reservation/CountryCode');
        }

        if (!isset($locale)) {

            Mage::throwException('No usable locale found, The order is most likely not acknowledged by Svea yet.');
        }

        return $locale;
    }

    /**
     * Extracts svea-rows from requested change rows.
     *
     * @param Mage_Sales_Model_Resource_Order_Creditmemo_Item_Collection
     *          |Mage_Sales_Model_Resource_Order_Invoice_Item_Collection $itemCollection
     * @param Array                                                      $sveaItems
     * @param String                                                     $shippingMethod
     * @param Array                                                      $requireActions
     * @param Int     optional                                           $referenceNumber incrementID reference
     * @return array|bool=false
     *
     * @throws \Mage_Adminhtml_Exception
     */
    protected function _getActionRows(
        $itemCollection,
        $sveaItems,
        $shippingMethod,
        $requireActions,
        $referenceNumber = null
    )
    {
        $chosenItems  = [];
        $return       = [];
        $items        = [];

        if (!$sveaItems) {

            return false;
        }


        foreach ($itemCollection as $item) {
            $prefix     = '';
            if (isset($referenceNumber)) {
                $prefix = $referenceNumber . '-';
            }
            $orderItem  = $item->getOrderItem();
            if ($orderItem->isChildrenCalculated()) {
                $prefix .= $orderItem->getQuoteItemId() .'-';
            }

            if ($item->getDiscountAmount()) {
                $sku     = substr(sprintf('discount-%s', $prefix . trim($orderItem->getQuoteItemId())), 0,40);
                $items[] = [
                    'sku'   => $sku,
                    'qty'   => 1,
                    'Price' => $item->getDiscountAmount()
                ];
            }

            if ($item->getQty()) {
                $items[] = [
                    'sku'   => $prefix . $item->getSku(),
                    'qty'   => $item->getQty(),
                    'Price' => $item->getPriceInclTax()
                ];
            }
        }

        foreach ($sveaItems as $key => $row) {
            if (isset($row['ArticleNumber'])) {
                $itemKey = array_search($row['ArticleNumber'], array_column($items, 'sku'));
                if (false !== $itemKey) {
                    $chosenItems[$key] = $row;
                    $qty = $items[$itemKey]['qty'];
                    $chosenItems[$key]['action_qty'] = (float)$qty;
                }
            } else {
                $itemKey = array_search($row['Name'], array_column($items, 'sku'));
                if (in_array($row['Name'], array_column($items, 'sku'))) {
                    $chosenItems[$key] = $row;
                    $qty = $items[$itemKey]['qty'];
                    $chosenItems[$key]['action_qty'] = (float)$qty;
                }
            }
            if ($shippingMethod && $shippingMethod == $row['Name']) {
                $chosenItems[$key] = $row;
                $chosenItems[$key]['action_qty'] = 1;
            }
        }

        foreach ($chosenItems as $key => $row) {
            foreach($requireActions as $requireAction) {
                if (!in_array($requireAction, $row['Actions'])) {
                    throw new Mage_Adminhtml_Exception(
                        'Order row was unprocesssable.'
                    );
                }
            }

            $return[$row['OrderRowId']] = $chosenItems[$key];
        }

        return  $return;
    }
}
