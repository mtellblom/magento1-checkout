Important! 

Your domain needs to be whitelisted in order to use the module, 

please contact Support-Webpay@sveaekonomi.se if you're unsure if you're whitelisted.

Requires 
* Magento 1.9 (May work in previous releases as well, but the module is developed and tested in 1.9.2/3)
* magento-hackaton-installer
* php 5.5 or higher
* curl
* mysql or mariadb
* Magento's cronjob running every now and then

**Installation**

* run `composer require sveaekonomi/magento1-checkout && composer install`

**Configuration**

* Disable any modules that overrides Magento's default checkout.
* Head over to payment methods, and  fill out form under the tab "Svea Ekonomi - Checkout".  
  
After installation the default checkout process will be overridden with Svea checkout.
