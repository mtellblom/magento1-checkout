<?php

$installer = new Mage_Sales_Model_Resource_Setup('core_setup');

/**
 * Add 'custom_attribute' attribute for entities
 */
$entities = ['quote', 'order'];
$options = [
    'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'  => '64K',
    'visible'  => false,
    'required' => false,
];

foreach ($entities as $entity) {
    $installer->addAttribute($entity, 'payment_information', $options);
}

$installer->endSetup();
