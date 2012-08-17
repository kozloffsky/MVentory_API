<?php

class MVentory_Tm_Model_Category extends Mage_Catalog_Model_Category {
  /**
   * Get category products collection
   *
   * @return Varien_Data_Collection_Db
   */
  public function getProductCollection()
  {
    $displayDescendingProducts = (bool) Mage::getStoreConfig(
            'mventory_tm/shop-interface/display-descending-products');

    if ($displayDescendingProducts) {
      $ids = array($this->getId());

      $sub = Mage::getModel('catalog/category')->getCategories($this->getId());
      foreach ($sub as $cat) {
        $ids[] = $cat->getId();
        $sub2 = Mage::getModel('catalog/category')->getCategories($cat->getId());
        foreach ($sub2 as $cat2) {
          $ids[] = $cat2->getId();
        }
      }

      $products = array();
      
      foreach ($ids as $id) {
        $collection = Mage::getResourceModel('catalog/product_collection')
          ->joinField('category_id', 'catalog/category_product', 'category_id', 'product_id = entity_id', null, 'left')
          ->addAttributeToFilter('category_id', $id)
          ->setStoreId($this->getStoreId());
        
        $products = array_merge($products, $collection->getAllIds());                
      }
      
      $collection = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('entity_id', array('in' => $products))
        ->setStoreId($this->getStoreId());
    } else {
      $collection = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId($this->getStoreId())
        ->addCategoryFilter($this);
    }
    return $collection;
  }
}
