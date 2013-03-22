<?php

$this->startSetup();

$tableName = 'mventory_tm/matching_rules';

$idxName = $this->getIdxName($tableName, array('attribute_set_id'));

$fkName = $this->getFkName($tableName,
                           'attribute_set_id',
                           'eav/attribute_set',
                           'attribute_set_id');

$connection = $this->getConnection();

$table = $connection
           ->newTable($this->getTable($tableName))
           ->addColumn('id',
                       Varien_Db_Ddl_Table::TYPE_INTEGER,
                       null,
                       array(
                         'identity' => true,
                         'unsigned' => true,
                         'nullable' => false,
                         'primary' => true,
                       ),
                       'Primary key')
           ->addColumn('attribute_set_id',
                       Varien_Db_Ddl_Table::TYPE_SMALLINT,
                       null,
                       array(
                         'unsigned' => true,
                         'nullable' => false,
                         'default' => '0'
                       ),
                       'Attribute Set ID')
           ->addColumn('rules',
                       Varien_Db_Ddl_Table::TYPE_TEXT,
                       null,
                       array(
                         'nullable' => true,
                       ),
                       'Rule data in JSON')
           ->addIndex($idxName, array('attribute_set_id'))
           ->addForeignKey($fkName,
                           'attribute_set_id',
                           $this->getTable('eav/attribute_set'),
                           'attribute_set_id',
                           Varien_Db_Ddl_Table::ACTION_CASCADE,
                           Varien_Db_Ddl_Table::ACTION_CASCADE)
           ->setComment('Matching rules');

$connection->createTable($table);

$this->endSetup();
