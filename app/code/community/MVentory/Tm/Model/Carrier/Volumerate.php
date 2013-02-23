<?php

/**
 * Volume based shipping carrier model
 *
 * @category   MVentor
 * @package    MVentory_Tm
 */

class MVentory_Tm_Model_Carrier_Volumerate
  extends Mage_Shipping_Model_Carrier_Abstract
  implements Mage_Shipping_Model_Carrier_Interface {

  const WEIGHT_CONDITION = 1;
  const VOLUME_CONDITION = 2;

  const DIMENSIONS_DELIMITER = '/';

  protected $_code = 'volumerate';
  protected $_isFixed = true;

  protected $_conditions = array(
    self::WEIGHT_CONDITION => 'weight',
    self::VOLUME_CONDITION => 'volume'
  );

  /**
   * Collect shipping rates
   *
   * @param Mage_Shipping_Model_Rate_Request $request
   * @return Mage_Shipping_Model_Rate_Result
   */
  public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
    if (!$this->getConfigFlag('active'))
      return false;

    //Clone conditions for futher using
    $conditions = $this->_conditions;

    $attributes = $this->_getVolumeAttributes();

    //Unset volume condition if volume attributes are not specified in
    //carrier config
    if (!$attributes)
      unset($conditions[self::VOLUME_CONDITION]);

    $rate = 0;

    foreach ($request->getAllItems() as $item) {
      if ($item instanceof Mage_Catalog_Model_Product)
        $product = $item->setQty(1);
      else
        $product = $item
                     ->setData('product',   null)
                     ->getProduct();

      //Convert weight from kilogrammes to tones
      $request->setWeight($product->getWeight() / 1000);

      //Calculate volume if volume condition is allowed
      if (isset($conditions[self::VOLUME_CONDITION]))
        $request->setVolume($this->_calculateVolume($product, $attributes));

      $itemRate = 0;

      foreach ($conditions as $condition) {

        //Ignore condition if its value is null
        if ($request->getData($condition) === null)
          continue;

        $request->setConditionName($condition);

        $_rate = $this->getRate($request);

        if (!isset($_rate['price']))
          continue;

        //Rate is per unit of condition value.
        //So we have to multiply it by number of units.
        $_rate['price'] *= $request->getData($condition);

        if ($_rate['price'] > $itemRate)
          $itemRate = $_rate['price'];
      }

      $rate += $itemRate * $item->getQty();
    }

    $minimalRate = (float) $this->getConfigData('minimal_rate');

    if ($minimalRate > $rate)
      $rate = $minimalRate;

    $result = Mage::getModel('shipping/rate_result');

    if ($rate >= 0) {
      $method = Mage::getModel('shipping/rate_result_method');

      $method->setCarrier('volumerate');
      $method->setCarrierTitle($this->getConfigData('title'));

      $method->setMethod('volumeweight');
      $method->setMethodTitle($this->getConfigData('name'));

      $method->setPrice($rate);
      $method->setCost(0);

      $result->append($method);
    }

    return $result;
  }

  public function getRate (Mage_Shipping_Model_Rate_Request $request) {
    return Mage::getResourceModel('mventory_tm/carrier_volumerate')
             ->getRate($request);
  }

  /**
   * Get allowed shipping methods
   *
   * @return array
   */
  public function getAllowedMethods () {
    return array('volumeweight' => $this->getConfigData('name'));
  }

  /**
   * Parse Volume attributes setting and return array of attribute codes
   *
   * @return array
   */
  protected function _getVolumeAttributes () {
    $attributes = $this->getConfigData('volume_attributes');

    if (!$attributes)
      return;

    $attributes = str_replace(', ', ',', $attributes);
    $attributes = explode(',', $attributes);

    if (!is_array($attributes))
      return;

    foreach ($attributes as $i => $attribute)
      if (!$attribute)
        unset($attributes[$i]);

    if (!count($attributes))
      return;

    return $attributes;
  }

  /**
   * Parse values of dimension attributes in specified product and calculate
   * volume if value is correct
   *
   * @param Mage_Catalog_Model_Product $product Product
   * @param array $attributes List of attribute codes
   *
   * @return numeric Calculated volume
   */
  protected function _calculateVolume ($product, $attributes) {
    $volume = null;

    foreach ($attributes as $attribute) {
      $dimensions = $product->getData($attribute);

      if (!$dimensions)
        continue;

      $dimensions = explode('/', $dimensions);

      if (!is_array($attributes))
        continue;

      foreach ($dimensions as $i => $dimension)
        if (!is_numeric($dimension) || $dimension == 0)
          unset($dimensions[$i]);

      if (count($dimensions) != 3)
        continue;

      $volume = 1;

      foreach ($dimensions as $dimension)
        //!!!FIXME: Harcoded convertion from milimetres to metres
        $volume *= ($dimension / 1000);

      break;
    }

    return $volume;
  }
}
