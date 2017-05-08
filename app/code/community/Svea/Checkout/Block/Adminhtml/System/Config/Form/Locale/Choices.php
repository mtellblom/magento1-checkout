<?php

/**
 * Adminhtml form for locale data option rendering.
 *
 * @package  Svea_Checkout
 * @module   Svea
 * @author   Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Adminhtml_System_Config_Form_Locale_Choices
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Adds a HTML table under element with all selectable values.
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $html = parent::_getElementHtml($element);
        $this->setElement($element);
        $html .= '<table><tr>';
        foreach ($this->_getTableHeader() as $title) {
            $html .= "<th style='padding-right: 5px;'>{$title}</th>";
        }
        $html .= '</tr>';
        foreach ($this->_getPrepareArrayForTable() as $data) {
            $html .= '<tr>';
            foreach ($data as $key => $value) {
                if (array_key_exists($key, $this->_getTableHeader())) {
                    $html .= "<td style='padding-right: 15px;'>$value</td>";
                }
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Returns the table headers for the locale data table.
     *
     * @return array locale data
     */
    protected function _getTableHeader()
    {
        return [
            'country'           => $this->__("Country"),
            'purchase_currency' => $this->__("Purchase Currency"),
            'locale'            => $this->__("Locale"),
        ];
    }

    /**
     * Rearranges all the option values to be used as in table.
     *
     * @return array locale data
     */
    protected function _getPrepareArrayForTable()
    {
        $element = $this->getElement();
        $tableValues = [];
        foreach ((array)$element->getValues() as $value) {
            $info = unserialize($value['value']);
            $tableValues[] = [
                'country'           => $value['label'],
                'purchase_currency' => $info['purchase_currency'],
                'locale'            => $info['locale'],
            ];
        }

        return $tableValues;
    }
}
