<?php

class MVentory_Tm_Model_Category extends Mage_Catalog_Model_Category {

  const DISPLAY_DESCENDING_PRODUCTS
    = 'mventory_tm/shop-interface/display-descending-products';

  /**
   * Get category products collection
   *
   * The method is redefined to load all descending products when it's allowed
   * and category is not anchor (anchor categories load descending products
   * by default)
   *
   * @return Varien_Data_Collection_Db
   */
  public function getProductCollection () {
    if ($this->getIsAnchor()
        || !Mage::getStoreConfig(self::DISPLAY_DESCENDING_PRODUCTS))
      return parent::getProductCollection();

    return Mage::getResourceModel('catalog/product_collection')
             ->joinField('category_id',
                         'catalog/category_product',
                         'category_id',
                         'product_id = entity_id',
                         null,
                         'left')
             ->addAttributeToFilter('category_id',
                                    array('in' => $this->getAllChildren(true)))
             ->setStoreId($this->getStoreId());
  }
}
