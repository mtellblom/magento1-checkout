<?php

/**
 * Queue resource collection model.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Resource_Queue_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Constructor method.
     */
    protected function _construct()
    {
        $this->_init('sveacheckout/queue');
    }
}
