<?php

class MVentory_Tm_Model_Resource_Sku
  extends Mage_Core_Model_Resource_Db_Abstract {

  protected function _construct() {
    $this->_init('mventory_tm/additional_skus', 'id');
  }

  public function getProductId ($sku, $website) {
    if (!$websiteId = $website->getId())
      return null;

    $adapter = $this->_getReadAdapter();

    $select = $adapter
                ->select()
                ->from($this->getMainTable(), array('product_id'))
                ->where('sku = :sku')
                ->where('website_id = :website_id');

    $binds = array(
      'sku' => $sku,
      'website_id' => $websiteId
    );

    return (int) $adapter->fetchOne($select, $binds);
  }

  public function add ($skus, $productId, $website) {
    if (!$websiteId = $website->getId())
      return $this;

    $skus = (array) $skus;

    $adapter = $this->_getWriteAdapter();
    $table = $this->getMainTable();

    $skus = array_diff($skus, $this->has($skus, $website));

    foreach ($skus as $sku) {
      $data = new Varien_Object(array(
        'sku' => $sku,
        'product_id' => $productId,
        'website_id' => $websiteId
      ));

      $adapter->insert(
        $table,
        $this->_prepareDataForTable($data, $table)
      );
    }

    return $this;
  }

  public function has ($skus, $website) {
    if (!$websiteId = $website->getId())
      return $this;

    $skus = (array) $skus;

    $adapter = $this->_getReadAdapter();

    $select = $adapter
                ->select()
                ->from($this->getMainTable(), array('sku'))
                ->where('sku in (?)', $skus)
                ->where('website_id = :website_id');

    return (array) $adapter->fetchCol(
      $select,
      array('website_id' => $websiteId)
    );
  }

  public function removeByProductId ($id) {
    $this
      ->_getWriteAdapter()
      ->delete($this->getMainTable(), array('product_id = ?' => $id));
  }
}
