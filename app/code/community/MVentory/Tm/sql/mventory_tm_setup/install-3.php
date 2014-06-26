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

$this->startSetup();

$table = $this
           ->getConnection()
           ->newTable($this->getTable('mventory/order_transaction'))
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
           ->addIndex($this->getIdxName('mventory/order_transaction',
                                        array('transaction_id')),
                      array('transaction_id'))
           ->setComment('Order transaction table');

$this->getConnection()->createTable($table);

$this->endSetup();
