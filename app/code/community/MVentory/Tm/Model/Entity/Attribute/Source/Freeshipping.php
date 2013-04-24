<?php

class MVentory_Tm_Model_Entity_Attribute_Source_Freeshipping
  extends Mage_Eav_Model_Entity_Attribute_Source_Boolean {

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
          'label' => $helper->__('Yes'),
          'value' => MVentory_Tm_Model_Connector::FREE,
        ),

        array(
          'label' => $helper->__('No'),
          'value' => MVentory_Tm_Model_Connector::UNDECIDED,
        ),
      );
    }

    return $this->_options;
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

  /**
   * Get options in "key-value" format
   *
   * @return array
   */
  public function toArray () {
    return array(
      MVentory_Tm_Model_Connector::UNDECIDED => 'Undecided',
      MVentory_Tm_Model_Connector::FREE => 'Free'
    );
  }
}

?>
