<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Layer attribute filter
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Layer_Filter_Attribute
  extends Mage_Catalog_Model_Layer_Filter_Attribute {

  const SORT_GROUPED = 'grouped';
  const SORT_ALPHABETICAL = 'alphabetical';

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

  protected function _compareCounts ($left, $right) {
    return (int) $left['count'] < (int) $right['count'];
  }

  protected function _getSortMethod () {
    $method = $this
                ->getLayer()
                ->getData('mventory_filter_sort_method');

    if (!$method)
      return self::SORT_GROUPED;

    if (!($method == self::SORT_GROUPED || $method == self::SORT_ALPHABETICAL))
      return self::SORT_GROUPED;

    return $method;
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

        if ($label == ''
            || $label == 'n/a'
            || $label == 'none'
            || $label == '~')
          unset($data[$id]);
      }

      $sortMethod = $this->_getSortMethod();


      if ($sortMethod == self::SORT_GROUPED && count($data) > 10) {
        usort($data, array($this, '_compareCounts'));

        $top = array_slice($data, 0, 10);
        $other = array_slice($data, 10);

        usort($top, array($this, '_compareLabels'));
        usort($other, array($this, '_compareLabels'));

        $data = array_merge($top, $other);
      } else
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
