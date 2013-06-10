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
    $attribute = $this->getAttributeModel();
    $layer = $this->getLayer();
    $aggregator = $layer->getAggregator();

    $this->_requestVar = $attribute->getAttributeCode();

    $key = $layer->getStateKey() . '_' . $this->_requestVar;
    $data = $aggregator->getCacheData($key);

    if ($data === null) {
      $data = parent::_getItemsData();

      foreach ($data as $id => $option) {
        $label = strtolower(trim($option['label']));

        if ($label == '' || $label == 'n/a' || $label == 'none')
          unset($data[$id]);
      }

      usort($data, array($this, '_compareLabels'));

      $tags = $layer->getStateTags(
        array(
          Mage_Eav_Model_Entity_Attribute::CACHE_TAG . ':' . $attribute->getId()
        )
      );

      $aggregator->saveCacheData($data, $key, $tags);
    }

    return $data;
  }
}
