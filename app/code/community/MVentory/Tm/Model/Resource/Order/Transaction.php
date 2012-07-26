<?php

class MVentory_Tm_Model_Resource_Order_Transaction
  extends Mage_Core_Model_Resource_Db_Abstract {

  protected function _construct() {
    $this->_init('mventory_tm/order_transaction', 'id');
  }

  public function getOrderIdByTransaction ($transactionId) {
    return $this
             ->getReadConnection()
             ->fetchOne('select order_id from '
                        . $this->getMainTable()
                        . ' where transaction_id = ?', $transactionId);
  }
}
