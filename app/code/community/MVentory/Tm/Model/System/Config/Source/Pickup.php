<?php

class MVentory_Tm_Model_System_Config_Source_Pickup {

  //TM pickup options mapping
  protected $_options = array(
    //MVentory_Tm_Model_Tm::PICKUP_NONE => 'None',
    MVentory_Tm_Model_Tm::PICKUP_ALLOW => 'Buyer can pickup',
    MVentory_Tm_Model_Tm::PICKUP_DEMAND => 'Buyer must pickup',
    MVentory_Tm_Model_Tm::PICKUP_FORBID => 'No pickups',
  );

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray ($default = false) {
    $helper = Mage::helper('mventory_tm');

    $options = array();

    if ($default)
      $options[-1] = array('value' => -1, 'label' => $helper->__('Default'));

    foreach ($this->_options as $value => $code)
      $options[] = array('value' => $value, 'label' => $helper->__($code));

    return $options;
  }
  
  /**
   * Get options in "key-value" format
   *
   * @return array
   */
  public function toArray () {
    return $this->_options;
  }
}

?>
