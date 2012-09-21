<?php

class MVentory_Tm_Helper_Tm extends Mage_Core_Helper_Abstract {

  public function getAttributes ($categoryId) {
    $model = Mage::getModel('mventory_tm/connector');

    $attrs = $model
               ->getTmCategoryAttrs($categoryId);

    if (!(is_array($attrs) && count($attrs)))
      return null;

    foreach ($attrs as &$attr) {
      $attr['Type'] = $model->getAttrTypeName($attr['Type']);
    }

    return $attrs;
  }
}
