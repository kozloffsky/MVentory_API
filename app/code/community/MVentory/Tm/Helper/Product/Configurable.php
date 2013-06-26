<?php

class MVentory_Tm_Helper_Product_Configurable
  extends MVentory_Tm_Helper_Product {

  public function getIdByChild ($child) {
    $id = $child instanceof Mage_Catalog_Model_Product
            ? $child->getId()
              : $this->getProductId($child);

    if (!$id)
      return $id;

    $configurableType
      = Mage::getResourceSingleton('catalog/product_type_configurable');

    $parentIds = $configurableType->getParentIdsByChild($id);

    //Get first ID because we use only one configurable product
    //per simple product
    return $parentIds ? $parentIds[0] : null;
  }

  public function getSiblingsIds ($product) {
    $id = $product instanceof Mage_Catalog_Model_Product
            ? $product->getId()
              : $this->getProductId($product);

    $configurableId = $this->getIdByChild($id);

    $ids = Mage::getResourceSingleton('catalog/product_type_configurable')
             ->getChildrenIds($configurableId);

    if (!$ids[0])
      return;

    //Unset product'd ID
    unset($ids[0][$id]);

    return $ids[0];
  }
}
