/**
 * Custom Event constructor pollyfill
 */
(function () {
  function CustomEvent(event, params) {
    params = params || {bubbles: false, cancelable: false, detail: undefined};
    var evt = document.createEvent('CustomEvent');
    evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
    return evt;
  };

  CustomEvent.prototype = window.Event.prototype;

  window.CustomEvent = CustomEvent;
})();

/**
 * Js for updating information in the Svea Checkout
 */
var SveaCheckout = Class.create()
SveaCheckout.prototype = {

  /**
   * Initialize
   */
  initialize: function (options) {
    this.opt                = options || {};
    this.opt.loadingTimeout = this.opt.loadingTimeout || 3500;
    this.opt.postUrl        = this.opt.postUrl || '/sveacheckout/cart/updateCheckout/';
    this.opt.htmlAlert      = this.opt.htmlAlert || 0;
    this.opt.sveaDebug      = this.opt.sveaDebug || 0;
    this.opt.messageTimeout = this.opt.messageTimeout || 5000;
    this.opt.debug          = this.opt.debug || 0;
    this.opt.inCheckout     = this.opt.inCheckout || 0;

    if (this.opt.inCheckout) {
      this.registerCheckoutEvents();
      this.addCheckoutEventListener();
    }
  },

  /**
   * Debug Function
   */
  _debug: function (msg, lvl) {
    if (typeof lvl === 'undefined') {
      lvl = 0;
    }

    if (!this.opt.debug) {
      return;
    }

    if (typeof msg === 'string') {
      // Log strings
      if (lvl === 0) {
        // Success/Default log message
        console.log('%c' + msg, 'color:green;');
      } else {
        // Error Message
        console.log('%c' + msg, 'color:red;');
      }
    } else {
      // Log objects
      console.log(msg);
    }
  },

  /**
   * Send Update Request to update the checkout
   */
  doAjaxUpdate: function (formName, formData) {
    this._debug('Starting Ajax Update for ' + formName);

    formData.updateName = formName;

    /**
     * isAjax flag is added if data is not wrapped in a Ajax element.
     */
    if (typeof formData.ajax === 'undefined') {
      formData.isAjax = 1;
    }

    document.dispatchEvent(new CustomEvent("startingAjaxUpdate", {
      detail: {
        formName: formName,
        formData: formData
      },
      bubbles: true,
      cancelable: true
    }));

    var that = this;

    new Ajax.Request(this.opt.postUrl, {
      method: 'post',
      parameters: formData,
      onComplete: function (response) {
        if (response.status !== 404) {
          that.ajaxSuccess(response, true);
        } else {
          that.ajaxFail(response);
        }
      }
    });
  },

  /**
   * Process response data on successful AJAX request.
   */
  ajaxSuccess: function (response) {
    this._debug('Ajax Success');

    var respJson = JSON.parse(response.responseText);
    var html = respJson.html || {};
    this._debug(respJson);

    document.dispatchEvent(new CustomEvent("startingAjaxSuccess", {
      detail: {
        response: response,
        respJson: respJson,
        html: html
      },
      bubbles: true,
      cancelable: true
    }));
    this.handleResponse(respJson);
  },

  /**
   * Handle AJAX response data.
   */
  handleResponse: function (response) {
    document.dispatchEvent(new CustomEvent("startingAjaxHandleResponse", {
      detail: {
        response: response
      },
      bubbles: true,
      cancelable: true
    }));
  },

  /**
   * Process response data on faulty AJAX request.
   */
  ajaxFail: function (response) {
    alert('Something went wrong! Please contact site administrator to fix the problem.');

    document.dispatchEvent(new CustomEvent("startingAjaxFail", {
      detail: {
        response: response
      },
      bubbles: true,
      cancelable: true
    }));
  },

  /********************
   * CHECKOUT SPECIFIC
   ********************/

  /**
   * Register Event listeners.
   */
  registerCheckoutEvents: function () {
    var that = this;
    document.getElementById('sveacheckout-wrapper').addEventListener('click', function(event) {
      var el = event.target;
      /**
       * Update Cart
       */
      if (
        (el.name === 'update_cart_action' && el.value === 'update_qty')
        || (el.parentNode.name == 'update_cart_action' && el.parentNode.value == 'update_qty')
      ) {
        that.updateCart(event);
      }
      /**
       * Remove item in cart.
       **/
      if (el.hasClassName('btn-remove')) {
        that.removeItemInCart(event);
      }

      if (typeof el.up('.sveacheckout-coupon') != 'undefined') {
        if (el.tagName == 'BUTTON' || el.up(1).tagName == 'BUTTON') {
          that.updateCoupon(event);
        }
      }
    });

    /**
     * Shipping Method buttons
     */
    $('co-shipping-method-form').observe('change', this.updateShipping.bind(this));
    $('discount-coupon-form').observe('submit',    this.updateCoupon.bind(this));
  },

  /**
   * Update Cart
   */
  updateCart: function (event) {
    if (typeof event == 'undefined') {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    var formData = $('co-product-cart-form').serialize(true);

    this._debug('Update Cart');
    this._debug(formData);

    this.doAjaxUpdate('cart', formData);
  },

  /**
   * Remove item in cart
   */
  removeItemInCart: function (event) {
    if (typeof event == 'undefined') {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    var elem = $(event.target);
    var href = elem.href;

    this._debug('Remove item in cart');
    this._debug(href);

    var data = {
      'url': href
    };

    this.doAjaxUpdate('cart-remove', data);
  },

  /**
   * Update Coupon
   */
  updateCoupon: function (event) {
    if (typeof event == 'undefined') {
      return;
    }
    var formData = $('discount-coupon-form').serialize(true);

    event.preventDefault();
    event.stopPropagation();
    var elem = event.target;
    if (elem.hasAttribute('data-remove')) {
      formData.remove = 1;
    }


    this._debug('Update Coupon');
    this._debug(formData);

    this.doAjaxUpdate('coupon', formData);
  },

  /**
   * Update Shipping
   */
  updateShipping: function (event) {
    if (typeof event == 'undefined') {
      return;
    }
    event.preventDefault();
    event.stopPropagation();

    var elem = event.target;
    var formData = $('co-shipping-method-form').serialize(true);

    this._debug('Update Shipping');
    this._debug(formData);

    this.doAjaxUpdate('shipping', formData);
  },

  /**
   * Assign eventListeners.
   */
  addCheckoutEventListener: function () {
    /**
     * Listens to AJAX update request to update the checkout.
     *
     * @param  {[JSON]} event
     */
    document.addEventListener("startingAjaxUpdate", function (event) {
      $('sveacheckout-wrapper').addClassName('loading');
    }.bind(this));

    /**
     * Listens to success of AJAX request in checkout.
     *
     * @param  {[JSON]} event
     */
    document.addEventListener("startingAjaxSuccess", function (event) {
      var html = event.detail.html;

      if (typeof html.totalsBlock !== 'undefined' && typeof $('sveacheckout-totals') !== 'undefined') {
        $('sveacheckout-totals').update(html.totalsBlock);
      }
      if (typeof html.shippingBlock !== 'undefined' && $('co-shipping-method-form')) {
        $('co-shipping-method-form').update(html.shippingBlock);
      }
      if (typeof html.couponBlock !== 'undefined' && $('discount-coupon-form')) {
        $('discount-coupon-form').update(html.couponBlock);
      }
      if (typeof html.cartBlock !== 'undefined' && $('sveacheckout-cart') ) {
        $('sveacheckout-cart').update(html.cartBlock);
      }

      /**
       * Remove loading state
       */
      $('sveacheckout-wrapper').removeClassName('loading');
      if (typeof window.scoApi !== 'Undefined') {
        window.scoApi.setCheckoutEnabled(true);
      }
    }.bind(this));

    /**
     * Listens to handle AJAX response data. Output messages and such things ...
     *
     * @param  {[JSON]} event
     */
    document.addEventListener("startingAjaxHandleResponse", function (event) {
      var response = event.detail.response;

      /**
       * Append error message
       */
      if (response.status === 0) {
        if (this.opt.htmlAlert === 1 && (typeof response.html.alertBlock !== 'undefined')) {
          $('sveacheckout-message').update(response.html.alertBlock).show();
          Effect.ScrollTo('sveacheckout-message', {duration: '0.2', offset: 0});
          if (this.opt.messageTimeout !== 0) { // Don't hide if timeout is zero
            setTimeout(function () {
              $('sveacheckout-message').fade().addClassName('hide');
            }, this.opt.messageTimeout);
          }
        } else {
          $('sveacheckout-message').update(response.message_title + '<br/>' + response.message);
        }
      } else {
        $('sveacheckout-message').fade().addClassName('hide');
      }

      /**
       * Is the session has expired the customer will be redirected.
       */
      if (response.status === 2) {
        window.location.href = response.message;
      }

      switch (response.status_code) {
        case 'cart':
          if (response.status === 0) {
            this.showMessage(response.message_title + '<br/>' + response.message, 'error');
            $('sveacheckout-wrapper').addClassName('cart-error')
          } else {
            $('sveacheckout-wrapper').removeClassName('cart-error')
          }
          break;
        case 'coupon':
          if (response.status === 0) {
            this.showMessage(response.message_title + '<br/>' + response.message, 'error');
          }
          break;
        case 'shipping':
          if (response.status === 0) {
            this.showMessage(response.message_title + '<br/>' + response.message, 'error');
          }
          break;
        case 'cart-remove':
          break;
        default:
          break;
      }
    }.bind(this));

    /**
     * Process response data on faulty AJAX request.
     */
    document.addEventListener("startingAjaxFail", function (event) {
      $('sveacheckout-wrapper').removeClassName('loading');
      if (typeof window.scoApi !== 'Undefined') {
        window.scoApi.setCheckoutEnabled(true);
      }
    }.bind(this));
  },

  showMessage: function (message, type) {
    var messageBox = $('sveacheckout-message');
    messageBox.update(message);
    messageBox.removeClassName('hide');
    messageBox.setAttribute('data-style', type);
    messageBox.show();
    if (this.opt.messageTimeout !== 0) {
      setTimeout(function () {
        messageBox.fade().addClassName('hide');
      }, this.opt.messageTimeout);
    }
  }
};