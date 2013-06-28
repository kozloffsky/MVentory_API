<?php

class MVentory_Tm_Helper_Product extends MVentory_Tm_Helper_Data {

  const ROOT_WEBSITE_PATH = 'mventory_tm/shop-interface/root_website';

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
    if ($identifierType == 'barcode')
      return (int) $this->getProductIdByBarcode($productId);

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
                  ->getProductId($productId);

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
    $shippingType = Mage::helper('mventory_tm/tm')
                      ->getShippingType($product, true);

    foreach ($accounts as &$account) {
      if (isset($account['shipping_types'][$shippingType])) {
        $account['shipping_type'] = $shippingType;
        $account = $account + $account['shipping_types'][$shippingType];
      }

      unset($account['shipping_types']);
    }

    return $accounts;
  }
}
