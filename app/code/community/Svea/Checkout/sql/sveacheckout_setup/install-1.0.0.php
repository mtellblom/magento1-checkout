<?php
$installer  = $this;
$installer->startSetup();
$connection = $installer->getConnection();

$queueTableName = $installer->getTable('sveacheckout/queue');
$queueTable     = $installer->getConnection()->newTable($queueTableName)
    ->addColumn(
        'queue_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
            'identity' => true,
        ],
        'Queue ID'
    )
    ->addColumn('quote_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
            'primary'  => false,
            'unique'   => true,
        ],
        'Quote ID')
    ->addColumn(
        'order_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => true,
            'primary'  => false,
            'unique'   => true,
            'default'  => null,
        ],
        'Order ID'
    )
    ->addColumn(
        'STAMP_DATE',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        [
            'nullable' => false,
            'default'  => '0000-00-00 00:00:00',
        ],
        'Stamp date'
    )
    ->addColumn(
        'STAMP_CR_DATE',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        [
            'nullable' => false,
            'default'  => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
        ],
        'Stamp create date'
    )
    ->addColumn(
        'push_response',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        '64k',
        [
            'nullable' => true,
        ],
        'Holds json response from Svea'
    )
    ->addColumn(
        'state',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        4,
        [
            'default'  => 1,
            'nullable' => false,
            'primary'  => false,
            'unsigned' => true,
        ],
        'State'
    )
    ->addForeignKey(
        $this->getFkName('sveacheckout/queue', 'quote_id', 'sales/quote', 'entity_id'),
        'quote_id',
        $this->getTable('sales/quote'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addForeignKey(
        $this->getFkName('sveacheckout/queue', 'quote_id', 'sales/order', 'entity_id'),
        'order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Store potential order references');

$installer->getConnection()->createTable($queueTable);
$installer->endSetup();
