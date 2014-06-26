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
 * Catalog data helper
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
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
