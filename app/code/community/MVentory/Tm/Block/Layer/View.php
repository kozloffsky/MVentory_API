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
 * Catalog layered navigation view block
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Block_Layer_View extends Mage_Catalog_Block_Layer_View {

  /**
   * Get all fiterable attributes of current category
   *
   * @return array
   */
  protected function _getFilterableAttributes () {
    $attrs = $this->getData('_filterable_attributes');

    if ($attrs != null)
      return $attrs;

    $attrs = parent::_getFilterableAttributes();

    if (!$attrs)
      return $attrs;

    $attrs = $attrs->getItems();

    //Use IDs to filter out sets which are not used in products from
    //current category
    $setIds = $this
                ->getLayer()
                ->getData('_set_ids');

    if ($setIds)
      $setIds = array_fill_keys($setIds, true);

    foreach ($attrs as $id => $attr) {

      //Remember 'price' attribute to add it as first attribute in filter
      if ($attr->getAttributeCode() == 'price') {
        $priceAttr = $attr;

        continue;
      }

      foreach ($attr->getData('attribute_set_info') as $setId => $setInfo)
        if (isset($setIds[$setId]))
          $sets[$setId][] = $id;
    }

    $_attrs = array();

    if (isset($priceAttr))
      $_attrs[$priceAttr->getAttributeId()] = $priceAttr;

    if (isset($sets) && $sets) {

      //Sort sets in descending order by number of attributes in them
      usort($sets, function ($a, $b) {
        return count($b) - count($a);
      });

      foreach ($sets as $attrIds)
        foreach ($attrIds as $id)
          $_attrs[$id] = $attrs[$id];
    }

    $this->setData('_filterable_attributes', $_attrs);

    return $_attrs;
  }
}
