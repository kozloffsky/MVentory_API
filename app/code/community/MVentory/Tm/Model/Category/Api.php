<?php

class MVentory_Tm_Model_Category_Api extends Mage_Catalog_Model_Category_Api
{

  private function removeInactive(&$tree)
  {
    foreach($tree['children'] as $idx => &$child)
    {
      if (isset($child['is_active']) && $child['is_active'] == 1) {
        removeInactive($child);
      } else {
        unset($tree['children'][$idx]);
      }
    }
    $tree['children'] = array_values($tree['children']);
  }

  public function treeActiveOnly()
  {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId(null);

    $model = Mage::getModel("catalog/category_api");
    $tree = $model->tree(null, $storeId);

    removeInactive($tree);

    return $tree;
  }

}
