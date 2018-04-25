<?php

/**
 * Source model to get an array of customer-type settings.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_System_Source_Svea_Customertype
{
    const PRIMARILY_INDIVIDUALS   = 1;
    const PRIMARILY_COMPANIES     = 2;
    const EXCLUSIVELY_INDIVIDUALS = 3;
    const EXCLUSIVELY_COMPANIES   = 4;

    /**
     * Creates a list of available customer-type options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => Mage::helper('sveacheckout')->__('Default'),
                'title' => Mage::helper('sveacheckout')->__('Default'),
                'value' => false,
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Primarily individuals'),
                'title' => Mage::helper('sveacheckout')->__('Primarily individuals'),
                'value' => self::PRIMARILY_INDIVIDUALS,
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Primarily companies'),
                'title' => Mage::helper('sveacheckout')->__('Primarily companies'),
                'value' => self::PRIMARILY_COMPANIES,
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Exclusively individuals'),
                'title' => Mage::helper('sveacheckout')->__('Exclusively individuals'),
                'value' => self::EXCLUSIVELY_INDIVIDUALS,
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Exclusively companies'),
                'title' => Mage::helper('sveacheckout')->__('Exclusively companies'),
                'value' => self::EXCLUSIVELY_COMPANIES,
            ]
        ];
    }
}