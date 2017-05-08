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
        }

        return false;
    }
}
