<?php

class MVentory_Tm_Model_System_Config_Source_Conditions {

  protected $_options = array();

  public function __construct () {
    $attribute = Mage::getModel('eav/entity_attribute')
                   ->loadByCode(Mage_Catalog_Model_Product::ENTITY,
                                'mv_condition_');

    if ($attribute->getId())
      $this->_options = $attribute
                          ->getSource()
                          ->getAllOptions(false);
  }

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    return $this->_options;
  }
}

?>
