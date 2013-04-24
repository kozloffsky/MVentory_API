<?php

class MVentory_Tm_Model_Entity_Attribute_Source_Boolean
  extends Mage_Eav_Model_Entity_Attribute_Source_Boolean {

  /**
   * Retrieve all options array
   *
   * @return array
   */
  public function getAllOptions () {
    if (is_null($this->_options)) {
      parent::getAllOptions();

      array_unshift($this->_options, array(
        'label' => Mage::helper('mventory_tm')->__('Default'),
        'value' => -1
      ));
    }

    return $this->_options;
  }
}
