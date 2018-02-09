<?php

/**
 * Source model to get an array of supported language settings.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_System_Source_Svea_Locale
{
    /**
     * Locale code for sv-se.
     *
     * @var string
     */
    const LOCALE_SV_SE = 'sv-SE';
    const LOCALE_NN_NO = 'nn-NO';
    const LOCALE_FI_FI = 'fi-FI';

    /**
     * Creates an option array with supported combinations of languages,
     * countries and locales.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => Mage::helper('sveacheckout')->__('Sweden'),
                'title' => Mage::helper('sveacheckout')->__('Sweden'),
                'value' => serialize($this->getOption(self::LOCALE_SV_SE)),
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Norway'),
                'title' => Mage::helper('sveacheckout')->__('Norway'),
                'value' => serialize($this->getOption(self::LOCALE_NN_NO)),
            ],
            [
                'label' => Mage::helper('sveacheckout')->__('Finland'),
                'title' => Mage::helper('sveacheckout')->__('Finland'),
                'value' => serialize($this->getOption(self::LOCALE_FI_FI)),
            ],
        ];
    }

    /**
     * Svea countries from locale code.
     *
     * @param string $locale
     *
     * @return array | bool
     */
    public function getOption($locale)
    {
        switch ($locale) {
            case self::LOCALE_SV_SE:
                return [
                    'locale'            => $locale,
                    'purchase_country'  => 'SE',
                    'purchase_currency' => 'SEK',
                ];

            case self::LOCALE_NN_NO:
                return [
                    'locale'            => $locale,
                    'purchase_country'  => 'NO',
                    'purchase_currency' => 'NOK',
                ];

            case self::LOCALE_FI_FI:
                return [
                    'locale'            => $locale,
                    'purchase_country'  => 'FI',
                    'purchase_currency' => 'EUR',
                ];
        }

        return false;
    }
}
