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
 */

/**
 * Resource model for additional SKUs and barcodes
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Resource_Sku
  extends Mage_Core_Model_Resource_Db_Abstract {

  protected function _construct() {
    $this->_init('mventory/additional_skus', 'id');
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

    $skus = array_diff($skus, $this->has($skus, $productId));

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

  public function has ($skus, $productId) {
    $skus = (array) $skus;

    $adapter = $this->_getReadAdapter();

    $select = $adapter
                ->select()
                ->from($this->getMainTable(), array('sku'))
                ->where('sku in (?)', $skus)
                ->where('product_id = :product_id');

    return (array) $adapter->fetchCol(
      $select,
      array('product_id' => $productId)
    );
  }

  public function get ($productId) {
    $adapter = $this->_getReadAdapter();

    $select = $adapter
      ->select()
      ->from($this->getMainTable(), array('sku'))
      ->where('product_id = :product_id');

    return (array) $adapter->fetchCol(
      $select,
      array('product_id' => $productId)
    );
  }

  public function removeByProductId ($id) {
    $this
      ->_getWriteAdapter()
      ->delete($this->getMainTable(), array('product_id = ?' => $id));
  }
}
