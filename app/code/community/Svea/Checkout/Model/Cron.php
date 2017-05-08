<?php

/**
 * Svea cron model. Removes finished and old queue rows.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Cron
    extends Mage_Core_Model_Abstract
{
    /**
     * Cronjob triggers. Looks for items to handle.
     * Will simulate pushes for orders and/or trigger delete old/error/created queue items.
     *
     */
    static public function run()
    {
        $queueItems = Mage::getResourceModel('sveacheckout/queue_collection');

        foreach ($queueItems as $item) {
            $itemDate        = strtotime($item->getData('STAMP_CR_DATE'));
            $deleteNewLimit  = strtotime('+2 days', $itemDate);
            $deleteOldLimit  = strtotime('+1 month', $itemDate);

            if ($deleteNewLimit <= time()) {
                self::deleteNewAndFinished($item);
            }

            if ($deleteOldLimit <= time()) {
                self::deleteOldAndErrors($item);
            }
        }
    }

    /**
     * Deletes an item from queue, if state matches.
     *
     * @param $item
     */
    protected function deleteNewAndFinished($item)
    {
        $queueModel = Mage::getModel('sveacheckout/Queue');

        $deleteItemsWithState = [
            $queueModel::SVEA_QUEUE_STATE_INIT,
            $queueModel::SVEA_QUEUE_STATE_OK
        ];

        if (in_array((int)$item->getState(), $deleteItemsWithState)) {
            $queueModel->setQueueId($item->getQueueId())->delete();
        }
    }

    /**
     * Deletes an item from queue, if state matches.
     *
     * @param $item
     */
    protected function deleteOldAndErrors($item)
    {
        $queueModel = Mage::getModel('sveacheckout/queue');

        $deleteItemsWithState = [
            $queueModel::SVEA_QUEUE_STATE_WAIT,
            $queueModel::SVEA_QUEUE_STATE_NEW,
            $queueModel::SVEA_QUEUE_STATE_ERR
        ];

        if (in_array((int)$item->getState(), $deleteItemsWithState)) {
            $queueModel->setQueueId($item->getQueueId())->delete();
        }
    }
}
