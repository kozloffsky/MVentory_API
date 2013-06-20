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

  public function fullInfo ($productId,
                            $identifierType = null,
                            $none = false) {

    //Support for not updated apps which requests product's info
    //by SKU or Barcode.
    //
    // * 1st param is null
    // * 2nd param contains SKU or barcode
    // * 3rd param shows if barcode is used
    if ($productId == null) {
      $productId = $identifierType;
      $identifierType = 'sku';
    }

    $helper = Mage::helper('mventory_tm/product');

    if (!$helper->hasApiUserAccess($productId, $identifierType))
      $this->_fault('access_denied');

    $productId = $helper->getProductId($productId, $identifierType);

    if (!$productId)
      $this->_fault('product_not_exists');

    $website = Mage::helper('mventory_tm/product')->getWebsite($productId);
    $storeId = $website
                 ->getDefaultStore()
                 ->getId();

    $result = $this->info($productId, $storeId, null, 'id');

    $stockItem = Mage::getModel('mventory_tm/stock_item_api');

    $_result = $stockItem->items($productId);

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

    $images = $productAttributeMedia->items($productId, $storeId, 'id');

    foreach ($images as &$image)
      $image['url'] = $mediaPath . $image['file'];

    $result['images'] = $images;

    $category = Mage::getModel('catalog/category_api');

    foreach ($result['categories'] as $i => $categoryId)
      $result['categories'][$i] = $category->info($categoryId, $storeId);

    //TM specific details start here

    $helper = Mage::helper('mventory_tm/tm');

    $tmAccounts = $helper->getAccounts($website);

    $tmAccountId = isset($result['tm_account_id'])
                     ? $result['tm_account_id']
                       : null;

    $tmAccount = $tmAccountId && isset($tmAccounts[$tmAccountId])
                   ? $tmAccounts[$tmAccountId]
                     : null;

    $tmOptions = Mage::helper('mventory_tm/product')
                    ->getTmFields($result, $tmAccount);

    //!!!FIXME: temp. workaround for the app
    $tmOptions['add_tm_fees'] = $tmOptions['add_fees'];

    if ($listingId = Mage::helper('mventory_tm/product')
                       ->getListingId($productId))
      $tmOptions['tm_listing_id'] = $listingId;

    $shippingTypes
      = Mage::getModel('mventory_tm/entity_attribute_source_freeshipping')
          ->getAllOptions();

    $tmOptions['shipping_types_list'] = $shippingTypes;

    $tmOptions['tm_accounts'] = array();

    foreach ($tmAccounts as $id => $account)
      $tmOptions['tm_accounts'][$id] = $account['name'];

    $tmOptions['preselected_categories'] = null;

    $product = Mage::getModel('catalog/product')->load($result['product_id']);

    $matchResult = Mage::getModel('mventory_tm/rules')
                     ->matchTmCategory($product );

    if (isset($matchResult['id']) && $matchResult['id'] > 0)
      $tmOptions['preselected_categories'][$matchResult['id']]
        = $matchResult['category'];

    $result['tm_options'] = $tmOptions;

    //Add shipping rate if product's shipping type is 'tab_ShipTransport'
    if (isset($result['mv_shipping_']) && $tmAccount) {
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
                                     $tmAccount['name'],
                                     $website);
    }

    $configurableType
      = Mage::getResourceSingleton('catalog/product_type_configurable');

    $parentIds = $configurableType->getParentIdsByChild($productId);

    if ($parentIds) {
      $childrenIds = array();

      foreach ($parentIds as $parentId) {
        $_childrenIds = $configurableType->getChildrenIds($parentId);

        $childrenIds += $_childrenIds[0];
      }

      unset($childrenIds[$productId]);

      $siblings = Mage::getResourceModel('catalog/product_collection')
                    ->addAttributeToSelect('price')
                    ->addAttributeToSelect('name')
                    ->addIdFilter($childrenIds)
                    ->addStoreFilter($storeId)
                    ->setFlag('require_stock_items');

      foreach ($siblings as $sibling)
        $result['siblings'][] = array(
          'product_id' => $sibling->getId(),
          'sku' => $sibling->getSku(),
          'name' => $sibling->getName(),
          'price' => $sibling->getPrice(),
          'qty' => $sibling->getStockItem()->getQty()
        );
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
      ->addAttributeToFilter(
          'type_id',
          Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
        )
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

    $id = Mage::helper('mventory_tm/product')->getProductId($sku, 'sku');

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
  
  public function duplicateAndReturnInfo ($oldSku,
                                          $newSku,
                                          $data = array(),
                                          $mode = 'all',
                                          $subtractQty = 0) {

    $newId = Mage::helper('mventory_tm/product')->getProductId($newSku, 'sku');

    if ($newId)
      return $this->fullInfo($newId, 'id');

    $old = $this->_getProduct($oldSku, null, 'sku');
    $oldId = $old->getId();

    $subtractQty = (int) $subtractQty;

    if ($subtractQty > 0) {
      $stock = Mage::getModel('cataloginventory/stock_item')
                 ->loadByProduct($oldId);
        
      if ($stock->getId())
        $stock
          ->subtractQty($subtractQty)
          ->save();

      unset($stock);
    }

    if (!isset($data['sku']))
      $data['sku'] = $newSku;

    //Reset stock journal for the duplicate
    $data['mv_stock_journal'] = '';

    $new = $old->duplicate();
    $newId = $new->getId();

    unset($old, $new);

    $this->update($newId, $data);

    $mode = strtolower($mode);

    $images = Mage::getModel('catalog/product_attribute_media_api');

    $old = $images->items($oldId);
    $new = $images->items($newId);

    $countOld = count($old);
    $countNew = count($new);

    for ($n = 0; $n < $countOld && $n < $countNew; $n++) {
      $file = $new[$n]['file'];

      if ($mode == 'none') {
        $images->remove($newId, $file);

        continue;
      }

      if (!isset($old[$n]['types'])) {
        if ($mode == 'main')
          $images->remove($newId, $file);

        continue;
      }

      $types = $old[$n]['types'];

      if ($mode == 'main' && !in_array('image', $types)) {
        $images->remove($newId, $file);

        continue;
      }
    
      $images->update($newId, $file, array('types' => $types));
    }

    return $this->fullInfo($newId);
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
        ->setTmCurrentListingId($connectorResult)
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
   * Update product data
   *
   * @param int|string $productId
   * @param array $productData
   * @param string|int $store
   * @return boolean
   */
  public function update ($productId,
                          $productData,
                          $store = null,
                          $identifierType = null) {

    $productId = Mage::helper('mventory_tm/product')
                   ->getProductId($productId, $identifierType);

    if (!$productId)
      $this->_fault('product_not_exists');

    $result = parent::update($productId, $productData, $store, 'id');

    if (!$result)
      return $result;

    if (isset($productData['stock_data']['qty'])
        && $productData['stock_data']['qty'] == 0)
      Mage::getResourceModel('mventory_tm/sku')->removeByProductId($productId);

    $skus = isset($productData['additional_sku'])
              ? (array) $productData['additional_sku']
                : false;

    if ($skus)
      Mage::getResourceModel('mventory_tm/sku')->add($skus, $productId);

    return $result;
  }

  /**
   * Return loaded product instance
   *
   * The function is redefined to check if api user has access to the product
   * and to load product but barcode or additional SKUs
   *
   * @param  int|string $productId (SKU or ID)
   * @param  int|string $store
   * @param  string $identifierType
   * @return Mage_Catalog_Model_Product
   */
  protected function _getProduct($productId, $store = null,
                                 $identifierType = null) {

    $helper = Mage::helper('mventory_tm/product');

    if (!$helper->hasApiUserAccess($productId, $identifierType))
      $this->_fault('access_denied');

    $productId = $helper->getProductId($productId, $identifierType);

    if (!$productId)
      $this->_fault('product_not_exists');

    $product = Mage::getModel('catalog/product')
                 ->setStoreId(Mage::app()->getStore($store)->getId())
                 ->load($productId);

    if (!$product->getId())
      $this->_fault('product_not_exists');

    return $product;
  }

  /**
   * Set additional data before product saved
   *
   * @param Mage_Catalog_Model_Product $product
   * @param array $productData
   */
  protected function _prepareDataForSave ($product, $productData) {
    parent::_prepareDataForSave($product, $productData);

    if (isset($productData['stock_data']['qty'])) {
      $qty = $this->_getStockJournalRecord($productData['stock_data']['qty']);

      $record = $product->getData('mv_stock_journal')
                . "\r\n"
                . $qty;

      $product->setData('mv_stock_journal', trim($record));
    }
  }

  protected function _getStockJournalRecord ($qty) {
    if (!$user = Mage::helper('mventory_tm')->getApiUser())
      return;

    $date = Mage::getModel('core/date')->date();

    return $qty . ', ' . $date . ', ' . $user->getId();
  }
}
