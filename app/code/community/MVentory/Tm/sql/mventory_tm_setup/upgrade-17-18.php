<?php

$tableName = 'mventory_tm/carrier_volumerate';
$table = $this->getTable($tableName);

$connection = $this->getConnection();

$connection->addColumn(
  $table,
  'shipping_type',
  array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 255,
    'nullable' => false,
    'comment' => 'Product shipping type'
  )
);

$fields = array(
  'website_id',
  'dest_country_id',
  'dest_region_id',
  'dest_zip',
  'condition_name',
  'condition_value'
);

$idxType = Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE;
$idxName = $this->getIdxName($tableName, $fields, $idxType);

$connection->dropIndex($table, $idxName);

$fields = array(
  'website_id',
  'shipping_type',
  'dest_country_id',
  'dest_region_id',
  'dest_zip',
  'condition_name',
  'condition_value'
);

$idxName = $this->getIdxName($table, $fields, $idxType);

$connection->addIndex($table, $idxName, $fields, $idxType);
