<?php

/**
 * Svea Checkout Helper.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Helper_Data
    extends Mage_Core_Helper_Abstract
{
    const ERR_100 = 'Size of Quote differ from Svea';

    /**
     * Retrieve config flag for store by path.
     *
     * @param  string $key
     * @param  int    $storeId
     *
     * @return string
     */
    public function getConfigData($key, $storeId = null)
    {
        if (!$storeId) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }

        return Mage::getStoreConfig("payment/{$key}", $storeId);
    }

    /**
     * Retrieve config flag for store by path.
     *
     * @param  string $key
     * @param  int    $storeId
     *
     * @return string
     */
    public function getConfigFlag($key, $storeId = null)
    {
        if (!$storeId) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }

        return Mage::getStoreConfigFlag("payment/{$key}", $storeId);
    }

    /**
     * Checks if customer is currently in checkout.
     *
     * @return boolean
     */
    public function isInCheckout()
    {
        $request = Mage::app()->getRequest();
        $frontName = $request->getRouteName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();
        $fullAction = "{$frontName}_{$controller}_{$action}";
        $handles = Mage::getConfig()->getNode('frontend/sveacheckout/in_checkout_handles')->asArray();
        if (isset($handles[$fullAction])) {
            return true;
        }

        return false;
    }

    /**
     * Replaces quote with a new one, in order to avoid
     * having multiple orders on the same quote.
     *
     * @return Mage_Sales_Model_Quote
     */
    public function replaceQuote()
    {
        $session = Mage::getSingleton('checkout/session');
        $oldQuote = $session->getQuote();
        $newQuote = Mage::getModel('sales/quote')->setStore(Mage::app()->getStore());
        $newQuote->merge($oldQuote)
            ->collectTotals()
            ->save();
        $session->replaceQuote($newQuote);
        $oldQuote->setIsActive(0);

        return $newQuote;
    }

    /**
     * @param array $quoteItems
     * @param array $sveaOrderItems
     *
     * @return array
     */
    public function compareQuoteToSveaOrder($quoteItems, $sveaOrderItems) {
        if (sizeof($quoteItems) !== sizeof($sveaOrderItems)) {

            return ['error' => $this->__($this::ERR_100)];
        }

        foreach ($quoteItems as $key => $quoteItem) {
            if (!array_key_exists('articleNumber', $quoteItem)) {
                $quoteItems[$key]['articleNumber'] = null;
            }
            if (!array_key_exists('quantity', $quoteItem)) {
                $quoteItems[$key]['quantity'] = 1;
            }
            if (!array_key_exists('discountPercent', $quoteItem)) {
                $quoteItems[$key]['discountPercent'] = 0;
            }
            if (!isset($quoteItem['temporaryReference'])) {
                $quoteItems[$key]['temporaryReference'] = $quoteItem['name'];
            }
            if (array_key_exists('discountId', $quoteItem)) {
                $quoteItems[$key]['amountIncVat'] = $quoteItem['amountIncVat'] * -1;
            }
        }

        foreach ($sveaOrderItems as $key => $sveaOrderItem) {

            if (!array_key_exists('ArticleNumber', $sveaOrderItem)) {
                $sveaOrderItems[$key]['ArticleNumber'] = null;
            }
            if (!array_key_exists('Quantity', $sveaOrderItem)) {
                $quoteItems[$key]['Quantity'] = 1;
            }
            if (!array_key_exists('DiscountPercent', $sveaOrderItem)) {
                $sveaOrderItems[$key]['DiscountPercent'] = 0;
            }
            if (!isset($sveaOrderItem['TemporaryReference'])) {
                $sveaOrderItems[$key]['TemporaryReference'] = $sveaOrderItem['Name'];
            }
        }

        usort($quoteItems, function ($a, $b) {
            return ($a['articleNumber'] < $b['articleNumber']) ? -1 : 1;
        });
        usort($sveaOrderItems, function ($a, $b) {
            return ($a['ArticleNumber'] < $b['ArticleNumber']) ? -1 : 1;
        });

        reset($quoteItems);
        reset($sveaOrderItems);

        $fieldMapper = [
            'articleNumber'      => 'ArticleNumber',
            'quantity'           => 'Quantity',
            'amountIncVat'       => 'UnitPrice',
            'vatPercent'         => 'VatPercent',
            'name'               => 'Name',
            'discountPercent'    => 'DiscountPercent'
        ];

        $errors = [];
        foreach ($quoteItems as $num => $row) {
            foreach ($fieldMapper as $keyInQuote => $keyInSvea) {
                if ($row[$keyInQuote] != $sveaOrderItems[$num][$keyInSvea]) {
                    $errors[] .=  '$row[' . $keyInQuote . '] != $sveaOrderItems[' . $num . ']['. $keyInSvea .']';
                    $errors[] .=  $row[$keyInQuote] .'!='. $sveaOrderItems[$num][$keyInSvea];
                }
            }
        }

        if (sizeof($errors)) {
            return ['error' => $errors];
        }
    }
}
