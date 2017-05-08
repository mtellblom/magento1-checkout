<?php

/**
 * Adminhtml payment block handler.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Block_Adminhtml_Info_Payment
    extends Mage_Payment_Block_Info
{
    /**
     * Sets payment information template.
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sveacheckout/payment/info/default.phtml');
    }

    /**
     * Load the template for output in admin html as payment information.
     *
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation()
    {
        if ($this->_paymentSpecificInformation !== null) {

            return $this->_paymentSpecificInformation;
        }

        $transport   = new Varien_Object();
        $transport   = parent::_prepareSpecificInformation($transport);
        $info        = $this->getInfo();
        $paymentData = $info->getAdditionalInformation();

        $labels = [
            'reference'    => 'Svea Order Id',
            'reference2'   => 'Our reference',
            'method'       => 'Payment Method',
            'ssn'          => 'Social Security Number',
            'mode'         => 'Mode',
        ];
        $paths = [
            'reservation/OrderId'             => 'reference',
            'reservation/ClientOrderNumber'   => 'reference2',
            'reservation/PaymentType'         => 'method',
            'reservation/Customer/NationalId' => 'ssn',
            'mode'                            => 'mode',
        ];

        if (!sizeof($paymentData)) {
            $paths = [
                'OrderId'             => 'reference',
                'ClientOrderNumber'   => 'reference2',
                'PaymentType'         => 'method',
                'Customer-NationalId' => 'ssn',
                'mode'                => 'mode',
            ];

            $transaction = $info->getAuthorizationTransaction();
            if (!$transaction && $transaction = $info->getTransaction($info->getLastTransId())) {
                $paymentData = $transaction
                    ->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
            }
        }

        $paymentData['mode'] = $this->_getOrderModeHtml();
        $filteredData        = $this->_getFilteredData($paymentData, $paths);

        foreach ($filteredData as $labelKey => $data) {
            $title = Mage::helper('sveacheckout')->__($labels[$labelKey]);
            $this->_addTransportInformation($transport, $title, $data);
        }

        return $transport;
    }

    /**
     * Returns the mode the order was placed under.
     *
     * @return string|bool=false
     */
    protected function _getOrderModeHtml()
    {
        $addInfo = $this->getInfo()->getAdditionalInformation();
        if (isset($addInfo['sveacheckout'])) {
            $sveaCheckout = new Varien_Object($addInfo['sveacheckout']);
            $messageColor = ($sveaCheckout->getMode() == 'test')
                          ? 'red'
                          : 'green';

            if ($sveaCheckout->getMode()) {
                return sprintf(
                    "<strong style='color: $messageColor; text-transform:uppercase;'>%s</strong>",
                    $sveaCheckout->getMode()
                );
            }
        }

        return false;
    }

    /**
     * Extract wanted data from paymentData.
     *
     * @param  array $paymentData
     * @param  array $paths
     *
     * @return array
     */
    protected function _getFilteredData($paymentData, $paths)
    {
        $filteredData = [];
        $paymentData = new Varien_Object($paymentData);

        foreach ($paths as $xPath => $arrayKey) {
            $filteredData[$arrayKey] = $paymentData->getData($xPath);
        }

        return $filteredData;
    }

    /**
     * Adds information to the transport information object.
     *
     * @param Varien_Object            $transport
     * @param string|null   (optional) $label       Label.
     * @param string|null   (optional) $value       Corresponding value along with the label.
     */
    protected function _addTransportInformation($transport, $label = "", $value = "")
    {
        $transport->addData(
            [
                $label => $value,
            ]
        );
    }
}
