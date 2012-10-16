<?php

/**
 * Dummy shipping model
 *
 * @category   MVentor
 * @package    MVentory_Tm
 */

class MVentory_Tm_Model_Carrier_Dummyshipping
  extends Mage_Shipping_Model_Carrier_Abstract
  implements Mage_Shipping_Model_Carrier_Interface {

  protected $_code = 'dummyshipping';
  protected $_isFixed = true;

  public function collectRates (Mage_Shipping_Model_Rate_Request $request) {
    if (!((Mage::getSingleton('api/server')->getAdapter() != null
           || Mage::registry('tm_allow_dummyshipping'))
          && $this->getConfigFlag('active')))
      return false;

    $method = Mage::getModel('shipping/rate_result_method');

    $method->setCarrier('dummyshipping');
    $method->setCarrierTitle($this->getConfigData('title'));

    $method->setMethod('dummyshipping');
    $method->setMethodTitle($this->getConfigData('name'));

    $method->setPrice('0.00');
    $method->setCost('0.00');

    $result = Mage::getModel('shipping/rate_result');

    $result->append($method);

    return $result;
  }

  public function getAllowedMethods () {
    return array('dummyshipping' => $this->getConfigData('name'));
  }

}
