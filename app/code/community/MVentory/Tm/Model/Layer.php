<?php

/**
 * Catalog view layer model
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Layer extends Mage_Catalog_Model_Layer {

  /**
   * Get collection of all filterable attributes for layer products set
   *
   * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute_Collection
   */
  public function getFilterableAttributes () {
    $setIds = $this->_getSetIds();

    if (!$setIds)
      return array();

    //Used in MVentory_Tm_Block_Layer_View class
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
