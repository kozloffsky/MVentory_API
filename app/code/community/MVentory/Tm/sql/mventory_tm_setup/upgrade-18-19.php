<?php

$this->startSetup();

$tableName = 'mventory_tm/additional_skus';

$idxName = $this->getIdxName(
             $tableName,
             array('sku'),
             Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
           );

$fkName = $this->getFkName(
            $tableName,
            'product_id',
            'catalog/product',
            'entity_id'
          );

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
               'sku',
               Varien_Db_Ddl_Table::TYPE_TEXT,
               64,
               array(
                 'nullable' => false,
               ),
               'Additional SKU'
             )
           ->addColumn(
               'product_id',
               Varien_Db_Ddl_Table::TYPE_INTEGER,
               null,
               array(
                 'unsigned'  => true,
                 'nullable'  => false,
               ),
               'Product ID'
             )
           ->addColumn(
               'created_at',
               Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
               null,
               array(
                 'nullable' => false,
                 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT
               ),
               'Creation Time'
             )
           ->addIndex(
               $idxName,
               array('sku'),
               array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
             )
           ->addForeignKey(
               $fkName,
               'product_id',
               $this->getTable('catalog/product'),
               'entity_id',
               Varien_Db_Ddl_Table::ACTION_CASCADE,
               Varien_Db_Ddl_Table::ACTION_CASCADE
             )
           ->setComment('Additional Product SKUs');

$connection->createTable($table);

$this->endSetup();
