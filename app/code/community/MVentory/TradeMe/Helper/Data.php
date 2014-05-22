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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

/**
 * Image helper
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Helper_Data extends Mage_Core_Helper_Abstract
{
  const COUNTRY_CODE = 'NZ';

  const LISTING_DURATION_MAX = 7;
  const LISTING_DURATION_MIN = 2;

  //TradeMe fees description
  //Available fields:
  // * from - Min product price for the fee (value is included in comparision)
  // * to - Max product price for the fee (value is included in comparision)
  // * rate - Percents
  // * fixed - Fixed part of fee
  // * min - Min fee value
  // * max - Max fee value
  private $_fees = array(
    //Up to $200 : 7.9% of sale price (50c minimum)
    array(
      'from' => 0,
      'to' => 200,
      'rate' => 0.079,
      'fixed' => 0,
      'min' => 0.5
    ),

    //$200 - $1500 : $15.80 + 4.9% of sale price over $200
    array(
      'from' => 200,
      'to' => 1500,
      'rate' => 0.049,
      'fixed' => 15.8,
    ),

    //Over $1500 : $79.50 + 1.9% of sale price over $1500 (max fee = $149)
    array(
      'from' => 1500,
      'rate' => 0.019,
      'fixed' => 79.5,
      'max' => 149
    ),
  );

  protected $_fields = array(
    'account_id' => 'tm_account_id',
    'shipping_type' => 'tm_shipping_type',
    'allow_buy_now' => 'tm_allow_buy_now',
    'add_fees' => 'tm_add_fees',
    'avoid_withdrawal' => 'tm_avoid_withdrawal',
  );

  protected $_fieldsWithoutDefaults = array(
    'relist' => 'tm_relist',
    'pickup' => 'tm_pickup'
  );

  public function getAttributes ($categoryId) {
    $model = new MVentory_TradeMe_Model_Api();

    $attrs = $model->getCategoryAttrs($categoryId);

    if (!(is_array($attrs) && count($attrs)))
      return null;

    foreach ($attrs as &$attr)
      $attr['Type'] = $model->getAttrTypeName($attr['Type']);

    return $attrs;
  }

  /**
   * Returns URL to the product's listing on TradeMe
   *
   * @param Mage_Catalog_Model_Product
   * @return string URL to the listing
   */
  public function getListingUrl ($product) {
    $website = Mage::helper('mventory_tm/product')->getWebsite($product);

    $domain = $this->isSandboxMode($website)
                ? 'tmsandbox'
                  : 'trademe';

    return 'http://www.'
           . $domain
           . '.co.nz/Browse/Listing.aspx?id='
           . $product->getTmCurrentListingId();
  }

  /**
   * Calculate TradeMe fees for the product
   *
   * @param float $price
   * @return float Calculated fees
   */
  public function calculateFees ($price) {

    //What we need to calculate here is the price to be used
    //for TradeMe listing. The value of that price after subtracting TradeMe
    //fees must be equal to the sell price from magento
    //(which is the price passed as an argument to this function)

    //This should never happen
    if ($price < 0)
      return 0;

    //First we need to figure out in which range the final TradeMe listing price
    //is going to be
    foreach ($this->_fees as $_fee) {

      //If a range doesn't have the upper bound then we are in the last range
      //so no need to check anything and we are just gonna use that range
      if (isset($_fee['to'])) {

        //Max fee value we can add to the price not to exceed the from..to range
        $maxFee = $_fee['to'] - $price;

        //If cannot add any fees then we are in the wrong range
        if ($maxFee < 0)
          continue;

        //Fee for the maximum price that still fits in the current range
        $feeForTheMaxPrice = $_fee['fixed']
                             + (($_fee['to']) - $_fee['from'])
                             * $_fee['rate'];

        //If the maximum fees we can apply not to exceed the from..to range
        //are still not enough then we are in the wrong range
        if ($maxFee < $feeForTheMaxPrice)
          continue;
      }

      //Calculate the fee for the range selected
      $fee = ($_fee['fixed'] + ($price - $_fee['from']) * $_fee['rate'])
             / (1 - $_fee['rate']);

      //Take into account the min and max values. */
      if (isset($_fee['min']) && $fee < $_fee['min'])
        $fee = $_fee['min'];

      if (isset($_fee['max']) && $fee > $_fee['max'])
        $fee = $_fee['max'];

      return $fee;
    }

    //This should never happen
    return 0;
  }

  /**
   * Add TradeMe fees to the product's price and format it
   *
   * @param float $price
   * @return float Product's price with calculated fees
   */
  public function addFees ($price) {
    return round($price + $this->calculateFees($price), 2);
  }

  /**
   * Calculates shippign rate for product by region ID.
   * Uses 'tablerate' carrier (Table rates shipping method)
   *
   * @param Mage_Catalog_Model_Product $product
   * @param string $regionName
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   * @return float Calculated shipping rate
   */
  public function getShippingRate ($product, $regionName, $website) {
    $destRegion = Mage::getModel('directory/region')
      ->loadByName($regionName, self::COUNTRY_CODE);

    if (!$destRegionId = $destRegion->getId())
      return;

    $store = $website->getDefaultStore();

    $path = Mage_Shipping_Model_Shipping::XML_PATH_STORE_REGION_ID;

    //Ignore when destionation region similar to origin one
    if (($origRegionId = Mage::getStoreConfig($path, $store)) == $destRegionId)
      return;

    $request = Mage::getModel('shipping/rate_request')
      ->setCountryId(self::COUNTRY_CODE)
      ->setRegionId($origRegionId)
      ->setDestCountryId(self::COUNTRY_CODE)
      ->setDestRegionId($destRegionId)
      ->setStoreId($store->getId())
      ->setStore($store)
      ->setWebsiteId($website->getId())
      ->setBaseCurrency($website->getBaseCurrency())
      ->setPackageCurrency($store->getCurrentCurrency())
      ->setAllItems(array($product))

      //We're using only volume carrier
      ->setLimitCarrier('volumerate')

      //Show that we have origin country and region in the request
      ->setOrig(true);

    $result = Mage::getModel('shipping/shipping')
      ->collectRates($request)
      ->getResult();

    if (!$result)
      return;

    //Expect only one rate
    if (!$rate = $result->getRateById(0))
      return;

    return $rate->getPrice();
  }

  /**
   * Retrieve array of accounts for specified website. Returns accounts from
   * default scope when parameter is omitted
   *
   * @param mixin $websiteId
   * @return array List of accounts
   */
  public function getAccounts ($website, $withRandom = true) {
    $website = Mage::app()->getWebsite($website);

    $configData = Mage::getModel('adminhtml/config_data')
      ->setWebsite($website->getCode())
      ->setSection('trademe');

    $configData->load();

    $groups = $configData->getConfigDataValue('trademe')->asArray();

    $accounts = array();

    if ($withRandom)
      $accounts[null] = array('name' => $this->__('Random'));

    foreach ($groups as $id => $fields)
      if (strpos($id, 'account_', 0) === 0) {
        if (isset($fields['shipping_types']))
          $fields['shipping_types']
            = (($types = unserialize($fields['shipping_types'])) === false)
                 ? array()
                   : $types;

        $accounts[$id] = $fields;
      }

    return $accounts;
  }

  /**
   * Prepare accounts for the specified product.
   * Leave TradeMe options for product's shipping type only
   *
   * @param array $accounts TradeMe accounts
   * @param Mage_Catalog_Model_Product $product Product
   *
   * @return array
   */
  public function prepareAccounts ($accounts, $product) {
    $shippingType = Mage::helper('mventory_tm/product')->getShippingType(
      $product,
      true
    );

    foreach ($accounts as &$account) {
      if (isset($account['shipping_types'][$shippingType])) {
        $account['shipping_type'] = $shippingType;
        $account = $account + $account['shipping_types'][$shippingType];
      }

      unset($account['shipping_types']);
    }

    return $accounts;
  }

  /**
   * Return account ID which will be used for the next listing of specified
   * product on TradeMe
   *
   * @param int $productId
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   * @return string Account ID
   */
  public function getAccountId ($productId, $website) {
    return Mage::helper('mventory_tm')
      ->getAttributesValue($productId, 'tm_account_id', $website);
  }

  /**
   * Set account ID which will be used for the next listing of specified
   * product on TradeMe
   *
   * @param int $productId
   * @param string $accountId
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   * @return MVentory_TradeMe_Helper_Data
   */
  public function setAccountId ($productId, $accountId, $website) {
    Mage::helper('mventory_tm')->setAttributesValue(
      $productId,
      array('tm_account_id' => $accountId),
      $website
    );

    return $this;
  }

  /**
   * Return account ID used for listing specified product on TradeMe
   *
   * @param int $productId
   * @return string Account ID
   */
  public function getCurrentAccountId ($productId) {
    return Mage::helper('mventory_tm')
      ->getAttributesValue($productId, 'tm_current_account_id');
  }

  /**
   * Set account ID used for listing specified product on TradeMe
   *
   * @param int $productId
   * @param string $accountId
   * @return MVentory_TradeMe_Helper_Data
   */
  public function setCurrentAccountId ($productId, $accountId) {
    Mage::helper('mventory_tm')->setAttributesValue(
      $productId,
      array('tm_current_account_id' => $accountId)
    );

    return $this;
  }

  /**
   * Returns listing ID linked to the product
   *
   * @param int $productId Product's ID
   * @return string Listing ID
   */
  public function getListingId ($productId) {
    return Mage::helper('mventory_tm')->getAttributesValue(
      $productId,
      'tm_current_listing_id'
    );
  }

  /**
   * Sets value of listing ID attribute in the product
   *
   * @param int|string $listingId Listing ID
   * @param int $productId Product's ID
   * @return MVentory_TradeMe_Helper_Data
   */
  public function setListingId ($listingId, $productId) {
    Mage::helper('mventory_tm')->setAttributesValue(
      $productId,
      array('tm_current_listing_id' => $listingId)
    );

    return $this;
  }

  /**
   * Extracts data for TradeMe options from product or from optional
   * account data if the product doesn't have attribute values
   *
   * @param Mage_Catalog_Model_Product|array $product Product's data
   * @param array $account TradeMe account data
   *
   * @return array TradeMe options
   */
  public function getFields ($product, $account = null) {
    if ($product instanceof Mage_Catalog_Model_Product)
      $product = $product->getData();

    $fields = array();

    foreach ($this->_fields as $name => $code) {
      $value = isset($product[$code])
                         ? $product[$code]
                           : null;

      if (!($account && ($value == '-1' || $value === null)))
        $fields[$name] = $value;
      else
        $fields[$name] = isset($account[$name]) ? $account[$name] : null;
    }

    foreach ($this->_fieldsWithoutDefaults as $name => $code)
      $fields[$name] = isset($product[$code]) ? $product[$code] : null;

    return $fields;
  }

  /**
   * Sets TradeMe options in product
   *
   * @param Mage_Catalog_Model_Product|array $product Product
   * @param array $fields Trademe options data
   *
   * @return MVentory_TradeMe_Helper_Data
   */
  public function setFields ($product, $fields) {
    $_fields = $this->_fields + $this->_fieldsWithoutDefaults;

    foreach ($_fields as $name => $code)
      if (isset($fields[$name]))
        $product->setData($code, $fields[$name]);

    return $this;
  }

  public function fillAttributes ($product, $attrs, $store) {
    $storeId = $store->getId();

    foreach ($attrs as $attr)
      $_attrs[strtolower($attr['Name'])] = $attr;

    unset($attrs);

    foreach ($product->getAttributes() as $code => $pAttr) {
      $input = $pAttr->getFrontendInput();

      if (!($input == 'select' || $input == 'multiselect'))
        continue;

      $frontend = $pAttr->getFrontend();

      $defaultValue = $frontend->getValue($product);
      $attributeStoreId = $pAttr->getStoreId();

      $pAttr->setStoreId($storeId);
      $value = $frontend->getValue($product);
      $pAttr->setStoreId($attributeStoreId);

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

      if (!isset($_attrs[$name]))
        continue;

      $attr = $_attrs[$name];

      $value = trim($value);

      if (!$value && $attr['IsRequiredForSell'])
        return array(
          'error' => true,
          'required' => $attr['DisplayName']
        );

      $result[$attr['Name']] = $value;
    }

    return array(
      'error' => false,
      'attributes' => isset($result) ? $result : null
    );
  }

  public function getMappingStore () {
    return Mage::app()->getStore(
      (int) parent::getConfig(MVentory_TradeMe_Model_Config::MAPPING_STORE)
    );
  }

  /**
   * Returns duration of TradeMe listing limited by MIN and MAX values
   * By default returns MAX duration value
   *
   * @param array $data Account data
   * @return int duration
   */
  public function getDuration ($data) {
    if (!(isset($data['duration'])
          && $duration = (int) $data['duration']))
      return self::LISTING_DURATION_MAX;

    if ($duration < self::LISTING_DURATION_MIN)
      return self::LISTING_DURATION_MIN;

    if ($duration > self::LISTING_DURATION_MAX)
      return self::LISTING_DURATION_MAX;

    return $duration;
  }

  /**
   * Upload TradeMe optons file and import data from it
   *
   * @param Varien_Object $object
   * @throws Mage_Core_Exception
   */
  public function importOptions ($data) {
    $shippingTypes =
      Mage::getModel('mventory_tm/system_config_source_allowedshippingtypes')
        ->toArray();

    if (!$shippingTypes)
      Mage::throwException($this->__('There\'re no available shipping types'));

    $scopeId = $data->getScopeId();
    $website = Mage::app()->getWebsite($scopeId);

    $accounts = $this->getAccounts($website, false);

    if (!$accounts)
      Mage::throwException($this->__('There\'re no accounts in this website'));

    foreach ($accounts as $id => $account)
      $accountMap[strtolower($account['name'])] = $id;

    foreach ($shippingTypes as $id => $label)
      $shippingTypeMap[strtolower($label)] = $id;

    unset($accounts, $shippingTypes);

    $groupId = $data->getGroupId();
    $field = $data->getField();

    if (empty($_FILES['groups']
                     ['tmp_name']
                     [$groupId]
                     ['fields']
                     [$field]
                     ['value']))
      return;

    $file = $_FILES['groups']['tmp_name'][$groupId]['fields'][$field]['value'];

    $info = pathinfo($file);

    $io = new Varien_Io_File();

    $io->open(array('path' => $info['dirname']));
    $io->streamOpen($info['basename'], 'r');

    //Check and skip headers
    $headers = $io->streamReadCsv();

    if ($headers === false || count($headers) < 5) {
      $io->streamClose();

      Mage::throwException(
        $this->__('Invalid TradeMe options file format')
      );
    }

    $rowNumber = 1;
    $data = array();

    $params = array(
      'account' => $accountMap,
      'type' => $shippingTypeMap,
      'hash' => array(),
      'errors' => array()
    );

    try {
      while (false !== ($line = $io->streamReadCsv())) {
        $rowNumber ++;

        if (empty($line))
          continue;

        $row = $this->_getImportRow($line, $rowNumber, $params);

        if ($row !== false)
          $data[] = $row;
      }

      $io->streamClose();

      $this->_saveImportedOptions($data, $website);

    } catch (Mage_Core_Exception $e) {
      $io->streamClose();

      Mage::throwException($e->getMessage());
    } catch (Exception $e) {
      $io->streamClose();

      Mage::logException($e);

      $msg = $this->__('An error occurred while import TradeMe options.');

      if ($params['errors'])
        $msg .= " \n" . implode(" \n", $params['errors']);

      Mage::throwException($msg);
    }

    if ($params['errors']) {
      $msg = 'File has not been imported. See the following list of errors: %s';
      $msg = $this->__($msg, implode(" \n", $params['errors']));

      Mage::throwException($msg);
    }
  }

  /**
   * Validate row for import and return options array or false
   *
   * @param array $row
   * @param int $rowNumber
   *
   * @return array|false
   */
  protected function _getImportRow ($row, $rowNumber = 0, &$params) {

    //Validate row
    if (count($row) < 10) {
      $msg = 'Invalid TradeMe options format in the row #%s';
      $params['errors'][] = $this->__($msg, $rowNumber);

      return false;
    }

    //Strip whitespace from the beginning and end of each column
    foreach ($row as $k => $v)
      $row[$k] = trim($v);

    $account = strtolower($row[0]);

    if (!isset($params['account'][$account])) {
      $msg = 'Invalid account ("%s") in the row #%s.';
      $params['errors'][] = $this->__($msg, $row[0], $rowNumber);

      return false;
    }

    $account = $params['account'][$account];

    $shippingType = strtolower($row[1]);

    if (!isset($params['type'][$shippingType])) {
      $msg = 'Invalid shipping type ("%s") in the row #%s.';
      $params['errors'][] = $this->__($msg, $row[1], $rowNumber);

      return false;
    }

    $shippingType = $params['type'][$shippingType];

    //Validate minimal price
    $minimalPrice = $this->_parseDecimalValue($row[2]);

    if ($minimalPrice === false) {
      $msg = 'Invalid Minimal price ("%s") value in the row #%s.';
      $params['errors'][] = $this->__($msg, $row[2], $rowNumber);

      return false;
    }

    $freeShippingCost = $this->_parseDecimalValue($row[3]);

    if ($freeShippingCost === false) {
      $msg = 'Invalid Free shipping cost ("%s") value in the row #%s.';
      $params['errors'][] = $this->__($msg, $row[3], $rowNumber);

      return false;
    }

    //Validate listing duration value
    $listingDuaration = (int) $row[10];

    //!!!TODO: use getDuration() method
    if (!$listingDuaration || $listingDuaration > self::LISTING_DURATION_MAX)
      $listingDuaration = self::LISTING_DURATION_MAX;
    else if ($listingDuaration < self::LISTING_DURATION_MIN)
      $listingDuaration = self::LISTING_DURATION_MIN;

    //Protect from duplicate
    $hash = sprintf('%s-%s', $account, $shippingType);

    if (isset($params['hash'][$hash])) {
      $msg = 'Duplicate Row #%s.';

      $params['errors'][] = $this->__($msg, $rowNumber);

      return false;
    }

    $params['hash'][$hash] = true;

    return array(
      'account' => $account,
      'shipping_type' => $shippingType,
      'minimal_price' => $minimalPrice,
      'free_shipping_cost' => $freeShippingCost,
      'allow_buy_now' => (bool) $row[4],
      'avoid_withdrawal' => (bool) $row[5],
      'add_fees' => (bool) $row[6],
      'allow_pickup' => (bool) $row[7],
      'category_image' => (bool) $row[8],
      'buyer' => (int) $row[9],
      'duration' => $listingDuaration,
      'shipping_options' => $this->_parseShippingOptionsValue($row[11]),
      'footer' => $row[12]
    );
  }

  /**
   * Parse and validate positive decimal value
   * Return false if value is not decimal or is not positive
   *
   * @param string $value
   * @return bool|float
   */
  protected function _parseDecimalValue ($value) {
    if (!is_numeric($value))
      return false;

    $value = (float) sprintf('%.2F', $value);

    if ($value < 0.0000)
      return false;

    return $value;
  }

  /**
   * Parse string with shipping options in following format
   *
   *   <price>,<method>\r\n
   *   ...
   *   <price>,<method>
   *
   * to a list with shipping options in following format
   *
   *   array(
   *     array(
   *       'price' => 12.5,
   *       'method' => 'Name of shipping method'
   *     ),
   *     ...
   *   )
   *
   * @param string $value Shipping options
   * @return array
   */
  protected function _parseShippingOptionsValue ($value) {
    $options = array();

    if (!$value = trim($value))
      return $options;

    foreach (explode("\n", str_replace("\r\n", "\n", $value)) as $option)
      if (count($option = explode(',', trim($option, " ,\t\n\r\0\x0B"))) == 2)
        $options[] = array(
          'price' => (float) rtrim($option[0]),
          'method' => ltrim($option[1])
        );

    return $options;
  }

  /**
   * Save parsed options in Magento config
   *
   * @param string $value
   * @return bool|float
   */
  protected function _saveImportedOptions ($data, $website) {
    foreach ($data as $options) {
      $accountId = $options['account'];
      $shippingTypeId = $options['shipping_type'];

      unset($options['account'], $options['shipping_type']);

      $accounts[$accountId][$shippingTypeId] = $options;
    }

    $websiteId = $website->getId();
    $config = Mage::getConfig();

    foreach ($accounts as $id => $data)
      $config->saveConfig(
        'trademe/' . $id . '/shipping_types',
        serialize($data),
        'websites',
        $websiteId
      );

    $config->reinit();

    Mage::app()->reinitStores();
  }

  public function isSandboxMode ($websiteId) {
    $path = MVentory_TradeMe_Model_Config::SANDBOX;

    return Mage::helper('mventory_tm')->getConfig($path, $websiteId) == true;
  }
}
