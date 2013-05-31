<?php

class MVentory_Tm_Model_System_Config_Source_Allowedshippingtypes {

  protected $_options = array();

  public function __construct () {
    $attribute = Mage::getModel('eav/entity_attribute')
                   ->loadByCode(Mage_Catalog_Model_Product::ENTITY,
                                'mv_shipping_');

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

  /**
   * Return options as id => label array
   *
   * @return array
   */
  public function toArray () {
    $options = array();

    foreach ($this->_options as $option)
      $options[$option['value']] = $option['label'];

    return $options;
  }
}

?>
