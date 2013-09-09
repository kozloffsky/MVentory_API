<?php

class MVentory_Tm_Model_Resource_Cart_Item
  extends Mage_Core_Model_Resource_Db_Abstract {
  	
  protected function _construct() {
    $this->_init('mventory_tm/cart_item', 'transaction_id');
    $this->_isPkAutoIncrement = false;
  }

  public function getCart($deleteBeforeTimestamp, $storeId) {
  	$date = date('Y-m-d H:i:s', $deleteBeforeTimestamp);
  	$sql = 'call GetCart(\''. $date.'\', '.$storeId.')';
  	
    return $this->getReadConnection()->fetchAll($sql);
  }
}
