<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

$tableName = 'mventory/carrier_volumerate';
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
