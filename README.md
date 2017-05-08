Requires 
* magento-hackaton-installer
* php 5.4 or higher
* curl
* mysql or mariadb
* Magento's cronjob running every now and then

**Installation**

* run `composer require sveaekonomi/magento1-checkout && composer install`

**Configuration**

* Disable any modules that overrides Magento's default checkout.
* Head over to payment methods, and  fill out form under the tab "Svea Ekonomi - Checkout".  
  
After installation the default checkout process will be overridden with Svea checkout.