<?php

/**
 * Source model to get an array of selectable customer groups for
 * access filtering.
 *
 * @package Webbhuset_SveaCheckout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_System_Source_Customer_Group_Multiselect
{
    /**
     * Customer groups options array.
     *
     * @var null|array
     */
    protected $_options;

    /**
     * Retrieve customer groups as array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('customer/group_collection')
                ->setRealGroupsFilter()
                ->loadData()
                ->toOptionArray();

            array_unshift(
                $this->_options,
                [
                    'value' => Mage_Customer_Model_Group::NOT_LOGGED_IN_ID,
                    'label' => Mage::helper('sveacheckout')->__('- Not logged in -'),
                ]
            );
        }

        return $this->_options;
    }
}
