<?php

$installer = new Mage_Sales_Model_Resource_Setup('core_setup');

/**
 * Add 'custom_attribute' attribute for entities
 */
$entities = ['quote', 'order'];
$options = [
    'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'visible'  => true,
    'required' => false,
];

foreach ($entities as $entity) {
    $installer->addAttribute($entity, 'payment_reference', $options);
}

// Required tables
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

// Insert statuses
$installer->getConnection()->insertArray(
    $statusTable,
    [
        'status',
        'label',
    ],
    [
        ['status' => 'sveacheckout_pending',      'label' => 'Svea Checkout new'],
        ['status' => 'sveacheckout_acknowledged', 'label' => 'Svea Checkout pending']
    ]
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
    $statusStateTable,
    [
        'status',
        'state',
        'is_default',
    ],
    [
        [
            'status'     => 'sveacheckout_pending',
            'state'      => 'new',
            'is_default' => 1,
        ],
        [
            'status'     => 'sveacheckout_acknowledged',
            'state'      => 'new',
            'is_default' => 0,
        ]
    ]
);
$installer->endSetup();
