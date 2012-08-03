<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog product api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Api extends Mage_Catalog_Model_Product_Api {

  public function fullInfo ($id = null, $sku = null) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $product = Mage::getModel('catalog/product');

    if (! $id)
      $id = $product->getResource()->getIdBySku($sku);

    $id = (int) $id;

    $result = $this->info($id, $storeId, null, 'id');

    $stockItem = Mage::getModel('cataloginventory/stock_item_api');

    $_result = $stockItem->items($id);

    if (isset($_result[0]))
      $result = array_merge($result, $_result[0]);

    $productAttribute = Mage::getModel('catalog/product_attribute_api');

    $_result = $productAttribute->items($result['set']);

    $result['set_attributes'] = array();

    foreach ($_result as $_attr) {
      $attr = $productAttribute->info($_attr['attribute_id'], $storeId);

      $attr['options']
        = $productAttribute->options($attr['attribute_id'], $storeId);

      $result['set_attributes'][] = $attr;
    }

    $productAttributeMedia
      = Mage::getModel('catalog/product_attribute_media_api');

    $result['images'] = $productAttributeMedia->items($id, $storeId, 'id');

    $category = Mage::getModel('catalog/category_api');

    foreach ($result['categories'] as $i => $categoryId)
      $result['categories'][$i] = $category->info($categoryId, $storeId);

    return $result;
  }

  public function limitedList ($name = null, $categoryId = null, $page = 1) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $limit = (int) Mage::getStoreConfig(
                                    'mventory_tm/api/products-number-to-fetch');

    if ($categoryId) {
      $category = Mage::getModel('catalog/category')
                    ->setStoreId($storeId)
                    ->load($categoryId);

      if (!$category->getId())
        $this->_fault('category_not_exists');

      $collection = $category->getProductCollection();
    } else {
      $collection = Mage::getModel('catalog/product')
                      ->getCollection()
                      ->addStoreFilter($storeId);

      if ($name)
        $collection
          ->addFieldToFilter('name', array('like' => "%{$name}%"));
    }

    $collection
      ->addAttributeToSelect('name')
      ->setPage($page, $limit);

    if (!$name)
      $collection
        ->setOrder('updated_at', Varien_Data_Collection::SORT_ORDER_DESC);

    $result = array('items' => array());

    foreach ($collection as $product)
      $result['items'][] = array('product_id' => $product->getId(),
                                 'sku' => $product->getSku(),
                                 'name' => $product->getName(),
                                 'set' => $product->getAttributeSetId(),
                                 'type' => $product->getTypeId(),
                                 'category_ids' => $product->getCategoryIds() );

    $result['current_page'] = $collection->getCurPage();
    $result['last_page'] = (int) $collection->getLastPageNumber();

    return $result;
  }

  public function createAndReturnInfo ($type, $set, $sku, $productData,
                                   $storeId = null) {

    $id = (int) Mage::getModel('catalog/product')
                  ->getResource()
                  ->getIdBySku($sku);

    if (! $id) {
      $helper = Mage::helper('mventory_tm');

      $storeId = $helper->getCurrentStoreId($storeId);

      $productData['website_ids'] = $helper->getWebsitesForProduct($storeId);

      //Set visibility to "Catalog, Search" value
      $productData['visibility'] = 4;

      //Set storeId as null to save values of attributes in the default scope
      $id = $this->create($type, $set, $sku, $productData, null);
    }

    return $this->fullInfo($id);
  }
}
