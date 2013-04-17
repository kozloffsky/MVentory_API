<?php

class MVentory_Tm_Model_Entity_Attribute_Source_Accounts
  extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {

  /**
   * Retrieve all options array
   *
   * @return array
   */
  public function getAllOptions () {
    if (is_null($this->_options)) {
      $this->_options = array(
        array(
          'label' => Mage::helper('mventory_tm')->__('Random'),
          'value' =>  null
        )
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
    $_options = array();

    foreach ($this->getAllOptions() as $option)
      $_options[$option['value']] = $option['label'];

    return $_options;
  }

  /**
   * Get a text for option value
   *
   * @param string|integer $value
   * @return string
   */
  public function getOptionText ($value) {
    $options = $this->getAllOptions();

    foreach ($options as $option)
     if ($option['value'] == $value)
      return $option['label'];

    return false;
  }
}
