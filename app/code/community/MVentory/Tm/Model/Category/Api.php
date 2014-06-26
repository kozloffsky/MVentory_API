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
 * Catalog category api
 *
 * @package MVentory/API
 */
class MVentory_API_Model_Category_Api extends Mage_Catalog_Model_Category_Api
{

  private function removeInactive(&$tree)
  {
    foreach($tree['children'] as $idx => &$child)
    {
      if (isset($child['is_active']) && $child['is_active'] == 1) {
        $this->removeInactive($child);
      } else {
        unset($tree['children'][$idx]);
      }
    }
    $tree['children'] = array_values($tree['children']);
  }

  public function treeActiveOnly()
  {
    $storeId = Mage::helper('mventory')->getCurrentStoreId(null);

    $model = Mage::getModel("catalog/category_api");
    $tree = $model->tree(null, $storeId);

    $this->removeInactive($tree);

    return $tree;
  }

}
