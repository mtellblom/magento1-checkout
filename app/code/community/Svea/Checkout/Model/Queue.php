<?php

/**
 * Svea checkout queue model.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Queue
    extends Mage_Core_Model_Abstract
{
    /**
     * @const int SVEA_QUEUE_STATE_INIT Customer has visited checkout, awaiting push.
     **/
    const SVEA_QUEUE_STATE_INIT = 1;

    /**
     * @const int SVEA_QUEUE_STATE_WAIT We've got push, but order not complete.
     **/
    const SVEA_QUEUE_STATE_WAIT = 2;

    /**
     * @const int SVEA_QUEUE_STATE_NEW  We got actual push.
     **/
    const SVEA_QUEUE_STATE_NEW  = 3;

    /**
     * @const int SVEA_QUEUE_STATE_OK   Order successfully created.
     **/
    const SVEA_QUEUE_STATE_OK   = 4;

    /**
     * @const int SVEA_QUEUE_STATE_ERR  Order creation failed.
     */
    const SVEA_QUEUE_STATE_ERR  = 5;
    
    /**
     * Constructor methods.
     *
     */
    public function _construct()
    {
        $this->_init('sveacheckout/queue', 'queue_id');
    }
}
