<?php

$this->startSetup();

$table = $this
           ->getConnection()
           ->newTable($this->getTable('mventory_tm/order_transaction'))
           ->addColumn('id',
                       Varien_Db_Ddl_Table::TYPE_INTEGER,
                       null,
                       array(
                         'identity' => true,
                         'unsigned' => true,
                         'nullable' => false,
                         'primary' => true,
                       ),
                       'ID')
           ->addColumn('transaction_id',
                       Varien_Db_Ddl_Table::TYPE_INTEGER,
                       null,
                       array(
                         'unsigned' => true,
                         'nullable' => false,
                       ),
                       'Transaction ID')
           ->addColumn('order_id',
                       Varien_Db_Ddl_Table::TYPE_INTEGER,
                       null,
                       array(
                         'unsigned' => true,
                         'nullable' => false,
                       ),
                       'Order ID')
           ->addIndex($this->getIdxName('mventory_tm/order_transaction',
                                        array('transaction_id')),
                      array('transaction_id'))
           ->setComment('Order transaction table');

$this->getConnection()->createTable($table);

$this->endSetup();
