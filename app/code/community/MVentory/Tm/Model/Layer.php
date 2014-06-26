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
 * Catalog view layer model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Layer extends Mage_Catalog_Model_Layer {

  /**
   * Get collection of all filterable attributes for layer products set
   *
   * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute_Collection
   */
  public function getFilterableAttributes () {
    $setIds = $this->_getSetIds();

    if (!$setIds)
      return array();

    //Used in MVentory_API_Block_Layer_View class
    $this->setData('_set_ids', $setIds);

    $collection
      = Mage::getResourceModel('catalog/product_attribute_collection')
          ->setItemObjectClass('catalog/resource_eav_attribute')
          ->setAttributeSetFilter($setIds)
          ->addStoreLabel(Mage::app()->getStore()->getId())
          ->addSetInfo()
          ->setOrder('sort_order', 'ASC');

    return $this
             ->_prepareAttributeCollection($collection)
             ->load();
  }

  /**
   * Add filters to attribute collection
   *
   * @param Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection
   * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection
   */
  protected function _prepareAttributeCollection ($collection) {
    $collection = parent::_prepareAttributeCollection($collection);

    //Filter out attributes which have label equal to '~' or '~ ' or ' ~'
    //in the current store
    $collection
      ->getSelect()
      ->having('store_label NOT IN (\'~\', \'~ \', \' ~\')');

    return $collection;
  }
}
