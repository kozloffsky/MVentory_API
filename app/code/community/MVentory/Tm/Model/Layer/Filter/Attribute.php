<?php

class MVentory_Tm_Model_Layer_Filter_Attribute
  extends Mage_Catalog_Model_Layer_Filter_Attribute {

  protected function _compareLabels ($left, $right) {
    $left = $left['label'];
    $right = $right['label'];

    $leftNotNumeric = !is_numeric($left);
    $rightNotNumeric = !is_numeric($right);

    if ($leftNotNumeric && $rightNotNumeric)
      return strcmp($left, $right);

    if (!($leftNotNumeric || $rightNotNumeric)) {
      $left = (float) $left;
      $right = (float) $right;

      return $left > $right ? 1 : ($left == $right ? 0 : -1);
    }

    return $leftNotNumeric - $rightNotNumeric;
  }

  /**
   * Get data array for building attribute filter items
   *
   * @return array
   */
  protected function _getItemsData () {
    $data = parent::_getItemsData();

    usort($data, array($this, '_compareLabels'));

    return $data;
  }
}
