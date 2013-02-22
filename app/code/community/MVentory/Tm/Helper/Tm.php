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
   * Returns URL to the listing on TM which specified product is assigned to
   *
   * @param Mage_Catalog_Model_Product|int $product
   *
   * @return string URL to the listing
   */
  public function getListingUrl ($product) {
    $domain = $this->isSandboxMode($this->getWebsite($product))
                ? 'tmsandbox'
                  : 'trademe';

    return 'http://www.'
           . $domain
           . '.co.nz/Browse/Listing.aspx?id='
           . $product->getTmListingId();
  }

  /**
   * Add TM fees to the product
   *
   * @param float $price
   * @return float Product's price with calculated fees
   */
  public function addFees ($price) {

    /* What we need to calculate here is the price to be used for TM listing.
       The value of that price after subtracting TM fees must be equal to the
       sell price from magento (which is the price passed as an argument to
       this function) */

    /* This should never happen. */
    if ($price < 0)
      return $price;

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

      /* Return the sale price with fees added. */
      return round($price + $fee, 2);
    }

    /* This should never happen. */
    return $price;
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
  public function getAccounts ($website) {
    $website = Mage::app()->getWebsite($website);

    $configData = Mage::getSingleton('adminhtml/config_data')
                    ->setWebsite($website->getCode())
                    ->setSection('mventory_tm');

    $configData->load();

    $groups = $configData->getConfigDataValue('mventory_tm')->asArray();

    $accounts = array();

    foreach ($groups as $id => $fields)
      if (strpos($id, 'account_', 0) === 0)
        $accounts[$id] = $fields;

    return $accounts;
  }

  /**
   * Return account ID used for listing specified product on TM
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
   * Set account ID used for listing specified product on TM
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
   * Returns value of mv_shipping_ attribute from specified product
   *
   * @param Mage_Catalog_Model_Product $product
   *
   * @return mixin Value of the attribute
   */
  public function getShippingType ($product) {
    $attributes = $product->getAttributes();

    $attributeCode = 'mv_shipping_';

    return isset($attributes[$attributeCode])
             ? $attributes[$attributeCode]->getFrontend()->getValue($product)
               : null;
  }
}
