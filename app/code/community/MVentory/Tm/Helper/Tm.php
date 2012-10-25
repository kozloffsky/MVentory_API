<?php

class MVentory_Tm_Helper_Tm extends Mage_Core_Helper_Abstract {

  const XML_PATH_ACCOUNTS = 'mventory_tm/settings/accounts';

  //TM fees description.
  //Available fields:
  // * from - Min product price for the fee (value is included in comparision)
  // * to - Max product price for the fee (value is included in comparision)
  // * rate - Percents
  // * fixed - Fixed part of fee
  // * min - Min fee value
  // * max - Max fee value
  private $_fees = array(
    //Up to $200 : 7.5% of sale price (50c minimum)
    array(
      'from' => 0,
      'to' => 199,
      'rate' => 0.075,
      'fixed' => 0,
      'min' => 0.5,
    ),

    //$200 - $1500 : $15.00 + 4.5% of sale price over $200
    array(
      'from' => 200,
      'to' => 1500,
      'rate' => 0.045,
      'fixed' => 15,
    ),

    //Over $1500 : $73.50 + 1.9% of sale price over $1500 (max fee = $149)
    array(
      'from' => 1501,
      'rate' => 0.019,
      'fixed' => 73.5,
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
   * Add TM fees to the product
   *
   * @param float $price
   * @return float Product's price with calculated fees
   */
  public function addFees ($price) {
    foreach ($this->_fees as $_fee) {
      //Check if price of the product is in the range of the fee
      $from = isset($_fee['from'])
                ? $price >= $_fee['from']
                  : true;

      $to = isset($_fee['to'])
              ? $price <= $_fee['to']
                : true;

      //Price of the product is not the range of the fee
      if (!($from && $to))
        continue;

      //Add fixed part of the fee
      $_price = isset($_fee['fixed'])
                   ? $price + $_fee['fixed']
                     : $price;

      //Calculate final price
      if (isset($_fee['rate']))
        $_price /= 1 - $_fee['rate'];

      //Return final price if there's no min/max for the fee value
      if (!(isset($_fee['min']) || isset($_fee['max'])))
        return round($_price, 2);

      $fee = $_price - $price;

      //Check for min and max values of the calculated fee
      if (isset($_fee['min']) && $fee < $_fee['min'])
        $fee = $_fee['min'];

      if (isset($_fee['max']) && $fee > $_fee['max'])
        $fee = $_fee['max'];

      return round($price + $fee, 2);
    }

    return $price;
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
    $value = Mage::helper('mventory_tm')
               ->getConfig(self::XML_PATH_ACCOUNTS, $website);

    $value = unserialize($value);

    return $value !== false ? $value : array();
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
    return Mage::helper('mventory_tm')
             ->getAttributesValue($productId, 'tm_account_id', $website);
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

    Mage::helper('mventory_tm')
      ->setAttributesValue($productId, $attrData, $website);
  }
}
