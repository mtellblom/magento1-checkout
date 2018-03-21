<?php

/**
 * Source model to get an array of supported language settings.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_System_Source_Svea_Reference
{
    /**
     * Creates a list of available reference options.
     * countries and locales.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => Mage::helper('sveacheckout')->__('Suffixed Order ID'),
                'title' => Mage::helper('sveacheckout')->__('Suffixed Order ID'),
                'value' => 'suffixed-order-id',
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Suffixed Increment ID'),
                'title' => Mage::helper('sveacheckout')->__('Suffixed Increment ID'),
                'value' => 'suffixed-increment-id',
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Plain Order ID'),
                'title' => Mage::helper('sveacheckout')->__('Plain Order ID'),
                'value' => 'plain-order-id',
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Plain Increment ID'),
                'title' => Mage::helper('sveacheckout')->__('Plain Increment ID'),
                'value' => 'plain-increment-id',
            ]
        ];
    }
}
