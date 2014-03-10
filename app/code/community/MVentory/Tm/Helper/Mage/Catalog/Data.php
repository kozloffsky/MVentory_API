<?php

class MVentory_Tm_Helper_Mage_Catalog_Data extends Mage_Catalog_Helper_Data {

  /**
   * Same as Mage_Catalog_Helper_Data::getBreadcrumbPath(), but returns
   * current category as link
   *
   * @return array
   */
  public function getBreadcrumbPath () {
    if ($this->_categoryPath)
      return $this->_categoryPath;

    $path = array();

    if ($category = $this->getCategory()) {
      $pathInStore = $category->getPathInStore();
      $pathIds = array_reverse(explode(',', $pathInStore));

      $categories = $category->getParentCategories();

      //Add category path breadcrumb
      foreach ($pathIds as $categoryId)
        if (isset($categories[$categoryId])
            && ($name = $categories[$categoryId]->getName()))

          $path['category' . $categoryId] = array(
            'label' => $name,
            'link' => $categories[$categoryId]->getUrl()
          );
    }

    if ($product = $this->getProduct())
      $path['product'] = array('label' => $product->getName());

    $this->_categoryPath = $path;

    return $this->_categoryPath;
  }
}
