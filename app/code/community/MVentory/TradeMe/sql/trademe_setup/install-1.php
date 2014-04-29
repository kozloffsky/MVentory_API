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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

$tableName = 'trademe/matching_rules';

$this->startSetup();

$connection = $this->getConnection();

$table = $connection
  ->newTable($this->getTable($tableName))
  ->addColumn(
      'id',
      Varien_Db_Ddl_Table::TYPE_INTEGER,
      null,
      array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
      ),
      'Primary key'
    )
  ->addColumn(
      'attribute_set_id',
      Varien_Db_Ddl_Table::TYPE_SMALLINT,
      null,
      array(
        'unsigned' => true,
        'nullable' => false,
        'default' => '0'
      ),
      'Attribute Set ID'
    )
  ->addColumn(
      'rules',
      Varien_Db_Ddl_Table::TYPE_TEXT,
      null,
      array(
        'nullable' => true,
      ),
      'Matching rule data in JSON format'
    )
  ->addIndex(
      $this->getIdxName($tableName, array('attribute_set_id')),
      array('attribute_set_id')
    )
  ->addForeignKey(
      $this->getFkName(
        $tableName,
        'attribute_set_id',
        'eav/attribute_set',
        'attribute_set_id'
      ),
      'attribute_set_id',
      $this->getTable('eav/attribute_set'),
      'attribute_set_id',
      Varien_Db_Ddl_Table::ACTION_CASCADE,
      Varien_Db_Ddl_Table::ACTION_CASCADE
    )
  ->setComment('TradeMe matching rules');

$connection->createTable($table);

$this->endSetup();
