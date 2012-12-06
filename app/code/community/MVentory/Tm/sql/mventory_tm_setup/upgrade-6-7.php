<?php

$this->startSetup();

$table = $this->getTable('mventory_tm/order_transaction');

$this
  ->getConnection()
  ->modifyColumn($table, 'transaction_id', 'DOUBLE UNSIGNED NOT NULL');

$this->endSetup();
