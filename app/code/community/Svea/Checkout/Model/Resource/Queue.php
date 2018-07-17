<?php

/**
 * Resource queue model.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Resource_Queue
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Constructor.
     *
     */
    protected function _construct()
    {
        $this->_init('sveacheckout/queue', 'queue_id');
    }

    /**
     * _prepareDataForSave
     *
     * @param Svea_Checkout_Model_Queue $object
     *
     * @return array
     */
    protected function _prepareDataForSave(Mage_Core_Model_Abstract $object) {
        $sveaOrder = $object->getData('push_response');
        $object->setData('state', $this->_prepareState($object));
        $object->setData('STAMP_DATE', now());
        if(is_array($sveaOrder) || is_object($sveaOrder)) {
            $sveaOrder = json_encode($sveaOrder);
        }
        $object->setData('push_response', ($sveaOrder));
        $data = parent::_prepareDataForSave($object);

        return $data;
    }

    /**
     * _prepareState
     *
     * @param Svea_Checkout_Model_Queue $object
     *
     * @return int
     */
    protected function _prepareState($object) {
        $newState = (int)$object->getData('state');
        $oldState = (int)$object->getOrigData('state');

        if (!$newState) {

            return $object::SVEA_QUEUE_STATE_INIT;
        }

        if ($newState > $oldState && $oldState !== $object::SVEA_QUEUE_STATE_ERR) {

            return $newState;
        } else {

            return $oldState;
        }
    }
}
