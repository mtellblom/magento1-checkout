<?php
use Svea\Checkout\transport\Connector;
use Svea\WebPay\Config\ConfigurationProvider;

/**
 * Class Svea_Checkout_Model_Checkout_Api_Configuration
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_Model_Checkout_Api_Configuration
    extends Mage_Core_Model_Abstract
    implements Svea\WebPay\Config\ConfigurationProvider
{
    /**
     * Gets API Endpoint URI.
     *
     * @param string $type one of { ConfigurationProvider::HOSTED_TYPE, ::INVOICE_TYPE, ::PAYMENTPLAN_TYPE,
     *                     ::HOSTED_ADMIN_TYPE, ::ADMIN_TYPE}
     *
     * @return string
     * @throws Exception
     */
    public function getEndPoint($type)
    {
        $testMode   = Mage::getStoreConfig('payment/SveaCheckout/testmode');
        $adminStore = (Mage::app()->getStore()->isAdmin() || Mage::getDesign()->getArea() == 'adminhtml');

        if ($type == 'CHECKOUT_ADMIN' || $adminStore) {
            return ($testMode)
                   ? Connector::TEST_ADMIN_BASE_URL
                   : Connector::PROD_ADMIN_BASE_URL;
        }

        return ($testMode)
               ? Connector::TEST_BASE_URL
               : Connector::PROD_BASE_URL;
    }

    /**
     * Fetch Checkout Merchant id, used for Checkout order type
     *
     * @param string|null $country
     *
     * @return string
     */
    public function getCheckoutMerchantId($country = NULL)
    {
        $encryptedMerchantId = Mage::getStoreConfig('payment/SveaCheckout/merchant_id');

        return $merchantId = Mage::helper('core')->decrypt($encryptedMerchantId);
    }

    /**
     * Fetches Checkout Secret word, used for Checkout order type.
     *
     * @param string|null $country
     *
     * @return string
     */
    public function getCheckoutSecret($country = NULL)
    {
        $encryptedSecret = Mage::getStoreConfig('payment/SveaCheckout/sharedsecret');

        return Mage::helper('core')->decrypt($encryptedSecret);
    }

    /**
     * Use this to provide information about your integration platform (i.e. Magento, OpenCart et al), that will be
     * sent to Svea with every service request. Should return a string. The information provided is sent as plain text
     * and should not include any confidential information.
     *
     * Uncomment this if you wish to provide this information from your Svea\WebPay\Config\ConfigurationProvider
     * implementation.
     *
     * @return string
     */
    public function getIntegrationPlatform()
    {
        return 'Magento' . Mage::getVersion();
    }

    /**
     * Use this to provide information about the company providing this particular integration (i.e. Svea Ekonomi, for
     * the Svea Opencart module, et al), that will be sent to Svea with every service request. Should return a string.
     * The information provided is sent as plain text and should not include any confidential information.
     *
     * Uncomment this if you wish to provide this information from your Svea\WebPay\Config\ConfigurationProvider
     * implementation.
     *
     * @return string
     */
    public function getIntegrationCompany()
    {
        return 'Webbhuset';
    }

    /**
     * Use this to provide information about the version of this particular integration integration platform (i.e.
     * 2.0.1 et al), that will be sent to Svea with every service request. Should return a string. The information
     * provided is sent as plain text and should not include any confidential information.
     *
     * Uncomment this if you wish to provide this information from your Svea\WebPay\Config\ConfigurationProvider
     * implementation.
     *
     * @return string
     */
    public function getIntegrationVersion()
    {
        return '1.0.0';
    }

    /**
     * Unused, gets Username.
     *
     * @param string $type one of { \Svea\WebPay\Config\ConfigurationProvider
     *                     ::HOSTED_TYPE,
     *                     ::INVOICE_TYPE,
     *                     ::PAYMENTPLAN_TYPE }
     * @param string $country
     *
     * @return string
     * @throws Exception
     */
    public function getUsername($type, $country)
    {

    }

    /**
     * Unused, normally fetches password.
     *
     * @param string $type one of { \Svea\WebPay\Config\ConfigurationProvider
     *                     ::HOSTED_TYPE,
     *                     ::INVOICE_TYPE,
     *                     ::PAYMENTPLAN_TYPE }
     * @param string $country
     *
     * @return string
     * @throws Exception
     */
    public function getPassword($type, $country)
    {

    }

    /**
     * Fetches ClientNumber
     *
     * @param string $type one of { \Svea\WebPay\Config\ConfigurationProvider
     *                     ::HOSTED_TYPE,
     *                     ::INVOICE_TYPE,
     *                     ::PAYMENTPLAN_TYPE }
     * @param string $country
     *
     * @return string
     * @throws Exception
     */
    public function getClientNumber($type, $country)
    {
        return $this->getCredentialsProperty('clientNumber', $type, $country);
    }

    /**
     * Fetches merchant ID
     *
     * @param string $type one of { \Svea\WebPay\Config\ConfigurationProvider
     *                     ::HOSTED_TYPE,
     *                     ::INVOICE_TYPE,
     *                     ::PAYMENTPLAN_TYPE }
     * @param string $country
     *
     * @return string
     * @throws Exception
     */
    public function getMerchantId($type, $country)
    {
        return $this->getCredentialsProperty('merchantId', $type, $country);
    }

    /**
     * Fetches secret.
     *
     * @param string $type one of { ConfigurationProvider
     *                     ::HOSTED_TYPE,
     *                     ::INVOICE_TYPE,
     *                     ::PAYMENTPLAN_TYPE }
     * @param string $country
     *
     * @return string
     * @throws Exception
     */
    public function getSecret($type, $country)
    {
        return $this->getCredentialsProperty('secret', $type, $country);
    }
}
