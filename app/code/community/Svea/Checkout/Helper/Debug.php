<?php

/**
 * Helper class for debug mode.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Helper_Debug
    extends Mage_Core_Helper_Abstract
{
    /**
     * Adds a new log row in custom debug-log.
     *
     * @param  string  $string Message to be logged
     * @param  integer $sveaId
     * @param  string  $objectId
     * @param  array   $extra
     *
     * @return bool   if disabled return false
     */
    public function writeToLog($string, $sveaId = null, $objectId = null, $extra = [], $type = 'quote')
    {
        if ($this->_isEnabled()) {
            return false;
        }

        $params = [
            'session' => Mage::getSingleton("core/session")->getEncryptedSessionId(),
            'ip'      => Mage::helper('core/http')->getRemoteAddr(false),
        ];

        if ($sveaId) {
            $params['Svea id'] = $sveaId;
        }

        if ($objectId) {
            $params["{$type} id"] = $objectId;
        }

        $params = array_merge($params, $extra);

        $message = '';

        foreach ($params as $title => $msg) {
            $message .= " | " . strtoupper($title) . ": {$msg}";
        }

        $message .= " || {$string}";

        Mage::log($message, null, 'sveacheckout_debug.log');

        return true;
    }

    /**
     * Returns a boolean response for if the debug mode is enabled.
     *
     * @return boolean
     */
    protected function _isEnabled()
    {
        return Mage::helper('sveacheckout')
            ->getConfigData('sveacheckout/disable_logging');
    }

    /**
     * Returns the current run state.
     *
     * @return string
     */
    public function getCurrentRunState()
    {
        $mode = Mage::helper('sveacheckout')
            ->getConfigData('sveacheckout/testmode');

        if ($mode) {
            return 'test';
        }

        return 'production';
    }
}
