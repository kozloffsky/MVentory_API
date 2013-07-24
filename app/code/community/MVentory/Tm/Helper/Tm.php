<?php

class MVentory_Tm_Helper_Tm extends MVentory_Tm_Helper_Data {

  const XML_PATH_ACCOUNTS = 'mventory_tm/settings/accounts';

  const COUNTRY_CODE = 'NZ';

  //TM fees description.
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

  public function getAttributes ($categoryId) {
    $model = Mage::getModel('mventory_tm/connector');

    $attrs = $model
               ->getTmCategoryAttrs($categoryId);

    if (!(is_array($attrs) && count($attrs)))
      return null;

    foreach ($attrs as &$attr) {
      $attr['Type'] = $model->getAttrTypeName($attr['Type']);
    }

    return $attrs;
  }

  /**
   * !!!TODO: move to product helper
   *
   * Returns URL to the listing on TM which specified product is assigned to
   *
   * @param Mage_Catalog_Model_Product|int $product
   *
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
   * Calculate TM fees for the product
   *
   * @param float $price
   * @return float Calculated fees
   */
  public function calculateFees ($price) {

    /* What we need to calculate here is the price to be used for TM listing.
       The value of that price after subtracting TM fees must be equal to the
       sell price from magento (which is the price passed as an argument to
       this function) */

    /* This should never happen. */
    if ($price < 0)
      return 0;

    /* First we need to figure out in which range the final TM listing price
       is going to be. */
    foreach ($this->_fees as $_fee) {

      /* If a range doesn't have the upper bound then we are in the last range so no
         no need to check anything and we are just gonna use that range. */
      if (isset($_fee['to']))
      {
        /* Max fee value we can add to the price not to exceed the from..to range */
        $maxFee = $_fee['to'] - $price;

        /* If cannot add any fees then we are in the wrong range. */
        if ($maxFee < 0)
          continue;

        /* Fee for the maximum price that still fits in the current range. */
        $feeForTheMaxPrice = $_fee['fixed'] + (($_fee['to']) - $_fee['from']) * $_fee['rate'];

        /* If the maximum fees we can apply not to exceed the from..to range
         are still not enough then we are in the wrong range. */
        if ($maxFee < $feeForTheMaxPrice)
          continue;
      }

      /* Calculate the fee for the range selected. */
      $fee = ( $_fee['fixed']+($price-$_fee['from'])*$_fee['rate'] )/( 1-$_fee['rate'] );

      /* Take into account the min and max values. */
      if (isset($_fee['min']) && $fee < $_fee['min'])
        $fee = $_fee['min'];

      if (isset($_fee['max']) && $fee > $_fee['max'])
        $fee = $_fee['max'];

      return $fee;
    }

    /* This should never happen. */
    return 0;
  }

  /**
   * Add TM fees to the product's price
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
   *
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

                 //We're using only tablerate carrier
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
   *
   * @return array List of accounts
   */
  public function getAccounts ($website, $withRandom = true) {
    $website = Mage::app()->getWebsite($website);

    $configData = Mage::getModel('adminhtml/config_data')
                    ->setWebsite($website->getCode())
                    ->setSection('mventory_tm');

    $configData->load();

    $groups = $configData->getConfigDataValue('mventory_tm')->asArray();

    $accounts = array();

    if ($withRandom)
      $accounts[null] = array('name' => $this->__('Random'));

    foreach ($groups as $id => $fields)
      if (strpos($id, 'account_', 0) === 0) {
        if (isset($fields['shipping_types']))
          $fields['shipping_types'] = unserialize($fields['shipping_types']);

        $accounts[$id] = $fields;
      }

    return $accounts;
  }

  /**
   * Return account ID which will be used for the next listing of specified
   * product on TM
   *
   * @param int $productId
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   *
   * @return string Account ID
   */
  public function getAccountId ($productId, $website) {
    return $this->getAttributesValue($productId, 'tm_account_id', $website);
  }

  /**
   * Set account ID which will be used for the next listing of specified
   * product on TM
   *
   * @param int $productId
   * @param string $accountId
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   */
  public function setAccountId ($productId, $accountId, $website) {
    $attrData = array('tm_account_id' => $accountId);

    $this->setAttributesValue($productId, $attrData, $website);
  }

  /**
   * Return account ID used for listing specified product on TM
   *
   * @param int $productId
   *
   * @return string Account ID
   */
  public function getCurrentAccountId ($productId) {
    return
      $this->getAttributesValue($productId, 'tm_current_account_id');
  }

  /**
   * Set account ID used for listing specified product on TM
   *
   * @param int $productId
   * @param string $accountId
   */
  public function setCurrentAccountId ($productId, $accountId) {
    $attrData = array('tm_current_account_id' => $accountId);

    $this->setAttributesValue($productId, $attrData);
  }

  /**
   * Returns value of mv_shipping_ attribute from specified product
   *
   * @param Mage_Catalog_Model_Product $product
   *
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

  /**
   * Upload TM optons file and import data from it
   *
   * @param Varien_Object $object
   * @throws Mage_Core_Exception
   *
   * @return void
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
        $this->__('Invalid TM options file format')
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

      $msg = $this->__('An error occurred while import TM options.');

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
      $msg = 'Invalid TM options format in the row #%s';
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
      'avoid_withdraval' => (bool) $row[5],
      'add_fees' => (bool) $row[6],
      'allow_pickup' => (bool) $row[7],
      'category_image' => (bool) $row[8],
      'buyer' => (int) $row[9],
      'footer' => $row[10],
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
   * Save parsed options in Magento config
   * 
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
        'mventory_tm/' . $id . '/shipping_types',
        serialize($data),
        'websites',
        $websiteId
      );

    $config->reinit();

    Mage::app()->reinitStores();
  }
}
