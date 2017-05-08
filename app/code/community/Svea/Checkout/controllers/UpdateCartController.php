<?php
require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'OnepageController.php';

/**
 * Svea Checkout UpdateCartController.
 *
 * @package Svea_Checkout
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Svea_Checkout_UpdateCartController
    extends Mage_Checkout_OnepageController
{
    /**
     * Hold the status code for error request.
     *
     * @var integer
     */
    const SVEA_CHECKOUT_RESPONSE_ERROR    = 0;

    /**
     * Hold the status code for successful request.
     *
     * @var integer
     */
    const SVEA_CHECKOUT_RESPONSE_SUCCESS  = 1;

    /**
     * Hold the status code for redirect request.
     *
     * @var integer
     */
    const SVEA_CHECKOUT_RESPONSE_REDIRECT = 2;

    protected $_quote                     = null;

    /**
     * Hold boolean for if the layout has been updated in last request.
     *
     * @var boolean
     */
    protected $_layoutReloaded            = false;

    /**
     * Hold the current update type.
     *
     * @var string
     */
    protected $_updateType                = "unknown";

    /**
     * Holds the basic response template.
     *
     * @var array
     */
    protected $_responseMessage           = [
        'status'        => '',
        'status_code'   => '',
        'message_title' => '',
        'message'       => '',
    ];
    protected $_helperData                = null;

    /**
     * Checkout quote update handler action.
     *
     */
    public function updateCheckoutAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                'Unathorized access',
                'Unathorized access'
            )
                ->_sendResponse(401);

            return;
        }

        if (!$this->_getQuote() || !$this->_getQuote()->getId()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_REDIRECT,
                'Session expired',
                Mage::getUrl('checkout/cart')
            )->_sendResponse(303);
        } else {
            $this->_setUpdateType($this->getRequest()->getParam('updateName'));

            switch ($this->_getUpdateType()) {
                case 'cart':
                    $this->_updateQuoteCart();
                    break;
                case 'cart-remove':
                    $this->_removeQuoteCart();
                    break;
                case 'coupon':
                    $this->_updateQuoteCoupon();
                    break;
                case 'shipping':
                    $this->_updateQuoteShipping();
                    break;
                default:
                    $this->_addResponseMessage(
                        self::SVEA_CHECKOUT_RESPONSE_ERROR,
                        'Unable to handle the request',
                        'Unable to handle the request'
                    )->_sendResponse(400);
                    break;
            }
        }
    }

    /**
     * Adds a response message information.
     *
     * @param integer $status
     * @param string  $title
     * @param string  $message
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addResponseMessage($status, $title, $message)
    {
        $this->_responseMessage = array_merge(
            $this->_responseMessage,
            [
                'status'        => $status,
                'status_code'   => $this->_getUpdateType(),
                'message_title' => $title,
                'message'       => $message,
            ]
        );

        return $this;
    }

    /**
     * Returns the current update type from request.
     *
     */
    protected function _getUpdateType()
    {
        return $this->_updateType;
    }

    /**
     * Sets the current type to update.
     *
     * @param string $type
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _setUpdateType($type)
    {
        if ($type) {
            $this->_updateType = $type;
        }

        return $this;
    }

    /**
     * Save and update current quote after update of cart.
     *
     */
    protected function _updateQuoteCart()
    {
        foreach ((array)$this->_getResponseParamData() as $itemId => $data) {
            foreach ($this->_getQuote()->getAllItems() as $item) {
                if ($item->getItemId() == $itemId) {
                    if (isset($data['qty'])) {
                        $response = $item->setQty($data['qty']);
                    }
                    unset($data[$itemId]);
                }
            }
        }

        $result = $this->_saveQuoteUpdates();

        $this->_addShippingBlockToResponse();
        $this->_addCartBlockToResponse();
        $this->_addTotalsBlockToResponse();
        $this->_addSveaBlockToResponse();

        if (!$this->_getQuote()->validateMinimumAmount()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_REDIRECT,
                'Minimum amount',
                Mage::getUrl('checkout/cart')
            );
            $code = 303;
        } elseif ($result) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_SUCCESS,
                "Cart updated",
                "Successful cart update"
            );
            $code = 200;
        } else {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                "Cart updated unsuccessful",
                $this->_getQuoteErrors()
            );
            $this->_addAlertBlockToResponse();
            $code = 422;
        }

        $this->_sendResponse($code);
    }

    /**
     * Returns param data from the current update type param data.
     *
     * @return string
     */
    protected function _getResponseParamData()
    {
        if (!$this->_getUpdateType()) {
            return "";
        }

        return $this->getRequest()->getParam($this->_getUpdateType());
    }

    /**
     * Save current quote after update.
     *
     * @throws Mage_Core_Exception
     *
     * @return bool
     */
    protected function _saveQuoteUpdates()
    {
        $info = $this->_getCurrentOrderInformationFromQuote();

        if ($this->_getQuote()->hasDataChanges() && !$this->_getQuote()->getHasError()) {
            try {

                $defaultCountry = Mage::helper('core')->getDefaultCountry();
                $quote = $this->_getQuote();

                if (!$quote->getBillingAddress()->getCountryId()) {
                    $quote->getBillingAddress()
                        ->setCountryId($defaultCountry);
                }
                if (!$quote->getShippingAddress()->getCountryId()) {
                    $quote->getShippingAddress()
                        ->setCountryId($defaultCountry);
                }

                $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
                $this->_getQuote()->collectTotals();
                $this->_getQuote()->save();

            } catch (Exception $e) {
                Mage::throwException(
                    sprintf(
                        "Unable to save the quote. Message \n%s",
                        $e->getMessage()
                    )
                );

                return false;
            }
        } elseif ($this->_getQuote()->getHasError()) {

            return false;
        }

        return true;
    }

    /**
     * Returns the current Svea order information from customer quote.
     *
     * @return Varien_Object
     */
    protected function _getCurrentOrderInformationFromQuote()
    {
        $data = new Varien_Object;

        if ($this->_getQuote()) {
            $info = $this->_getQuote()
                ->getPayment()
                ->getAdditionalInformation();

            if (isset($info['sveacheckout'])) {
                $data->setData($info['sveacheckout']);

                return $data;
            }
        }

        return $data;
    }

    /**
     * Adds cart HTML block to response.
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addCartBlockToResponse()
    {
        $this->_addBlockToHtmlResponse(
            'cartBlock',
            $this->_getCartBlockHtml()
        );

        return $this;
    }

    /**
     * Adds specified block to HTML response.
     *
     * @param string $name
     * @param string $blockHtml
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addBlockToHtmlResponse($name, $blockHtml)
    {
        if (!isset($this->_responseMessage['html'])) {
            $this->_responseMessage['html'] = [];
        }

        $this->_responseMessage['html'] = array_merge(
            $this->_responseMessage['html'],
            [
                $name => $blockHtml,
            ]
        );

        return $this;
    }

    /**
     * Fetches block for cart HTML.
     *
     * @return string
     */
    protected function _getCartBlockHtml()
    {

        return $this->_getLayoutBlockHtml('checkout.cart');
    }

    /**
     * Retrieves data from core_config_data based on $key.
     *
     * @param  string $key
     *
     * @return string
     */
    protected function _getConfigData($key)
    {
        return $this->_getDataHelper()
            ->getConfigData($key);
    }

    /**
     * Returns the helper class for data function.
     *
     * @return Webbhuset_SveaCheckout_Helper_Data
     */
    protected function _getDataHelper()
    {
        if (!$this->_helperData) {
            $this->_helperData = Mage::helper('sveacheckout');
        }

        return $this->_helperData;
    }

    /**
     * Fetches custom block with specific name and output HTML.
     *
     * @return string
     */
    protected function _getLayoutBlockHtml($name)
    {
        if (!$name) {
            return "";
        }

        $block = $this->_getLayoutBlock($name);

        if ($block) {
            return $block->toHtml();
        }

        return "";
    }

    /**
     * Fetches custom block with specific name.
     *
     * @return Mage_Core_Block_Template
     */
    protected function _getLayoutBlock($name)
    {
        if (!$name) {
            return "";
        }

        $this->_reloadLayout();

        return $this->getLayout()->getBlock($name);
    }

    /**
     * Reloads the layout.
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _reloadLayout()
    {
        if ($this->_layoutReloaded) {
            return $this;
        }

        $this->loadLayout('sveacheckout_index_index', true, true);

        $this->_layoutReloaded = true;

        return $this;
    }

    /**
     * Adds totals HTML block to response.
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addTotalsBlockToResponse()
    {
        $this->_addBlockToHtmlResponse(
            'totalsBlock',
            $this->_getTotalsBlockHtml()
        );

        return $this;
    }

    /**
     * Fetches block HTML for quote totals.
     *
     * @return string
     */
    protected function _getTotalsBlockHtml()
    {
        return $this->_getLayoutBlockHtml('checkout.cart.totals');
    }

    /**
     * Returns string with all quote errors.
     *
     * @return string
     */
    protected function _getQuoteErrors()
    {
        $string = "";
        foreach ($this->_getQuote()->getErrors() as $item) {
            $string .= $item->getCode();
        }

        return $string;
    }

    /**
     * Adds alert message block to response depending on system configuration
     * setting.
     *
     * @param  string $extraString
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addAlertBlockToResponse($extraString = "")
    {
        if ($this->_getConfigData('sveacheckout_layout/alert_template')) {
            $this->_addBlockToHtmlResponse(
                'alertBlock',
                $this->_getLayoutBlock('checkout.alert.message')
                    ->setChild(
                        'content',
                        $this->getLayout()
                            ->createBlock('core/text')
                            ->setText($extraString . $this->_getQuoteErrors())
                    )->toHtml()
            );
        }

        return $this;
    }

    /**
     * Renders an JSON encoded string.
     * Outputs in code 200 by default.
     *
     * @param  integer $code
     */
    protected function _sendResponse($code = 200)
    {
        $message = $this->_responseMessage;

        if (is_array($message)) {
            $message = Mage::helper('core')->jsonEncode($message);
        }

        $this->getResponse()
            ->setHttpResponseCode($code)
            ->setHeader('Cache-Control', 'no-cache', true)
            ->setHeader('Content-type', 'application/json', true)
            ->setHeader('Last-Modified', date('r'));

        $this->getResponse()->clearBody();

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('core/text')
                ->setText($message)
                ->toHtml()
        );
    }

    /**
     * Save and update current quote after update of cart.
     *
     */
    protected function _removeQuoteCart()
    {
        $productId = $this->_getParamFromUrl(
            $this->getRequest()->getParam('url'),
            'id'
        );

        $this->_getQuote()->removeItem($productId);

        $result = $this->_saveQuoteUpdates();

        if ($this->_getQuote()->getItemsCount() >= 1) {
            $this->_addCartBlockToResponse();
            $this->_addTotalsBlockToResponse();
            $this->_addShippingBlockToResponse();
            $this->_addSveaBlockToResponse();

            if (!$this->_getQuote()->validateMinimumAmount()) {
                $this->_addResponseMessage(
                    self::SVEA_CHECKOUT_RESPONSE_REDIRECT,
                    'Minimum amount',
                    Mage::getUrl('checkout/cart')
                );
                $code = 303;
            } elseif ($result) {
                $this->_addResponseMessage(
                    self::SVEA_CHECKOUT_RESPONSE_SUCCESS,
                    "Cart updated",
                    "Successful cart update"
                );
                $code = 200;
            } else {
                $this->_addResponseMessage(
                    self::SVEA_CHECKOUT_RESPONSE_ERROR,
                    "Cart updated unsuccessful",
                    $this->_getQuoteErrors()
                );
                $this->_addAlertBlockToResponse();
                $code = 422;
            }
        } else {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_REDIRECT,
                'Cart empty',
                Mage::getUrl('checkout/cart')
            );
            $code = 303;
        }

        $this->_sendResponse($code);
    }

    /**
     * Returns params form URL.
     *
     * @param  string $url
     * @param  string $param
     *
     * @return string
     */
    protected function _getParamFromUrl($url, $param)
    {
        $paramsArr = (array)explode("/", trim(parse_url($url, PHP_URL_PATH), "/"));
        $key = array_search($param, (array)$paramsArr);

        if (!$paramsArr || $key == -1) {
            return "";
        }

        if (isset($paramsArr[$key + 1])) {
            return $paramsArr[$key + 1];
        }

        return "";
    }

    /**
     * Save and update current quote after adding coupon code.
     *
     * @throws Mage_Core_Exception
     */
    protected function _updateQuoteCoupon()
    {
        $couponCode = (string)$this->getRequest()->getParam('coupon_code');

        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }

        try {
            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode(
                strlen($couponCode) ? $couponCode : ''
            );

            $this->_saveQuoteUpdates();

            $this->_addTotalsBlockToResponse();
            $this->_addShippingBlockToResponse();
            $this->_addCouponBlockToResponse();
            $this->_addSveaBlockToResponse();

            if (strlen($couponCode)) {
                if (!$this->_getQuote()->validateMinimumAmount()) {
                    $this->_addResponseMessage(
                        self::SVEA_CHECKOUT_RESPONSE_REDIRECT,
                        'Minimum amount',
                        Mage::getUrl('checkout/cart')
                    );
                    $code = 303;
                } elseif ($couponCode == $this->_getQuote()->getCouponCode()) {
                    $this->_addResponseMessage(
                        self::SVEA_CHECKOUT_RESPONSE_SUCCESS,
                        "Cart updated",
                        "Successful cart update"
                    );
                    $code = 200;
                } else {
                    $this->_addResponseMessage(
                        self::SVEA_CHECKOUT_RESPONSE_ERROR,
                        "Cart updated unsuccessfully",
                        $this->__(
                            'Coupon code "%s" is not valid.',
                            Mage::helper('core')->htmlEscape($couponCode)
                        )
                    );
                    $this->_addAlertBlockToResponse(
                        $this->__(
                            'Coupon code "%s" is not valid.',
                            Mage::helper('core')->htmlEscape($couponCode)
                        )
                    );
                    $code = 422;
                }
            } else {
                $this->_addResponseMessage(
                    self::SVEA_CHECKOUT_RESPONSE_SUCCESS,
                    "Cart updated",
                    "Successful cart update"
                );
                $code = 200;
            }

        } catch (Mage_Core_Exception $e) {
            Mage::throwException(
                sprintf(
                    "Unable to save the quote. Message \n%s",
                    $e->getMessage()
                )
            );
        } catch (Exception $e) {
            Mage::throwException(
                sprintf(
                    "Unable to save the quote. Message \n%s",
                    $e->getMessage()
                )
            );
        }

        $this->_sendResponse($code);
    }

    /**
     * Adds shipping HTML block to response.
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addShippingBlockToResponse()
    {
        $this->_addBlockToHtmlResponse(
            'shippingBlock',
            $this->_getShippingBlockHtml()
        );

        return $this;
    }

    /**
     * Fetches block HTML for shipping methods.
     *
     * @return string
     */
    protected function _getShippingBlockHtml()
    {
        return $this->_getLayoutBlockHtml('checkout.shipping');
    }

    /**
     * Adds coupon HTML block to response.
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addCouponBlockToResponse()
    {
        $this->_addBlockToHtmlResponse(
            'couponBlock',
            $this->_getCouponBlockHtml()
        );

        return $this;
    }

    /**
     * Fetches block for coupon HTML.
     *
     * @return string
     */
    protected function _getCouponBlockHtml()
    {
        return $this->_getLayoutBlockHtml('checkout.cart.coupon');
    }

    /**
     * Save and update current quote after updating shipping method.
     *
     */
    protected function _updateQuoteShipping()
    {
        $data = $this->getRequest()->getParam('shipping_method', '');

        $result = $this->getOnepage()->saveShippingMethod($data);

        if (!$result) {
            Mage::dispatchEvent(
                'checkout_controller_onepage_save_shipping_method',
                [
                    'request' => $this->getRequest(),
                    'quote'   => $this->getOnepage()->getQuote()
                ]
            );

            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_SUCCESS,
                "Cart updated",
                "Successful cart update"
            );
            $code = 200;
        } else {
            $this->_addAlertBlockToResponse();

            if (isset($result['message'])) {
                $errorMessage = $result['message'];
            } else {
                $errorMessage = $this->_getQuoteErrors();
            }

            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                "Cart updated unsuccessfully",
                $errorMessage
            );
            $code = 422;
        }

        $this->_saveQuoteUpdates();

        $this->_addShippingBlockToResponse();
        $this->_addTotalsBlockToResponse();
        $this->_addSveaBlockToResponse();

        $this->_sendResponse($code);
    }

    /**
     * Adds Svea HTML block to response.
     *
     * @return Svea_Checkout_UpdateCartController
     */
    protected function _addSveaBlockToResponse()
    {
        $this->_addBlockToHtmlResponse(
            'snippet',
            $this->_getSveaCheckoutBlock()
        );

        return $this;
    }

    /**
     * Fetches block for Svea window.
     *
     * @return string
     */
    protected function _getSveaCheckoutBlock()
    {
        $buildOrderModel = Mage::getModel('sveacheckout/Checkout_Api_BuildOrder');
        $quote           = Mage::getSingleton('checkout/session')->getQuote();
        $sveaId          = (int)$quote->getPaymentReference();
        $sveaOrder       = $buildOrderModel->createSveaOrderFromQuote($quote);
        $snippet         = $sveaOrder->setCheckoutOrderId((int)$sveaId)->getOrder();
        $buildOrderModel->sveaOrderHasErrors($sveaOrder, $quote, $snippet);

        return $snippet['Gui']['Snippet'];
    }

    /**
     * Returns Current Svea Order Id to an Ajax Request.
     *
     */
    public function getSveaOrderIdAction()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                'Unauthorized access',
                'Unauthorized access'
            )
                ->_sendResponse(401);

            return;
        }
        $quote = $this->_getQuote();

        if (!$quote) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $this->setQuote($quote);
        }

        $sveaOrderId = $this->_quote->getPaymentReference();
        $this->_responseMessage = ['sveaOrderId' => $sveaOrderId];
        $this->_sendResponse();
    }

    /**
     * Returns the current quote.
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->_quote;
    }

    /**
     * Returns the current quote.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->setQuote(Mage::getSingleton('checkout/session')->getQuote());
        }

        if (!$this->_quote) {
            $this->setQuote($this->getOnepage()->getQuote());
        }

        return $this->getQuote();
    }

    /**
     * Quote setter.
     *
     * @param Mage $quote
     */
    public function setQuote($quote)
    {
        $this->_quote = $quote;
    }

    /**
     * Adds product with specified product id in the current quote.
     *
     * @param string $productId
     *
     */
    protected function _addProductToQuote($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);

        if ($product->getId()) {
            $this->_getQuote()->addProduct($product, new Varien_Object(["qty" => 1]));
        }

        $result = $this->_saveQuoteUpdates();

        $this->_addCartBlockToResponse();
        $this->_addTotalsBlockToResponse();
        $this->_addSveaBlockToResponse();

        if ($result && $product->getId()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_SUCCESS,
                "Cart updated",
                "Successful cart update"
            );
            $code = 200;
        } elseif ($this->_getQuote()->getHasError()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                "Cart updated unsuccessfully",
                $this->_getQuoteErrors()
            );
            $this->_addAlertBlockToResponse();
            $code = 422;
        } elseif (!$product->getId()) {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                "Cart updated unsuccessfully",
                'Unable to process the requested product'
            );
            $this->_addAlertBlockToResponse();
            $code = 422;
        } else {
            $this->_addResponseMessage(
                self::SVEA_CHECKOUT_RESPONSE_ERROR,
                "Cart updated unsuccessfully",
                'Unknown error'
            );
            $this->_addAlertBlockToResponse();
            $code = 422;
        }

        $this->_sendResponse($code);
    }

    /**
     * Fetches block HTML for alert methods.
     *
     * @return string
     */
    protected function _getAlertBlockHtml()
    {
        return $this->_getLayoutBlockHtml('checkout.alert.message');
    }
}
