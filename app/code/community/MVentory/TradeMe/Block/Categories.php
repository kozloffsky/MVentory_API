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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * TradeMe categories table block
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Block_Categories extends Mage_Core_Block_Template
{

  const TYPE_CHECKBOX = 'checkbox';
  const TYPE_RADIO = 'radio';

  const URL = 'http://www.trademe.co.nz';

  private $_categories = null;

  /**
   * Return list of categories
   *
   * @return array
   */
  public function getCategories () {
    if ($this->_categories === null)
      $this->_categories = (new MVentory_TradeMe_Model_Api())->getCategories();

    return $this->_categories;
  }

  /**
   * Calculate required number of columns in table
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

  /**
   * Set type of category selector: single or multiple
   *
   * @param string $type Type of selector
   * @return MVentory_TradeMe_Block_Categories
   */
  public function setInputType ($type) {
    if (!($type == self::TYPE_CHECKBOX || $type == self::TYPE_RADIO))
      $type = self::TYPE_CHECKBOX;

    $this->setData('input_type', $type);

    return $this;
  }
}
