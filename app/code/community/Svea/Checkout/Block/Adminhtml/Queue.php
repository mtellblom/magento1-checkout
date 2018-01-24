<?php

/**
 * Svea Checkout Admin block.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Adminhtml_Queue
    extends Mage_Adminhtml_Block_Template
{
    /**
     * @var array holds order rows.
     */
    protected $_orderRows = [];

    /**
     * Gets queue data.
     *
     * @return array with data to be used building the admin grid.
     */
    public function getQueueData()
    {
        $request           = Mage::app()->getRequest();
        $moreInfo          = (int)$request->getParam('moreInfo');
        $removeItem        = (int)$request->getParam('delete');
        $filterOrders      = Mage::getStoreConfig('payment/SveaCheckout/queue_show_all');
        $validationMessage = '';
        $sveaStatus        = '';
        $translateState = [
            0 => 'Invalid state',
            1 => 'Checkout initiated',
            2 => 'Got push',
            3 => 'Created, no payment',
            4 => 'Finished',
            5 => 'Error occurred',
        ];

        if ($removeItem) {
            Mage::getModel('sveacheckout/queue')
                ->setQueueId($removeItem)
                ->delete();
        }

        $queueCollection = Mage::getResourceModel('sveacheckout/queue_collection')
            ->setOrder('queue_id', 'DESC');
        if (!$filterOrders) {
            $queueCollection->addFieldToFilter('state', ['nin' => [1, 4]]);
        }

        $details = [];
        $rows    = [];
        $i       = 0;
        $urlModel = Mage::getSingleton('adminhtml/url');

        foreach ($queueCollection as $queueItem) {
            $deleteUrl = $urlModel->getUrl(
                '*/queue/index', ['delete' => $queueItem->getData('queue_id')]
            );
            $detailsUrl = $urlModel->getUrl(
                '*/queue/index', ['moreInfo' => $queueItem->getData('quote_id')]
            );

            $quote = Mage::getModel('sales/quote')
                ->loadByIdWithoutStore($queueItem->getData('quote_id'));

            $mode = Mage::getStoreConfig('payment/SveaCheckout/testmode', $quote->getStoreId()) ? 'test' : 'prod';

            $pushUrl = Mage::getUrl(
                'sveacheckout/push/index', [
                    'quoteId'    => $queueItem->getData('quote_id'),
                    'Simulation' => 'true',
                    'mode'       => $mode,
                    'sveaId'     => $quote->getData('payment_reference'),
                ]
            );

            $cell = [];
            $cell[] = sprintf(
                '<a href="%s"><button type="button" class="success">%s</button></a>',
                $detailsUrl,
                $this->__('Details')
            );

            $cell[] = $queueItem->getOrderId();
            $cell[] = $queueItem->getData('quote_id');
            $cell[] = $quote->getPaymentReference();
            $cell[] = $quote->getCustomerId();
            $cell[] = $quote->getCustomerEmail();
            $cell[] = $quote->getGrandTotal();

            if ($queueItem->getData('quote_id') == $moreInfo) {
                $appEmulation           = Mage::getSingleton('core/app_emulation');
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($quote->getStoreId());

                $svea                   = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
                $sveaOrder              = $svea->setupCommunication();

                $svea->setLocales($sveaOrder);
                $orderId = (int)$quote->getPaymentReference();
                $response = new Varien_Object($sveaOrder->setCheckoutOrderId($orderId)->getOrder());
                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);


                $details['Svea Ekonomi Order'] = $this->_renderSveaOrder($response);
                $details['Magento Quote']      = $this->_renderQuote($quote);
                try {
                    $validationMessage = (!$svea->sveaOrderHasErrors($sveaOrder, $quote, $response))
                        ? '<h1 class="valid">' . $this->__('Order items match between parties.') . '</h1>'
                        : '<h1 class="invalid">' .
                        $this->__('One or more order rows does not match between parties.')
                        . '</h1>';
                } catch (Exception $e) {
                    $validationMessage = 'unable to validate with message:<br/> ' . $e->getMessage();
                }
                $sveaStatus = $this->__('Svea Ekonomi status:') . ' ' . $response->getData('Status');
            }

            $cell[] = $queueItem->getData('STAMP_DATE');
            $cell[] = $queueItem->getData('STAMP_CR_DATE');
            $cell[] = $translateState[(int)$queueItem->getData('state')];

            $cell[] = sprintf(
                '<span data-href="%s" 
                    onclick="viewResponse(this);"
                ><button type="button">%s</button></span>',
                $pushUrl,
                $this->__('Run create process')
            );
            $cell[] = sprintf(
                '<a onclick="return confirm(\'%s\');" href="%s"><button type="button" class="delete">%s</button></a>',
                $this->__('Are you sure?'),
                $deleteUrl,
                $this->__('Delete')
            );

            $rows[$i] = $cell;
            $i++;
        }

        return [
            'details'    => $details,
            'rows'       => $rows,
            'validation' => $validationMessage,
            'sveaStatus' => $sveaStatus,
        ];
    }

    /**
     * Convert order to array.
     *
     * @param  Varien_Object $order the return data from get or create order converted to a Varien Object.
     *
     * @return array
     */
    protected function _renderSveaOrder($order)
    {

        return $order->__toArray();
    }

    /**
     * Collects data to view the detailed quote view.
     *
     * @param  Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    protected function _renderQuote($quote)
    {
        $this->_addCartItems($quote);
        $this->_addTotalRows($quote);
        $this->_addShipping($quote);
        $items['Cart']['Items'] = $this->_orderRows;
        $magentoShipping = $quote->getShippingAddress()->__toArray();
        $magentoBilling  = $quote->getBillingAddress()->__toArray();

        $selectValues = array_flip([
            'firstname',
            'middlename',
            'lastname',
            'suffix',
            'company',
            'street',
            'city',
            'region',
            'postcode',
            'country_id'
        ]);

        $formattedQuote = array_merge($quote->__toArray(), $items);
        $formattedQuote['ShippingAddress'] = array_filter(array_intersect_key($magentoShipping, $selectValues));
        $formattedQuote['BillingAddress']  = array_filter(array_intersect_key($magentoBilling, $selectValues));
        $formattedQuote['EmailAddress']    = $formattedQuote['customer_email'];
        $formattedQuote['PhoneNumber']     = $magentoBilling['telephone'];

        return $formattedQuote;
    }


    /**
     * Go through the quote and add orderItems to variable.
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool on error return false.
     */
    protected function _addCartItems($quote)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getHasChildren() || !$item->getParentItemId()) {
                $sortedItems[$item->getId()]['item'] = $item;
            } else {
                $parentId = $item->getParentItemId();

                if (empty($sortedItems[$parentId])) {
                    $sortedItems[$parentId] = ['children' => []];
                }

                $sortedItems[$parentId]['children'][] = $item;
            }
            unset($item);
        }

        if (!isset($sortedItems)) {
            return false;
        }

        foreach ($sortedItems as $data) {
            $item = isset($data['item'])
                ? $data['item']
                : null;
            $children = isset($data['children'])
                ? $data['children']
                : [];
            if (!$item) {

                continue;
            }
            if ($item->isChildrenCalculated()) {
                foreach ($children as $child) {
                    $this->_processItem($child, $item->getId());
                }
            } else {
                $this->_processItem($item);
            }
        }
    }

    /**
     * Adding a row to the (reservation) svea-order.
     *
     * @param Mage_Sales_Model_Quote_Item $item   quote item
     * @param string                      $prefix SKU prefix
     */
    protected function _processItem($item, $prefix = '')
    {
        if ($item->getQty() > 0) {
            if ($prefix) {
                $prefix = $prefix . '-';
            }

            $this->_addOrderRow([
                'ArticleNumber'      => $prefix . $item->getSku(),
                'Name'               => (string)mb_substr($item->getName(), 0, 40),
                'Quantity'           => (float)round($item->getQty(), 2),
                'UnitPrice'          => (float)$item->getPriceInclTax(),
                'VatPercent'         => (int)round($item->getTaxPercent()),
                'DiscountPercent'    => (int)0,
                'Unit'               => (null),
                'TemporaryReference' => (null),
            ]);
            if ((float)$item->getDiscountAmount()) {
                $this->_addOrderRow([
                    'ArticleNumber'      => (sprintf('discount-%s', $prefix . $item->getId())),
                    'Name'               => (string)mb_substr($item->getName(), 0, 40),
                    'Quantity'           => 1,
                    'UnitPrice'          => $item->getDiscountAmount() * -1,
                    'VatPercent'         => (int)round($item->getTaxPercent()),
                    'DiscountPercent'    => (int)0,
                    'Unit'               => (null),
                    'TemporaryReference' => (null),

                ]);
            }
        }
    }

    /**
     * Get cheapest shipping option.
     *
     * @param  Mage_Sales_Model_Quote $quote
     *
     * @return string|bool=false
     */
    protected function _getCheapestShippingOption($quote)
    {
        $availableShippingRates = $quote->getShippingAddress()
            ->getShippingRatesCollection()->toArray();
        //Remove an array level
        $availableShippingRates = array_pop($availableShippingRates);
        //Order by price
        if (is_array($availableShippingRates)) {
            uasort(
                $availableShippingRates,
                function ($a, $b) {
                    return ($a['price'] < $b['price']) ? -1 : 1;
                }
            );
            //Reset keys
            $availableShippingRates = array_values($availableShippingRates);
        }

        //Select first
        if (isset($availableShippingRates[0]) && isset($availableShippingRates[0]['code'])) {

            return $availableShippingRates[0]['code'];
        }

        return false;
    }

    /**
     * Gets the shipping method, first hand get the pre-chosen by user,
     * secondly: get the admin-selected. Lastly get the cheapest available option.
     *
     *
     * @param  Mage_Sales_Model_Quote $quote
     *
     * @return bool|string
     */
    protected function _selectShippingOption($quote)
    {
        $fallbackMethod = Mage::helper('sveacheckout')
            ->getConfigData('sveacheckout_layout/shipping_method_default');
        $selectedMethod = $quote->getShippingAddress()->getShippingMethod();

        if ($selectedMethod) {
            return $selectedMethod;
        }

        if ($fallbackMethod) {
            return $fallbackMethod;
        }

        return $this->_getCheapestShippingOption($quote);
    }


    /**
     * Adds shipping to the quote and Svea order.
     *
     * @param  Mage_Sales_Model_Quote $quote
     */
    protected function _addShipping($quote)
    {
        $shipping      = $quote->getShippingAddress();
        $vatPercent    = 0;
        $appliedTaxes   = $shipping->getAppliedTaxes();
        $appliedTaxes   = reset($appliedTaxes);
        $shippingTitle = ($shipping->getShippingDescription())
            ? mb_substr($shipping->getShippingDescription(), 0, 40)
            : Mage::helper('sveacheckout')->__('Shipping');

        if (isset($appliedTaxes['percent'])) {
            $vatPercent = $appliedTaxes['percent'];
        }

        $this->_addOrderRow([
            'ArticleNumber'   => '',
            'Name'            => (string)$shippingTitle,
            'Quantity'        => 1,
            'UnitPrice'       => (float)$shipping->getShippingInclTax(),
            'VatPercent'      => $vatPercent,
            'DiscountPercent' => '',
        ]);
    }

    /**
     * Adding fees to the (reservation) Order.
     *
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function _addTotalRows($quote)
    {
        $totals = $quote->getTotals();

        /**
         * Magento standard order-totals should not be treated
         * as fees and thus added to the invoice.
         */
        $removeKeys = [
            'tax',
            'subtotal',
            'cost_total',
            'grand_total',
            'shipping',
            'discount',
        ];

        $taxPercent = 0;
        if (isset($totals['tax']) && isset($totals['grand_total'])) {
            if ($totals['tax']['value']) {
                $taxPercent = $totals['tax']['value'] / ($totals['grand_total']['value'] + $totals['tax']['value']);
            }
        }

        foreach ($removeKeys as $key) {
            if (isset($totals[$key])) {
                unset($totals[$key]);
            }
        }

        /**
         * If there are any totals left, they should be
         * treated as fees and thus added to invoice.
         */
        foreach ($totals as $totalRow) {
            $amount = round($totalRow->getValue(), 2);
            $title = ($totalRow->getTitle())
                ? mb_substr($totalRow->getTitle(), 0, 40)
                : Mage::helper('sveacheckout')->__('fee');
            $this->_addOrderRow([
                'Name'       => (string)$title,
                'VatPercent' => (int)$taxPercent,
                'UnitPrice'  => $amount,
                'Quantity'   => 1,
            ]);
        }
    }

    /**
     * Append a new order row to the array which will be sent to Svea.
     *
     * @param array $array
     */
    protected function _addOrderRow($array)
    {
        $this->_orderRows[] = $array;
    }
}
