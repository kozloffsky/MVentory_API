<?php

/**
 * Source model for stores which includes empty option
 *
 * @category   MVentor
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Model_System_Config_Source_Store
  extends Mage_Adminhtml_Model_System_Config_Source_Store {

  public function toOptionArray () {
    if ($this->_options !== null)
      return $this->_options;

    parent::toOptionArray();

    if (!$this->_options)
      return $this->_options;

    array_unshift($this->_options, array('label' => '', 'value' => ''));

    return $this->_options;
  }
}
