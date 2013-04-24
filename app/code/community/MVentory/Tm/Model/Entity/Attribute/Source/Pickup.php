<?php

class MVentory_Tm_Model_Entity_Attribute_Source_Pickup
  extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {

  /**
   * Retrieve all options array
   *
   * @return array
   */
  public function getAllOptions () {
    if (is_null($this->_options)) {
      $helper = Mage::helper('mventory_tm');

      $this->_options = array(
        array(
          'label' => $helper->__('Default'),
          'value' => -1
        ),

        array(
          'label' => $helper->__('Buyer can pickup'),
          'value' => MVentory_Tm_Model_Tm::PICKUP_ALLOW,
        ),

        array(
          'label' => $helper->__('Buyer must pickup'),
          'value' => MVentory_Tm_Model_Tm::PICKUP_DEMAND,
        ),

        array(
          'label' => $helper->__('No pickups'),
          'value' => MVentory_Tm_Model_Tm::PICKUP_FORBID,
        ),
      );
    }

    return $this->_options;
  }

  /**
   * Retrieve option array
   *
   * @return array
   */
  public function getOptionArray () {
    $options = array();

    foreach ($this->getAllOptions() as $option)
      $options[$option['value']] = $option['label'];

    return $options;
  }

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    $options = $this->getOptionArray();

    unset($options[-1]);

    return $options;
  }
}

?>
