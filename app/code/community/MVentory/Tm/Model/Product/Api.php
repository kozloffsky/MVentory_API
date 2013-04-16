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

  const FETCH_LIMIT_PATH = 'mventory_tm/api/products-number-to-fetch';
  const TAX_CLASS_PATH = 'mventory_tm/api/tax_class';

  public function fullInfo ($id = null, $sku = null, $searchByBarcode = false) {
    if (! $id)
      $id = Mage::getResourceModel('catalog/product')->getIdBySku($sku);

    $id = (int) $id;

    if (!Mage::getModel('catalog/product')->load($id)->getId()) {
      if ($searchByBarcode)
      {
        $barCode = $sku;

        $currentStoreId = Mage::helper('mventory_tm')->getCurrentStoreId();

        $collection = Mage::getModel('catalog/product')
                      ->getCollection()
                      ->addStoreFilter($currentStoreId);

        $collection->addAttributeToFilter(
          array(
              array('attribute'=> 'product_barcode_','eq' => $barCode))
        );
        
        if ($collection->count() > 0)
        {
          $id = $collection->getFirstItem()->getId();
        }
      }
    }

    if (!($id > 0))
      $this->_fault('product_not_exists');

    $helper = Mage::helper('mventory_tm/tm');

    $website = $helper->getWebsite($id);
    $storeId = $website
                 ->getDefaultStore()
                 ->getId();

    $result = $this->info($id, $storeId, null, 'id');

    $stockItem = Mage::getModel('mventory_tm/stock_item_api');

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

    $baseUrlPath = Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL;

    $mediaPath = Mage::getStoreConfig($baseUrlPath, $storeId)
                 . 'media/'
                 . Mage::getSingleton('catalog/product_media_config')
                     ->getBaseMediaUrlAddition();

    $images = $productAttributeMedia->items($id, $storeId, 'id');

    foreach ($images as &$image)
      $image['url'] = $mediaPath . $image['file'];

    $result['images'] = $images;

    $category = Mage::getModel('catalog/category_api');

    foreach ($result['categories'] as $i => $categoryId)
      $result['categories'][$i] = $category->info($categoryId, $storeId);

    //TM specific details start here

    $tmAccountId = isset($result['tm_account_id'])
                     ? $result['tm_account_id']
                       : null;

    $tmAccounts = $helper->getAccounts($website);

    if (!$tmAccountId)
      $tmAccountId = key($tmAccounts);

    $tmOptions = array(
      'allow_buy_now' => $tmAccounts[$tmAccountId]['allow_buy_now'],
      'add_tm_fees' => $tmAccounts[$tmAccountId]['add_fees'],
      'shipping_type' => $tmAccounts[$tmAccountId]['shipping_type'],
      'relist' => $tmAccounts[$tmAccountId]['relist'],
      'preselected_categories' => null
    );

    if ($listingId = Mage::helper('mventory_tm/product')->getListingId($id))
      $tmOptions['tm_listing_id'] = $listingId;

    $shippingTypes
      = Mage::getModel('mventory_tm/system_config_source_shippingtype')
        ->toOptionArray();

    $tmOptions['shipping_types_list'] = $shippingTypes;

    $tmOptions['tm_accounts'] = array();

    foreach ($tmAccounts as $id => $account)
      $tmOptions['tm_accounts'][$id] = $account['name'];

    $product = Mage::getModel('catalog/product')->load($result['product_id']);

    $matchResult = Mage::getModel('mventory_tm/rules')
                     ->matchTmCategory($product );

    if (isset($matchResult['id']) && $matchResult['id'] > 0)
      $tmOptions['preselected_categories'][$matchResult['id']]
        = $matchResult['category'];

    $result['tm_options'] = $tmOptions;

    //Add shipping rate if product's shipping type is 'tab_ShipTransport'
    if (isset($result['mv_shipping_'])) {
      $do = false;

      //Iterate over all attributes...
      foreach ($result['set_attributes'] as $attribute)
        //... to find attribute with shipping type info, then...
        if ($attribute['attribute_code'] == 'mv_shipping_')
          //... iterate over all its options...
          foreach ($attribute['options'] as $option)
            //... to find option with same value as in product and with
            //label equals 'tab_ShipTransport'
            if ($option['value'] == $result['mv_shipping_']
                && $do = ($option['label'] == 'tab_ShipTransport'))
              break 2;

      if ($do)
        $result['shipping_rate']
          = $helper->getShippingRate(new Varien_Object($result),
                                     $tmAccounts[$tmAccountId]['name'],
                                     $website);
    }

    return $result;
  }

  public function limitedList ($name = null, $categoryId = null, $page = 1) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $limit = (int) Mage::getStoreConfig(self::FETCH_LIMIT_PATH, $storeId);

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
    }

    if ($name)
    {
      if (ctype_digit((string)$name) && strlen((string)$name)>=5)
      {
        $collection->addAttributeToFilter(
          array(
              array('attribute'=> 'name','like' => "%{$name}%"),
              array('attribute'=> 'sku','like' => "%{$name}%"),
              array('attribute'=> 'product_barcode_','like' => "%{$name}%"))
        );
      }
      else
     {
        $collection->addAttributeToFilter(
          array(
              array('attribute'=> 'name','like' => "%{$name}%"),
              array('attribute'=> 'sku','like' => "%{$name}%"))
        );
      }
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

      $productData['website_ids'] = $helper->getWebsitesForProduct();

      //Set visibility to "Catalog, Search" value
      $productData['visibility'] = 4;

      //if (!isset($productData['tax_class_id']))
        $productData['tax_class_id']
          = (int) $helper->getConfig(self::TAX_CLASS_PATH,
                                     $helper->getCurrentWebsite());

      //Set storeId as null to save values of attributes in the default scope
      $id = $this->create($type, $set, $sku, $productData, null);
    }

    return $this->fullInfo($id);
  }
  
  public function duplicateAndReturnInfo ($skuToDuplicate, $skuNew, $productData, $imagesCopyMode, $decreaseOriginalQTYBy) {

    $id = (int) Mage::getModel('catalog/product')
                  ->getResource()
                  ->getIdBySku($skuNew);

    if (!$id) {
      $oldProduct = $this->_getProduct($skuToDuplicate, null, 'sku');

      if (!$id = $oldProduct->getId()) {
        $this->_fault('product_not_exists');
      }

      if ($decreaseOriginalQTYBy > 0) {
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($id);
        
        if (!is_null($stockItem->getId())) {
          $stockItem->setData('qty', $stockItem->getData('qty') - $decreaseOriginalQTYBy);
          $stockItem->save();
        }
      }

      $newProduct = $oldProduct->duplicate();

      if (!isset($productData['sku']))
        $productData['sku'] = $skuNew;

      $this->update($newProduct->getId(), $productData);

      $images_api = Mage::getModel('catalog/product_attribute_media_api');

      $items_old = $images_api->items($id);
      $items_new = $images_api->items($newProduct->getId());

      for($pos=0; $pos<count($items_old); $pos++) {

        if ($pos >= count($items_new))
          break;

        $item_old = $items_old[$pos];
        $item_new = $items_new[$pos];

        if ((strcmp($imagesCopyMode, 'none') == 0) ||
          ((strcmp($imagesCopyMode, 'main') == 0) && (!in_array('image', $item_old['types'])))) {
          $images_api->remove($newProduct->getId(), $item_new['file']);
        } else {
          if (isset($item_old['types'])) {
            $images_api->update($newProduct->getId(), $item_new['file'], array('types' => $item_old['types']));
          }
        }
      }

      $id = $newProduct->getId();
    }
    
    return $this->fullInfo($id);
  }

  /**
   * Get info about new products, sales and stock
   *
   * @return array
   */
  public function statistics () {
    $storeId    = Mage::helper('mventory_tm')->getCurrentStoreId();
    $store      = Mage::app()->getStore($storeId);

    $date       = new Zend_Date();

    $dayStart   = $date->toString('yyyy-MM-dd 00:00:00');

    $weekStart  = new Zend_Date($date->getTimestamp() - 7 * 24 * 3600);

    $monthStart = new Zend_Date($date->getTimestamp() - 30 * 24 * 3600);

    // Get Sales info
    $collection = Mage::getModel('sales/order')->getCollection();
    $collection
      ->getSelect()
      ->columns('SUM(grand_total) as sum');
    $collection
      ->addFieldToFilter('store_id', $storeId)
      ->addFieldToFilter('created_at', array(
        'from' => $dayStart));
    $daySales = trim($collection
                  ->load()
                  ->getFirstItem()
                  ->getData('sum'), 0);

    $collection = Mage::getModel('sales/order')->getCollection();
    $collection
      ->getSelect()
      ->columns('SUM(grand_total) as sum');
    $collection
      ->addFieldToFilter('store_id', $storeId)
      ->addFieldToFilter('created_at', array(
        'from' => $weekStart->toString('YYYY-MM-dd 00:00:00')));
    $weekSales = trim($collection
                   ->load()
                   ->getFirstItem()
                   ->getData('sum'), 0);

    $collection = Mage::getModel('sales/order')->getCollection();
    $collection
      ->getSelect()
      ->columns('SUM(grand_total) as sum');
    $collection
      ->addFieldToFilter('store_id', $storeId)
      ->addFieldToFilter('created_at', array(
        'from' => $monthStart->toString('YYYY-MM-dd 00:00:00')));
    $monthSales = trim($collection
                    ->load()
                    ->getFirstItem()
                    ->getData('sum'), 0);

    $collection = Mage::getModel('sales/order')->getCollection();
    $collection
      ->getSelect()
      ->columns('SUM(grand_total) as sum');
    $collection->addFieldToFilter('store_id', $storeId);
    $totalSales = trim($collection
                    ->load()
                    ->getFirstItem()
                    ->getData('sum'), 0);
    // End of Sales info

    // Get Stock info
    $collection = Mage::getModel('catalog/product')->getCollection();

    if (Mage::helper('catalog')->isModuleEnabled('Mage_CatalogInventory')) {
      $collection
        ->joinField('qty',
                    'cataloginventory/stock_item',
                    'qty', 'product_id=entity_id',
                    '{{table}}.stock_id=1 AND {{table}}.is_in_stock=1
                    AND {{table}}.manage_stock=1 AND {{table}}.qty>0', 'left');
    }
    if ($storeId) {
      //$collection->setStoreId($store->getId());
      $collection->addStoreFilter($store);

      $collection->joinAttribute(
        'price',
        'catalog_product/price',
        'entity_id',
        null,
        'left',
        $storeId
      );
    } else {
      $collection->addAttributeToSelect('price');
    }

    $collection
      ->getSelect()
      ->columns(array('COUNT(at_qty.qty) AS total_qty',
                      'SUM(at_qty.qty*at_price.value) AS total_value'));
    $result = $collection
                ->load()
                ->getFirstItem()
                ->getData();

    $totalStockQty = trim($result['total_qty'], 0);
    $totalStockValue = trim($result['total_value'], 0);
    // End of Stock info

    // Get Products info
    $collection = Mage::getModel('catalog/product')->getCollection();
    $collection
      ->getSelect()
      ->columns('COUNT(entity_id) as loaded');
    $collection
      ->addStoreFilter($store)
      ->addFieldToFilter('created_at', array(
        'from' => $dayStart));
    $dayLoaded = $collection
                   ->load()
                   ->getFirstItem()
                   ->getData('loaded');

    $collection = Mage::getModel('catalog/product')->getCollection();
    $collection
      ->getSelect()
      ->columns('COUNT(entity_id) as loaded');
    $collection
      ->addStoreFilter($store)
      ->addFieldToFilter('created_at', array(
        'from' => $weekStart->toString('YYYY-MM-dd 00:00:00')));
    $weekLoaded  = $collection
                     ->load()
                     ->getFirstItem()
                     ->getData('loaded');

    $collection = Mage::getModel('catalog/product')->getCollection();
    $collection
      ->getSelect()
      ->columns('COUNT(entity_id) as loaded');
    $collection
      ->addStoreFilter($store)
      ->addFieldToFilter('created_at', array(
        'from' => $monthStart->toString('YYYY-MM-dd 00:00:00')));
    $monthLoaded = $collection
                     ->load()
                     ->getFirstItem()
                     ->getData('loaded');
    // End of Products info

    return array('day_sales' => (double)$daySales,
                 'week_sales' => (double)$weekSales,
                 'month_sales' => (double)$monthSales,
                 'total_sales' => (double)$totalSales,
                 'total_stock_qty' => (double)$totalStockQty,
                 'total_stock_value' => (double)$totalStockValue,
                 'day_loaded' => (double)$dayLoaded,
                 'week_loaded' => (double)$weekLoaded,
                 'month_loaded' => (double)$monthLoaded);
  }

  public function submitToTM ($productId, $tmData) {
    $product = $this->_getProduct($productId, null, 'id');

    if (is_null($product->getId())) {
      $this->_fault('product_not_exists');
    }

    //Add temp workaround until the app won't be updated
    if (isset($tmData['add_tm_fees']))
      $tmData['add_fees'] = $tmData['add_tm_fees'];

    if (isset($tmData['tm_category_id']))
      $tmData['category'] = $tmData['tm_category_id'];

    $connector = Mage::getModel('mventory_tm/connector');

    $connectorResult = $connector->send($product, $tmData['category'], $tmData);

    if (is_int($connectorResult)) {
      $product
        ->setTmListingId($connectorResult)
        ->save();
    }

    $result = $this->fullInfo($productId, null);

    if (!is_int($connectorResult))
    {
      $result['tm_error'] = $connectorResult;
    }

    return $result;
  }

  /**
   * Delete product
   *
   * The method is redefined to prevent removing of products from Magento
   * via API. It disables a product and adds (DELETED) to its name
   *
   * @param int|string $productId (SKU or ID)
   * @param  string $identifierType
   *
   * @return boolean
   */
  public function delete ($productId, $identifierType = null) {
    $product = $this->_getProduct($productId, null, $identifierType);

    $name = $product->getName();

    if (substr($name, -strlen('(DELETED)')) != '(DELETED)')
      $name .= ' (DELETED)';

    try {
      $product
        ->setName($name)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
        ->save();
    } catch (Mage_Core_Exception $e) {
      $this->_fault('not_deleted', $e->getMessage());
    }

    return true;
  }

  /**
   * Return loaded product instance
   *
   * The function is redefined to check if api user has access to the product
   *
   * @param  int|string $productId (SKU or ID)
   * @param  int|string $store
   * @param  string $identifierType
   * @return Mage_Catalog_Model_Product
   */
  protected function _getProduct($productId, $store = null,
                                 $identifierType = null) {

    if (!Mage::helper('mventory_tm/product')
           ->hasApiUserAccess($productId, $identifierType))
      $this->_fault('access_denied');

    return parent::_getProduct($productId, $store, $identifierType);
  }
}
