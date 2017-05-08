<?php

/**
 * Svea Checkout admin queue controller.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Adminhtml_QueueController
    extends Mage_Adminhtml_Controller_Action
{
    /**
     * Index endpoint, serves svea checkout queue admin block.
     *
     */
    public function indexAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sveacheckout')
            ->renderLayout();
    }
}
