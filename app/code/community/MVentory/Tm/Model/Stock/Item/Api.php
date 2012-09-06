<?php

/**
 * Catalog inventory api
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Stock_Item_Api
  extends Mage_CatalogInventory_Model_Stock_Item_Api {

  public function items ($productIds) {
    if (!is_array($productIds))
      $productIds = array($productIds);

    $product = Mage::getModel('catalog/product');

    foreach ($productIds as &$productId)
      if ($newId = $product->getIdBySku($productId))
        $productId = $newId;

    $collection = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->setFlag('require_stock_items', true)
                    ->addFieldToFilter('entity_id', array('in' => $productIds));

    $result = array();

    foreach ($collection as $product)
      if ($product->getStockItem())
        $result[] = array(
          'product_id' => $product->getId(),
          'sku' => $product->getSku(),
          'qty' => $product->getStockItem()->getQty(),
          'is_in_stock' => $product->getStockItem()->getIsInStock(),
          'manage_stock' => $product->getStockItem()->getManageStock()
        );

    return $result;
  }
}
