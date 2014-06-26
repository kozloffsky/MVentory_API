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
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Volume based shipping carrier model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Carrier_Volumerate
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

    $volumeAttributes = $this->_getVolumeAttributes();

    //Unset volume condition if volume attributes are not specified in
    //carrier config
    if (!$volumeAttributes)
      unset($conditions[self::VOLUME_CONDITION]);

    $shippingTypes = array();

    $totalWeights = array();
    $totalVolumes = array();

    foreach ($request->getAllItems() as $item) {
      $product = $this->_getProduct($item);
      $shippingType = $product->getData('mv_shipping_');

      $qty = $item->getQty();

      $shippingTypes[] = $shippingType;

      if (!isset($totalWeights[$shippingType]))
        $totalWeights[$shippingType] = 0;

      if (!isset($totalVolumes[$shippingType]))
          $totalVolumes[$shippingType] = 0;

      //Convert weight from kilogrammes to tones
       $totalWeights[$shippingType] += $product->getWeight() / 1000 * $qty;

      //Calculate volume if volume condition is allowed
      if (isset($conditions[self::VOLUME_CONDITION]))
        $totalVolumes[$shippingType]
          += $this->_calculateVolume($product, $volumeAttributes) * $qty;
    }

    $totalRate = 0;

    foreach ($shippingTypes as $shippingType) {

      $request
        ->setShippingType($shippingType)
        ->setWeight($totalWeights[$shippingType])
        ->setVolume($totalVolumes[$shippingType]);

      $rate = 0;

      foreach ($conditions as $condition) {

        //Ignore condition if its value is null
        if ($request->getData($condition) === null)
          continue;

        $request->setConditionName($condition);

        $_rate = $this->getRate($request);;

        if (!isset($_rate['price']))
          continue;

        //Rate is per unit of condition value.
        //So we have to multiply it by number of units.
        $_rate['price'] *= $request->getData($condition);

        if ($_rate['price'] > $rate)
          $rate = $_rate['price'];
      }

      $minimalRate = (float) $_rate['min_rate'];

      if ($minimalRate > $rate)
        $rate = $minimalRate;

      $totalRate += $rate;
    }

    $result = Mage::getModel('shipping/rate_result');

    if ($totalRate > 0) {
      $method = Mage::getModel('shipping/rate_result_method');

      $method->setCarrier('volumerate');
      $method->setCarrierTitle($this->getConfigData('title'));

      $method->setMethod('volumeweight');
      $method->setMethodTitle($this->getConfigData('name'));

      $method->setPrice($totalRate);
      $method->setCost(0);

      $result->append($method);
    }

    return $result;
  }

  public function getRate (Mage_Shipping_Model_Rate_Request $request) {
    return Mage::getResourceModel('mventory/carrier_volumerate')
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

  protected function _getProduct ($item) {
    if ($item instanceof Mage_Sales_Model_Quote_Item_Abstract
        || $item instanceof Mage_Sales_Model_Order_Item)
      return $item
               ->setData('product',   null)
               ->getProduct();

    return $item->setQty(1);
  }
}
