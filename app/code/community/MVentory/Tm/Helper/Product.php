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
 * Product helper
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Helper_Product extends MVentory_Tm_Helper_Data {

  const ROOT_WEBSITE_PATH = 'mventory_tm/api/root_website';

  protected $_tmFields = array(
    'account_id' => 'tm_account_id',
    'shipping_type' => 'tm_shipping_type',
    'allow_buy_now' => 'tm_allow_buy_now',
    'add_fees' => 'tm_add_fees',
    'avoid_withdrawal' => 'tm_avoid_withdrawal',
  );

  protected $_tmFieldsWithoutDefaults = array(
    'relist' => 'tm_relist',
    'pickup' => 'tm_pickup'
  );

  /**
   * Returns product's category
   *
   * @param Mage_Catalog_Model_Product $product
   *
   * @return Mage_Catalog_Model_Category
   */
  public function getCategory ($product) {
    $categories = $product->getCategoryIds();

    if (!count($categories))
      return null;

    $category = Mage::getModel('catalog/category')->load($categories[0]);

    if ($category->getId())
      return $category;

    return null;
  }

  /**
   * Return website which the product are assigned to
   *
   * @param Mage_Catalog_Model_Product|int $product
   *
   * @return Mage_Core_Model_Website
   */
  public function getWebsite ($product = null) {
    $app = Mage::app();

    if ($product == null) {
      $product = Mage::registry('product');

      if (!$product)
        return $app->getWebsite();
    }

    if ($product instanceof Mage_Catalog_Model_Product)
      $ids = $product->getWebsiteIds();
    else
      $ids = Mage::getResourceModel('catalog/product')
               ->getWebsiteIds($product);

    if (!$n = count($ids))
      return $app->getWebsite();

    if ($n == 1)
      return $app->getWebsite(reset($ids));

    foreach ($ids as $id) {
      $website = $app->getWebsite($id);

      if (!$this->getConfig(self::ROOT_WEBSITE_PATH, $website))
        break;
    }

    return $website;
  }

  /**
   * Returns product's URL
   *
   * @param Mage_Catalog_Model_Product $product
   *
   * @return string
   */
  public function getUrl ($product) {
    $website = $this->getWebsite($product);

    $baseUrl = $this->getConfig('web/unsecure/base_url', $website);

    return rtrim($baseUrl, '/')
           . '/'
           . $product->getUrlPath($this->getCategory($product));
  }

  /**
   * Returns TM listing ID linked to the product
   *
   * @param int $productId Product's ID
   *
   * @return string Listing ID
   */
  public function getListingId ($productId) {
    return $this->getAttributesValue($productId, 'tm_current_listing_id');
  }

  /**
   * Sets value of TM listing ID attribute in the product
   *
   * @param int|string $listingId Listing ID
   * @param int $productId Product's ID
   *
   */
  public function setListingId ($listingId, $productId) {
    $attribute = array('tm_current_listing_id' => $listingId);

    $this->setAttributesValue($productId, $attribute);
  }

  /**
   * Try to get product's ID
   *
   * @param  int|string $productId (SKU, ID or Barcode)
   * @param  string $identifierType
   *
   * @return int|null
   */
  public function getProductId ($productId, $identifierType = null) {
    if ($identifierType == 'barcode') {
      $id = (int) $this->getProductIdByBarcode($productId);

      if ($id < 1)
        $id = (int) Mage::getResourceModel('mventory_tm/sku')
          ->getProductId($productId, $this->getCurrentWebsite());

      return $id > 0 ? $id : null;
    }

    if ($identifierType == 'id' && ((int) $productId > 0))
      return (int) $productId;

    $id = (int) Mage::getResourceModel('catalog/product')
                  ->getIdBySku($productId);

    if ($id > 0)
      return $id;

    $id = (int) $this->getProductIdByBarcode($productId);

    if ($id > 0)
      return $id;

    $id = (int) Mage::getResourceModel('mventory_tm/sku')
      ->getProductId($productId, $this->getCurrentWebsite());

    if ($id > 0)
      return $id;

    $id = (int) $productId;

    return $id > 0 ? $id : null;
  }

  /**
   * Search product ID by value of product_barcode_ attribute
   *
   * !!!TODO: product_barcode_ attribute should be converted to global
   *
   * @param  string $barcode Barcode
   *
   * @return int|null
   */
  public function getProductIdByBarcode ($barcode) {
    $ids = Mage::getResourceModel('catalog/product_collection')
             ->addAttributeToFilter('product_barcode_', $barcode)
             ->addStoreFilter($this->getCurrentStoreId())
             ->getAllIds(1);

    return $ids ? $ids[0] : null;
  }

  /**
   * Check if api user has access to a product.
   *
   * Return true if current api users assigned to the Admin website
   * or to the same website as the product
   *
   * @param  int|string $productId (SKU or ID)
   * @param  string $identifierType
   *
   * @return boolean
   */
  public function hasApiUserAccess ($productId, $identifierType = null) {
    $userWebsite = $this->getApiUserWebsite();

    if (!$userWebsite)
      return false;

    $userWebsiteId = $userWebsite->getId();

    if ($userWebsiteId == 0)
      return true;

    $id = $this->getProductId($productId, $identifierType);

    $productWebsiteId = $this
                          ->getWebsite($id)
                          ->getId();

    return $productWebsiteId == $userWebsiteId;
  }

  /**
   * Extracts data for TM options from product or from optional account data
   * if the product doesn't have attribute values
   *
   * @param Mage_Catalog_Model_Product|array $product Product's data
   * @param array $account TM account data 
   *
   * @return array TM options
   */
  public function getTmFields ($product, $account = null) {
    if ($product instanceof Mage_Catalog_Model_Product)
      $product = $product->getData();

    $fields = array();

    foreach ($this->_tmFields as $name => $code) {
      $value = isset($product[$code])
                         ? $product[$code]
                           : null;

      if (!($account && ($value == '-1' || $value === null)))
        $fields[$name] = $value;
      else
        $fields[$name] = isset($account[$name]) ? $account[$name] : null;
    }

    foreach ($this->_tmFieldsWithoutDefaults as $name => $code)
      $fields[$name] = isset($product[$code]) ? $product[$code] : null;

    return $fields;
  }

  /**
   * Sets TM options in product
   *
   * @param Mage_Catalog_Model_Product|array $product Product
   * @param array $fields TM options data
   *
   * @return MVentory_Tm_Helper_Product
   */
  public function setTmFields ($product, $fields) {
    $tmFields = $this->_tmFields + $this->_tmFieldsWithoutDefaults;

    foreach ($tmFields as $name => $code)
      if (isset($fields[$name]))
        $product->setData($code, $fields[$name]);

    return $this;
  }

  /**
   * Prepare TM accounts for the specified product.
   * Leave TM options for product's shipping type only
   *
   * @param array $accounts TM accounts
   * @param Mage_Catalog_Model_Product $product Product
   *
   * @return array
   */
  public function prepareAccounts ($accounts, $product) {
    $shippingType = $this->getShippingType($product, true);

    foreach ($accounts as &$account) {
      if (isset($account['shipping_types'][$shippingType])) {
        $account['shipping_type'] = $shippingType;
        $account = $account + $account['shipping_types'][$shippingType];
      }

      unset($account['shipping_types']);
    }

    return $accounts;
  }

  public function fillTmAttributes ($product, $tmAttributes, $store) {
    $storeId = $store->getId();

    foreach ($tmAttributes as $tmAttribute)
      $_tmAttributes[strtolower($tmAttribute['Name'])] = $tmAttribute;

    unset($tmAttributes);

    foreach ($product->getAttributes() as $code => $attribute) {
      $input = $attribute->getFrontendInput();

      if (!($input == 'select' || $input == 'multiselect'))
        continue;

      $frontend = $attribute->getFrontend();

      $defaultValue = $frontend->getValue($product);
      $attributeStoreId = $attribute->getStoreId();

      $attribute->setStoreId($storeId);
      $value = $frontend->getValue($product);
      $attribute->setStoreId($attributeStoreId);

      if ($defaultValue == $value)
        continue;

      $value = trim($value);

      if (!$value)
        continue;

      $parts = explode(':', $value, 2);

      if (!(count($parts) == 2 && $parts[0]))
        return array(
          'error' => true,
          'no_match' => $code
        );

      $name = strtolower(rtrim($parts[0]));
      $value = ltrim($parts[1]);

      if (!isset($_tmAttributes[$name]))
        continue;

      $tmAttribute = $_tmAttributes[$name];

      $value = trim($value);

      if (!$value && $_tmAttribute['IsRequiredForSell'])
        return array(
          'error' => true,
          'required' => $tmAttribute['DisplayName']
        );

      $result[$tmAttribute['Name']] = $value;
    }

    return array(
      'error' => false,
      'attributes' => isset($result) ? $result : null
    );
  }

  public function updateFromSimilar ($product, $similar) {
    if ($similar instanceof Mage_Catalog_Model_Product)
      $similar = array($similar);

    foreach ($similar as $_similar) {
      $item = Mage::getModel('cataloginventory/stock_item')
        ->loadByProduct($_similar);

      if ($item->getId())
        $similarData[] = $item->getData();
    }

    if (!isset($similarData))
      return;

    unset($item, $_similar);

    $this->_loadStockData($product);

    if ($item = $product->getData('stock_item'))
      $item->setData('mventory_ignore_stock_update', true);

    $product->setData(
      'stock_data',
      $this->_updateStockData($product->getData('stock_data'), $similarData)
    );
  }

  private function _loadStockData ($product) {
    $item = $product->getData('stock_item');

    if ($product->getId()) {
      $stock = Mage::getModel('cataloginventory/stock_item')
        ->loadByProduct($product);

      if ($stock->getId())
        $item = $stock;
    }

    $data = $product->getData('stock_data');

    if (!($item instanceof Mage_CatalogInventory_Model_Stock_Item))
      $item = null;

    if (!$data && $item)
      $data = $item->getData();

    $product
      ->setData('stock_item', $item)
      ->setData('stock_data', $data);
  }

  private function _updateStockData ($stock, $similar) {
    foreach ($similar as $_similar) {
      if (!$stock) {
        $stock = $_similar;
        continue;
      }

      if (!isset($stock['manage_stock']) && isset($_similar['manage_stock']))
        $stock['manage_stock'] = $_similar['manage_stock'];

      if (isset($_similar['qty']))
        $stock['qty'] = isset($stock['qty'])
                          ? $stock['qty'] + $_similar['qty']
                            : $_similar['qty'];
    }

    return $stock;
  }

  /**
   * Loads media_gallery attribute. If product is specified it tries to get
   * the attribute from list of loaded attributes in the product
   *
   * @param Mage_Catalog_Model_Product $product Product
   *
   * @return Mage_Eav_Model_Entity_Attribute
   */
  public function getMediaGalleryAttr ($product = null) {
    if ($product && $product instanceof Mage_Catalog_Model_Product) {
      $attrs = $product->getAttributes();

      if (isset($attrs['media_gallery']))
        return $attrs['media_gallery'];
    }

    return Mage::getModel('eav/entity_attribute')
      ->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'media_gallery');
  }

  /**
   * Returns list of media attributes with values. It return values from last
   * product in the list if no one product has all values for all media
   * attributes
   *
   * @param array $products List of products
   *
   * @return array
   */
  public function getMediaAttrs ($products) {
    $attrs = null;

    foreach ($products as $product) {
      $values = array();

      if (!$attrs)
        $attrs = $product->getMediaAttributes();

      foreach ($attrs as $attr) {
        $code = $attr->getAttributeCode();

        if ($value = $product->getData($code))
          $values[$code] = $value;
      }

      if (count($values) == count($attrs))
        return $values;
    }

    return $values;
  }

  /**
   * Returns list of images for the product
   *
   * @param Mage_Catalog_Model_Product $product Product
   * @param Varien_Object $backend
   * @param bool $fileAsKey Return array of images with image filename as a key
   *
   * @return array
   */
  public function getImages ($product, $backend = null, $fileAsKey = true) {
    $gallery = $product->getMediaGallery('images');

    if (!$gallery && $product->getId()) {
      if (!$backend)
        $backend = new Varien_Object(array(
          'attribute' => $this->getMediaGalleryAttr($product)
        ));

      $gallery
        = Mage::getResourceSingleton('catalog/product_attribute_backend_media')
            ->loadGallery($product, $backend);

      unset($backend);
    }

    if (!$gallery)
      return array();

    if (!$fileAsKey)
      return $gallery;

    foreach ($gallery as $image)
      $images[$image['file']] = $image;

    return $images;
  }

  /**
   * Adds images to the product. It ignores existing product images.
   *
   * @param Mage_Catalog_Model_Product $product Product
   * @param array $images
   *
   * @return MVentory_Tm_Helper_Product
   */
  public function addImages ($product, array $images) {
    $_images = $this->getImages($product);

    $resource
      = Mage::getResourceSingleton('catalog/product_attribute_backend_media');

    $id = $product->getId();
    $storeId = $product->getStoreId();
    $attrId = $this->getMediaGalleryAttr($product)->getAttributeId();

    foreach ($images as $image) {
      if (!isset($_images[$image['file']])) {
        $resource->insertGalleryValueInStore(
            array(
              'value_id' => $resource->insertGallery(
                array(
                  'entity_id' => $id,
                  'attribute_id' => $attrId,
                  'value' => $image['file']
                )
              ),
              'label'  => $image['label'],
              'position' => (int) $image['position'],
              'disabled' => (int) $image['disabled'],
              'store_id' => $storeId
            )
          );
      }
    }

    return $this;
  }

  /**
   * Returns value of mv_shipping_ attribute from specified product
   *
   * @param Mage_Catalog_Model_Product $product
   * @param boolean $rawValue
   * @return mixin Value of the attribute
   */
  public function getShippingType ($product, $rawValue = false) {
    $attributeCode = 'mv_shipping_';

    if ($rawValue)
      return $product->getData($attributeCode);

    $attributes = $product->getAttributes();

    return isset($attributes[$attributeCode])
             ? $attributes[$attributeCode]->getFrontend()->getValue($product)
               : null;
  }
}
