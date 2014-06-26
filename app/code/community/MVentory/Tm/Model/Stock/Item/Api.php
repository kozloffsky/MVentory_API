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
 * Catalog inventory api
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Stock_Item_Api
  extends Mage_CatalogInventory_Model_Stock_Item_Api {

  public function items ($productIds) {
    if (!is_array($productIds))
      $productIds = array($productIds);

    $product = Mage::getModel('catalog/product');

    foreach ($productIds as &$productId)
      if ($newId = $product->getIdBySku($productId))
        $productId = $newId;

    $storeId = Mage::helper('mventory')->getCurrentStoreId();

    $collection = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->setFlag('require_stock_items', true)
                    ->addStoreFilter($storeId)
                    ->addFieldToFilter('entity_id', array('in' => $productIds));

    $result = array();

    foreach ($collection as $product)
      if ($product->getStockItem())
        $result[] = array(
          'product_id' => $product->getId(),
          'sku' => $product->getSku(),
          'qty' => $product->getStockItem()->getQty(),
          'is_in_stock' => $product->getStockItem()->getIsInStock(),
          'manage_stock' => $product->getStockItem()->getManageStock(),
          'is_qty_decimal' => $product->getStockItem()->getIsQtyDecimal()
        );

    return $result;
  }
}
