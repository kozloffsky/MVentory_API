<?php

/**
 * TM categories table block
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Block_Categories extends Mage_Core_Block_Template {

  private $_selectedCategories = null;

  protected function _construct() {
    parent::_construct();

    $this->setTemplate('tm/categories.phtml');
  }

  /**
   * Return TM categories
   *
   * @return array
   */
  public function getCategories () {
    return Mage::getModel('mventory_tm/connector')
             ->getTmCategories();
  }

  /**
   * Retrieve list of TM categories IDs from the category
   *
   * @return array
   */
  public function getSelectedCategories () {
    if ($this->_selectedCategories)
      return $this->_selectedCategories;

    $this->_selectedCategories = array();

    //Get Magento category. It was set in controller
    $category = $this->getCategory();

    if ($category) {
      $categories = $category->getTmAssignedCategories();

      if ($categories && is_string($categories))
        $this->_selectedCategories = explode(',', $categories);
    }

    return $this->_selectedCategories;
  }

  /**
   * Return TM URL
   *
   * @return string
   */
  public function getTmUrl () {
    return 'http://www.trademe.co.nz';
  }

  /**
   * Calculate required number of columns to show TM categories
   * in a table
   *
   * @return int
   */
  public function getColsNumber () {
    $categories = $this->getCategories();

    $cols = 0;

    foreach ($categories as $category)
      if (count($category['name']) > $cols)
        $cols = count($category['name']);

    return $cols;
  }
}
