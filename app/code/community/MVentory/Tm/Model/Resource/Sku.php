<?php

class MVentory_Tm_Model_Resource_Sku
  extends Mage_Core_Model_Resource_Db_Abstract {

  protected function _construct() {
    $this->_init('mventory_tm/additional_skus', 'id');
  }

  public function getProductId ($sku) {
    $adapter = $this->_getReadAdapter();

    $select = $adapter
                ->select()
                ->from($this->getMainTable(), array('product_id'))
                ->where('sku = :sku');

    $binds = array('sku' => $sku);

    return $adapter->fetchRow($select, $binds);
  }

  public function add ($skus, $productId) {
    $skus = (array) $skus;

    $adapter = $this->_getWriteAdapter();
    $table = $this->getMainTable();

    $skus = array_diff($skus, $this->has($skus));

    foreach ($skus as $sku) {
      $data = new Varien_Object(array(
        'sku' => $sku,
        'product_id' => $productId,
      ));

      $adapter->insert(
        $table,
        $this->_prepareDataForTable($data, $table)
      );
    }

    return $this;
  }

  public function has ($skus) {
    $skus = (array) $skus;

    $adapter = $this->_getReadAdapter();

    $select = $adapter
                ->select()
                ->from($this->getMainTable(), array('sku'))
                ->where('sku in (?)', $skus);

    return (array) $adapter->fetchCol($select);
  }

  public function removeByProductId ($id) {
    $this
      ->_getWriteAdapter()
      ->delete($this->getMainTable(), array('product_id = ?' => $id));
  }
}
