<?php

/**
 * TM categories
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Block_Catalog_Category_Tab_Tm
  extends Mage_Adminhtml_Block_Widget {

  public function __construct() {
    parent::__construct();

    $this->setTemplate('catalog/category/edit/tab/tm.phtml');
  }

  public function getCategory () {
    return Mage::registry('category');
  }

  public function getCategories () {
    return Mage::getModel('mventory_tm/connector')
             ->getTmCategories();
  }

  public function getSelectedCategories () {
    $category = $this->getCategory();

    $categories = $category->getTmAssignedCategories();

    if (!($categories && is_string($categories)))
      return array();

    return explode(',', $categories);
  }

  public function getColsNumber () {
    $categories = $this->getCategories();

    $cols = 0;

    foreach ($categories as $id => $names)
      if (count($names) > $cols)
        $cols = count($names);

    return $cols;
  }
}

