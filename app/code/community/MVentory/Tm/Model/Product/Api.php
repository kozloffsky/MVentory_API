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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Catalog product api
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Model_Product_Api extends Mage_Catalog_Model_Product_Api {

  const FETCH_LIMIT_PATH = 'mventory_tm/api/products-number-to-fetch';
  const TAX_CLASS_PATH = 'mventory_tm/api/tax_class';

  const CONF_TYPE = Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE;

  protected $_excludeFromProduct = array(
    'type' => true,
    'type_id' => true,
    'old_id' => true,
    'news_from_date' => true,
    'news_to_date' => true,
    'country_of_manufacture' => true,
    'categories' => true,
    'required_options' => true,
    'has_options' => true,
    'image_label' => true,
    'small_image_label' => true,
    'thumbnail_label' => true,
    'group_price' => true,
    'tier_price' => true,
    'msrp_enabled' => true,
    'minimal_price' => true,
    'msrp_display_actual_price_type' => true,
    'msrp' => true,
    'enable_googlecheckout' => true,
    'meta_title' => true,
    'meta_keyword' => true,
    'meta_description' => true,
    'is_recurring' => true,
    'recurring_profile' => true,
    'custom_design' => true,
    'custom_design_from' => true,
    'custom_design_to' => true,
    'custom_layout_update' => true,
    'page_layout' => true,
    'options_container' => true,
    'gift_message_available' => true,
    'url_key' => true,
    'visibility' => true
  );

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

    $productId = $helper->getProductId($productId, $identifierType);

    if (!($productId && $helper->hasApiUserAccess($productId, 'id')))
      $this->_fault('product_not_exists');

    $website = Mage::helper('mventory_tm/product')->getWebsite($productId);
    $storeId = $website
                 ->getDefaultStore()
                 ->getId();

    $_result = $this->info($productId, $storeId, null, 'id');

    //Product's ID can be changed by '_getProduct()' function if original
    //product is configurable one
    $productId = $_result['product_id'];

    foreach ($_result as $key => $value) {
      if (isset($this->_excludeFromProduct[$key]))
        continue;

      $result[$key] = $value;
    }

    $stockItem = Mage::getModel('mventory_tm/stock_item_api');

    $_result = $stockItem->items($productId);

    if (isset($_result[0]))
      $result = array_merge($result, $_result[0]);

    $result['set_attributes']
      = Mage::getModel('mventory_tm/product_attribute_api')
          ->fullInfoList($result['set']);

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

     $helper = Mage::helper('mventory_tm/product_configurable');

    if ($siblingIds = $helper->getSiblingsIds($productId)) {
      foreach ($result['set_attributes'] as $attr)
        if ($attr['is_configurable'])
          break;

      $siblings = Mage::getResourceModel('catalog/product_collection')
                    ->addAttributeToSelect('price')
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect($attr['attribute_code'])
                    ->addIdFilter($siblingIds)
                    ->addStoreFilter($storeId)
                    ->setFlag('require_stock_items');

      foreach ($siblings as $sibling)
        $result['siblings'][] = array(
          'product_id' => $sibling->getId(),
          'sku' => $sibling->getSku(),
          'name' => $sibling->getName(),
          'price' => $sibling->getPrice(),
          'qty' => $sibling->getStockItem()->getQty(),
          $attr['attribute_code'] => $sibling->getData($attr['attribute_code'])
        );
    }

    $product = new Varien_Object($result);

    Mage::dispatchEvent(
      'mventory_api_product_info',
      array('product' => $product, 'website' => $website)
    );

    return $product->getData();
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

    if ($name) {
      $tmpl = '%' . $name . '%';

      //Use 'left' join to include products without record
      //for value of barcode attribute in DB
      $collection->addAttributeToFilter(
        array(
          array('attribute' => 'name', 'like' => $tmpl),
          array('attribute' => 'sku', 'like' => $tmpl),
          array('attribute' => 'product_barcode_', 'like' => $tmpl)
        ),
        null,
        'left'
      );
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

  public function createAndReturnInfo ($type, $set, $sku, $data,
                                       $storeId = null) {

    $helper = Mage::helper('mventory_tm/product');

    if (!$id = $helper->getProductId($sku, 'sku')) {
      $data['mv_created_userid'] = $helper->getApiUser()->getId();
      $data['website_ids'] = $helper->getWebsitesForProduct();

      $website = $helper->getCurrentWebsite();

      //Set visibility to website's default value
      $data['visibility'] = (int) $helper->getConfig(
        MVentory_Tm_Model_Config::_API_VISIBILITY,
        $website
      );

      $data['tax_class_id'] = (int) $helper->getConfig(
        self::TAX_CLASS_PATH,
        $website
      );

      //Use admin store ID to save values of attributes in the default scope
      $id = $this->create(
        $type,
        $set,
        $sku,
        $data,
        Mage_Core_Model_App::ADMIN_STORE_ID
      );
    }

    return $this->fullInfo($id, 'id');
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

    //Load images from the original product before duplicating
    //because the original one can be removed during duplication
    //if duplicated product is similar to it.
    $images = Mage::getModel('catalog/product_attribute_media_api');
    $oldImages = $images->items($oldId);

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

    //Set visibility to "Catalog, Search". By default all products are visible.
    //They will be hidden if configurable one is created.
    $data['visibility'] = 4;

    //Reset stock journal for the duplicate
    $data['mv_stock_journal'] = '';

    $new = $old
            ->setData('mventory_update_duplicate', $data)
            ->duplicate();

    $newId = $new->getId();

    if ($new->getData('mventory_assigned_to_configurable_after'))
      return $this->fullInfo($newId, 'id');

    unset($new);

    $mode = strtolower($mode);

    $old = $oldImages;
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

    return $this->fullInfo($newId, 'id');
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

    $helper = Mage::helper('mventory_tm/product');

    $productId = $helper->getProductId($productId, $identifierType);

    if (!$productId)
      $this->_fault('product_not_exists');

    $skus = isset($productData['additional_sku'])
              ? (array) $productData['additional_sku']
                : false;

    $removeSkus = isset($productData['stock_data']['qty'])
                  && $productData['stock_data']['qty'] == 0;

    if ($skus)
      unset($productData['stock_data']);

    //Use admin store ID to save values of attributes in the default scope
    $result = parent::update(
      $productId,
      $productData,
      Mage_Core_Model_App::ADMIN_STORE_ID,
      'id'
    );

    if (!$result)
      return $result;

    if ($removeSkus)
      Mage::getResourceModel('mventory_tm/sku')->removeByProductId($productId);

    if ($skus) {
      Mage::getResourceModel('mventory_tm/sku')->add(
        $skus,
        $productId,
        $helper->getCurrentWebsite()
      );

      $stock = Mage::getModel('cataloginventory/stock_item')
                 ->loadByProduct($productId);

      if ($stock->getId())
        $stock
          ->addQty(count($skus))
          ->save();
    }

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

    $productId = $helper->getProductId($productId, $identifierType);

    if (!($productId && $helper->hasApiUserAccess($productId, 'id')))
      $this->_fault('product_not_exists');

    $helper = Mage::helper('mventory_tm/product_configurable');

    //Load details of assigned product if the product is configurable
    if ($childrenIds = $helper->getChildrenIds($productId))
      $productId = current($childrenIds);

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
