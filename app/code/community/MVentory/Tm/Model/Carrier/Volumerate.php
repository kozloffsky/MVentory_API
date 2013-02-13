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

  protected $_code = 'volumerate';
  protected $_isFixed = true;

  protected $_conditionNames = array('weight', 'volume');

  /**
   * Collect shipping rates
   *
   * @param Mage_Shipping_Model_Rate_Request $request
   * @return Mage_Shipping_Model_Rate_Result
   */
  public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
    if (!$this->getConfigFlag('active'))
      return false;

    $rate = 0;

    foreach ($request->getAllItems() as $item) {
      $product = $item
                   ->setData('product',   null)
                   ->getProduct();

      $request->setWeight($product->getWeight());

      $volume = $product->getSizeHeight()
                * $product->getSizeWidth()
                * $product->getSizeDepth();

      $request->setVolume($volume);

      $itemRate = 0;

      foreach ($this->_conditionNames as $conditionName) {
        $request->setConditionName($conditionName);

        $_rate = $this->getRate($request);

        if (!empty($_rate) && $_rate['price'] > $itemRate)
          $itemRate = $_rate['price'];
      }

      $rate += $itemRate * $item->getQty();
    }

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

}
