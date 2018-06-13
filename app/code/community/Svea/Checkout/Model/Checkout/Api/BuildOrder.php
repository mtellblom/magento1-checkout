<?php
use Svea\WebPay\WebPayItem;

/**
 * Svea Checkout create order model.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Checkout_Api_BuildOrder
    extends Svea_Checkout_Model_Checkout_Api_Init
{
    /**
     * In order to make sure our quote is up to date; we instantiate a new order,
     * with a prefixed identifier and diff the response to our current.
     *
     * If the cart from the responses differ, the quote has changed.
     * If the (reservation)order is in a state where we can update it, we do.
     * Then we run the validation again to make sure they now are in sync.
     *
     * If the order is still not in sync we try once again.
     *
     * @param  array    $response
     * @param  int|null $tries loop counter
     *
     * @return bool
     */
    public function sveaOrderHasErrors($sveaOrder, $quote, $response, $tries = 0)
    {
        if (is_object($response)) {
            $response = $response->getData();
        }

        $fakeOrder = $this->createSveaOrderFromQuote($quote, true)->getCheckoutOrderBuilder();
        //to Array.
        $fakeOrder = json_decode(json_encode($fakeOrder), true);
        $diff      = $this->_diffOrderRows($fakeOrder['rows'], $response['Cart']['Items']);

        if (sizeof($diff['error'])) {
            if ($tries >= 2) {

                return true;
            }

            if(!$this->sveaOrderHasErrors($sveaOrder, $quote->collectTotals(), $response, $tries + 1)) {
                $quote->save();

                return false;
            }

            if (isset($response['Status']) && $response['Status'] != 'Created') {

                return true;
            }

            try {
                $updatedOrder = $sveaOrder->setCheckoutOrderId((int)$response['OrderId'])->updateOrder();

                return $this->sveaOrderHasErrors($sveaOrder, $quote, $updatedOrder, $tries + 1);
            } catch (Exception $e) {

                return true;
            }
        }

        return false;
    }

    /**
     * Diff order Rows.
     *
     * @param $fakeOrderRows
     * @param $orderRows
     */
    protected function _diffOrderRows($fakeOrderRows, $orderRows)
    {
        $helper = Mage::helper('sveacheckout');
        $differenceBetweenArrays = $helper->compareQuoteToSveaOrder($fakeOrderRows, $orderRows);

        return $differenceBetweenArrays;
    }

    /**
     * Instantiates an order in Svea:s end and
     * returns the (reservation)order as an array.
     *
     * @param  Mage_Sales_Model_Quote $quote
     * @param  bool $test
     *
     * @return \Svea\Checkout\CheckoutClient
     */
    public function createSveaOrderFromQuote($quote, $test = false)
    {
        $sveaOrder = $this->setupCommunication();

        /*if ($test === false) {
            $quote = $this->checkQuote($sveaOrder, $quote);
        }*/
        $this->_setupOrderConfig($sveaOrder, $quote)
            ->setLocales($sveaOrder, $quote)
            ->_presetValues($quote,$sveaOrder)
            ->_addCartItems($quote)
            ->_addShipping($quote, $sveaOrder, $test)
            ->_addTotalRows($quote, $sveaOrder);

        return $sveaOrder;
    }

    /**
     * Check if Svea order is still valid.
     *
     * @param $sveaOrder
     * @param $quote
     *
     * @return mixed
     */
    protected function checkQuote($sveaOrder, $quote) {
        /*$sveaId = (int)$quote->getPaymentReference();
        if ($sveaId) {
            try {
                $orderResponse = $sveaOrder->setCheckoutOrderId($sveaId)->getOrder($sveaId);
            } catch (\Svea\Checkout\Exception\SveaApiException $e) {
                $quote = Mage::helper('sveacheckout')->replaceQuote();
            }

            if (isset($orderResponse) && $orderResponse['Status'] != 'Created'){
                $quote = Mage::helper('sveacheckout')->replaceQuote();
            }
        }*/

        return $quote;
    }

    /**
     * Setup feed Svea with initial settings, like quote identifier and URIs.
     * If fakeorder is true, the id will be faked, and order non-useable nor created in magento.
     *
     * @param Svea\WebPay\BuildOrder $SveaOrder SveaOrder Object.
     * @param  bool $fake                       Used for validation purposes.
     *
     * @return Svea_Checkout_Model_Checkout_Api_BuildOrder
     */
    protected function _setupOrderConfig($sveaOrder, $quote)
    {
        $quoteId = $quote->getId();
        $storeId = Mage::app()->getStore()->getId();
        $mode    = Mage::getStoreConfig('payment/SveaCheckout/testmode', $storeId) ? 'test' : 'prod';
        $secret  = urlencode(Mage::getModel('Core/Encryption')->encrypt($quoteId));

        $pushParams      = [
            'quoteId' => $quoteId,
            'mode'    => $mode,
        ];

        if (isset($pushParams['sveaId'])) {
            $pushParams['sveaId'] = $quote->getPaymentReference();
        }

        $validationParams = array_merge(
            $pushParams,
            ['secret' => $secret]
        );

        $pushUri          = Mage::getUrl('sveacheckout/push',       $pushParams);
        $validationUri    = Mage::getUrl('sveacheckout/validation', $validationParams);
        $termsUri         = Mage::getUrl('sveacheckout/index/terms', ['quoteId' => $quoteId]);

        //payment_SveaCheckout_override_terms_uri
        $overrideTerms    = Mage::getStoreConfig('payment/SveaCheckout/override_terms_uri');
        $overrideTermsUri = Mage::getStoreConfig('payment/SveaCheckout/terms_uri');

        $overridePush     = Mage::getStoreConfig('payment/sveacheckout_dev_settings/override_push_uri');
        $overridePushUri  = Mage::getStoreConfig('payment/sveacheckout_dev_settings/push_uri');

        if (!$quoteId) {
            Mage::throwException('setup - No valid quote.');
        }

        if ($overrideTerms && !empty($overrideTermsUri)) {
            $termsUri = Mage::getUrl($overrideTermsUri, []);
        }

        if ($overridePush && !empty($overridePushUri)) {
            $pushUri       = str_replace(Mage::getBaseUrl(), $overridePushUri, $pushUri);
            $validationUri = str_replace(Mage::getBaseUrl(), $overridePushUri, $validationUri);
        }

        //To avoid order already being created, if you for example have
        //stageEnv/devEnv and ProductionEnv with quote id in same range.
        $allowedLength = 32;
        $separator = '_';
        $lengthOfHash  = $allowedLength - (strlen((string)$quoteId) + strlen($separator));
        $hashedBaseUrl = sha1(Mage::getBaseUrl());
        $clientId      = $quoteId . $separator . mb_substr($hashedBaseUrl, 0, $lengthOfHash);

        $sveaOrder->setClientOrderNumber($clientId)
            ->setCheckoutUri(Mage::getUrl('sveacheckout/index', ['quoteId' => $quoteId, 'reactivate'=>'true']))
            ->setValidationCallBackUri($validationUri)
            ->setConfirmationUri(Mage::getUrl('sveacheckout/index/success', ['quoteId' => $quoteId]))
            ->setPushUri($pushUri)
            ->setTermsUri($termsUri);

        return $this;
    }

    /**
     * Set locales to (reservation)order.
     *
     * @param Svea\WebPay\BuildOrder $sveaOrder SveaOrder Object.
     *
     * @return Svea_Checkout_Model_Checkout_Api_BuildOrder
     */
    public function setLocales($sveaOrder, $quote = null)
    {
        $localeData = Mage::getStoreConfig('payment/SveaCheckout/purchase_locale');
        $locale     = unserialize($localeData);

        if (!isset($locale) || !is_array($locale)) {
            Mage::throwException('No usable locale found.');
        }

        $country = ($quote) ? $quote->getShippingAddress()->getCountryId() : null;
        $country = ($country) ? $country : $locale['purchase_country'];

        $sveaOrder
            ->setCountryCode($country)
            ->setCurrency($locale['purchase_currency'])
            ->setLocale($locale['locale']);

        return $this;
    }

    /**
     * Send Svea customer details for logged in customers.
     *
     * @param Svea\WebPay\BuildOrder $sveaOrder SveaOrder Object.
     *
     * @return Svea_Checkout_Model_Checkout_Api_BuildOrder
     */
    protected function _presetValues($quote, $sveaOrder)
    {
        $allowedCustomerType  =  Mage::getStoreConfig('payment/SveaCheckout/allowed_customers');
        if ($allowedCustomerType !== false) {
            $ct = Mage::getModel('sveacheckout/System_Source_Svea_Customertype');

            $readOnly  = (in_array($allowedCustomerType, [$ct::EXCLUSIVELY_INDIVIDUALS, $ct::EXCLUSIVELY_COMPANIES]))
                ? true
                : false;
            $isCompany = (in_array($allowedCustomerType, [$ct::EXCLUSIVELY_COMPANIES, $ct::PRIMARILY_COMPANIES]))
                ? true
                : false;

            $presetIsCompany = WebPayItem::presetValue()
                ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::IS_COMPANY)
                ->setValue($isCompany)
                ->setIsReadonly($readOnly);
            $sveaOrder->addPresetValue($presetIsCompany);
        }

        if (!$quote->getCustomer() || !$quote->getCustomer()->getPrimaryBillingAddress()) {
            return $this;
        }

        $primaryBilling = $quote->getCustomer()->getPrimaryBillingAddress();
        $telephone = $primaryBilling->getTelephone();
        $presetPhoneNumber = WebPayItem::presetValue()
            ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::PHONE_NUMBER)
            ->setValue($telephone)
            ->setIsReadonly(false);
        $sveaOrder->addPresetValue($presetPhoneNumber);

        $zip = $primaryBilling->getPostcode();
        $presetPostcode = WebPayItem::presetValue()
            ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::POSTAL_CODE)
            ->setValue($zip)
            ->setIsReadonly(false);
        $sveaOrder->addPresetValue($presetPostcode);

        $email = $primaryBilling->getEmail();
        $presetEmail = WebPayItem::presetValue()
            ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::EMAIL_ADDRESS)
            ->setValue($email)
            ->setIsReadonly(false);
        $sveaOrder->addPresetValue($presetEmail);

        return $this;
    }

    /**
     * Go through the quote and add quoteItems to the invoiceOrder.
     *
     * @param  Mage_Sales_Model_Quote $quote
     *
     * @throws Mage_Core_Exception
     *
     * @return Svea_Checkout_Model_Checkout_Api_BuildOrder
     */
    protected function _addCartItems($quote)
    {
        $sortedItems = [];
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
                    $this->_processItem($child, $item->getId(), $item->getQty());
                }
            } else {
                $this->_processItem($item);
            }
        }

        return $this;
    }

    /**
     * Adding a row to the (reservation)Order.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @param string                      $prefix quote_item_id | parent_quote_item_id
     * @param float|integer                               $multiply
     */
    protected function _processItem($item, $prefix = '', $multiply = 1)
    {
        $sveaOrder = $this->getSveaOrder();
        if ($item->getQty() > 0) {
            if ($prefix) {
                $prefix = $prefix . '-';
            }

            $qty = $item->getQty() * $multiply;
            $orderRowItem = WebPayItem::orderRow()
                ->setAmountIncVat((float)$item->getPriceInclTax())
                ->setVatPercent((int)round($item->getTaxPercent()))
                ->setQuantity((float)round($qty, 2))
                ->setArticleNumber($prefix . $item->getSku())
                ->setName((string)mb_substr($item->getName() . $this->getItemOptions($item), 0, 40))
                ->setTemporaryReference((string)$item->getId());
            $sveaOrder->addOrderRow($orderRowItem);

            if ((float)$item->getDiscountAmount()) {
                $itemRowDiscount = WebPayItem::fixedDiscount()
                    ->setName(mb_substr(sprintf('discount-%s', $prefix . $item->getId()), 0, 40))
                    ->setVatPercent((int)round($item->getTaxPercent()))
                    ->setAmountIncVat((float)$item->getDiscountAmount());

                $sveaOrder->addDiscount($itemRowDiscount);
            }
        }
    }

    /**
     * Get item options from orderitem.
     *
     * @param $item
     *
     * @return string
     */
    protected function getItemOptions($item)
    {
        $paymentInformation = unserialize($item->getQuote()->getPaymentInformation());
        $isActive = (isset($paymentInformation['view_options_on_invoice']))
                  ? $paymentInformation['view_options_on_invoice']
                  : false;

        if (!$isActive) {

            return '';
        }

        $itemOptions = [];
        $options     = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        if ($options) {
            if (isset($options['options']) && !empty($options['options'])) {
                $itemOptions = array_merge($itemOptions, $options['options']);
            }
            if (isset($options['additional_options']) && !empty($options['additional_options'])) {
                $itemOptions = array_merge($itemOptions, $options['additional_options']);
            }
            if (isset($options['attributes_info']) && !empty($options['attributes_info'])) {
                $itemOptions = array_merge($itemOptions, $options['attributes_info']);
            }
        }
        $options = [];
        foreach ($itemOptions as $option) {
            $options[] = implode(': ', $option);
        }
        $options = implode(', ',$options);

        if (!empty(trim($options))) {

            return ' '.$options;
        }
    }

    /**
     * Adds Shipping to quote and Svea Order.
     *
     * @param Svea\WebPay\BuildOrder $sveaOrder SveaOrder Object.
     * @param Mage_Sales_Model_Quote $quote     Quote.
     *
     * @throws Exception
     *
     * @return Svea_Checkout_Model_Checkout_Api_BuildOrder
     */
    protected function _addShipping($quote, $sveaOrder, $noSave)
    {
        $didNotLoadFromQuote = false;
        //Default shipping method.
        $method = Mage::helper('sveacheckout')
            ->getConfigData('sveacheckout_layout/shipping_method_default');

        if (!$quote->getBillingAddress()->getCountryId()) {
            $this->_addBasicAddressToQuote($quote);
        }

        //Chosen shipping method.
        if ($quote->getShippingAddress()->getShippingMethod()) {
            $method = $quote->getShippingAddress()->getShippingMethod();
        }

        //Neither Default nor chosen exists, select cheapest option.
        if (!($quote->getShippingAddress()->getShippingRateByCode($method))) {
            $method = $this->_getCheapestShippingOption($quote);
        }

        $shippingAddress = $quote->getShippingAddress();
        if(!$noSave) {
            //update shipping with rates.
            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setFreeShipping(false)
                ->setShippingMethod($method);

            if ('freeshipping_freeshipping' == $method) {
                $shippingAddress
                    ->setCollectShippingRates(true)
                    ->collectShippingRates()
                    ->setFreeShipping(true)
                    ->setShippingMethod($method);
            }
            $shippingAddress->save();
        }

        $quote->setTotalsCollectedFlag(false)
            ->collectTotals();

        $methodTitle = $shippingAddress->getShippingDescription();


        if ($noSave) {
            $ratesCollection = $shippingAddress->getShippingRatesCollection();
            foreach ($ratesCollection as $rate) {
                if ($rate->getCode() == $method) {
                    $methodTitle = $rate->getCarrierTitle() . ' - ' . $rate->getData('method_title');
                    $didNotLoadFromQuote = true;
                    $fallbackPrice  = $rate->getPrice();
                }
            }
        }

        //Add shipping to SveaOrder.
        $shipping       = $quote->getShippingAddress();
        $vatPercent     = 0;
        $shippingTitle  = ($methodTitle)
                        ? mb_substr($methodTitle, 0, 40)
                        : Mage::helper('sveacheckout')->__('Shipping');
        $shippingPrice  = ($didNotLoadFromQuote)
                        ? $fallbackPrice
                        : $shipping->getShippingInclTax();
        $appliedTaxes   = $shipping->getAppliedTaxes();
        $appliedTaxes   = reset($appliedTaxes);
        if (isset($appliedTaxes['percent'])) {
            $vatPercent = $appliedTaxes['percent'];
        }

        $shippingFee = WebPayItem::shippingFee()
            ->setName((string)$shippingTitle)
            ->setVatPercent((int)$vatPercent)
            ->setAmountIncVat((float)$shippingPrice);

        $sveaOrder->addFee($shippingFee);

        return $this;
    }

    /**
     * Add a basic address to quote if missing.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _addBasicAddressToQuote($quote)
    {
        $defaultCountry = Mage::helper('core')->getDefaultCountry();
        $country = ($quote) ? $quote->getShippingAddress()->getCountryId() : null;
        $country = ($country) ? $country : $defaultCountry;
        $country = Mage::app()->getRequest()->getParam('country_id');

        if (!$quote->getBillingAddress()->getCountryId()) {
            $quote->getBillingAddress()
                ->setCountryId($country)
                ->setCollectShippingRates(true);
        }
        if (!$quote->getShippingAddress()->getCountryId()) {
            $quote->getShippingAddress()
                ->setCountryId($country)
                ->setCollectShippingRates(true);
        }

        return $this;
    }

    /**
     * Get cheapest shipping option.
     *
     * @return string|bool=false
     */
    protected function _getCheapestShippingOption($quote)
    {
        //Get array of available rates
        $availableShippingRates = $quote
            ->getShippingAddress()->getShippingRatesCollection()->toArray();
        //Remove a level
        $availableShippingRates = array_pop($availableShippingRates);
        //Sort by price
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
     * Adding fees to the (reservation)Order.
     *
     * @param Svea\WebPay\BuildOrder $sveaOrder SveaOrder Object.
     *
     * @return Svea_Checkout_Model_Checkout_Api_BuildOrder
     */
    protected function _addTotalRows($quote, $sveaOrder)
    {
        $totals    = $quote->getTotals();

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

            $fee = WebPayItem::invoiceFee()
                ->setName((string)$title)
                ->setVatPercent((int)$taxPercent)
                ->setAmountIncVat($amount);

            $sveaOrder->addFee($fee);
        }

        return $this;
    }
}
