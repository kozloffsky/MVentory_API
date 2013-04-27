<?php

/**
 * TM categories table block
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Block_Categories extends Mage_Core_Block_Template {

  const TYPE_CHECKBOX = 'checkbox';
  const TYPE_RADIO = 'radio';

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

  public function setInputType ($type) {
    if (!($type == self::TYPE_CHECKBOX || $type == self::TYPE_RADIO))
      $type = self::TYPE_CHECKBOX;

    $this->setData('input_type', $type);

    return $this;
  }
}
