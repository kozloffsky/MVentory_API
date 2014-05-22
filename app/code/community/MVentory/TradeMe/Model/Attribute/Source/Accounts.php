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
 * Source model for account field
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Attribute_Source_Accounts
  extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
  /**
   * Retrieve all options array
   *
   * @return array
   */
  public function getAllOptions () {
    if (is_null($this->_options)) {
      $this->_options = array(
        array(
          'label' => Mage::helper('trademe')->__('Random'),
          'value' =>  null
        )
      );
    }

    return $this->_options;
  }

  /**
   * Retrieve option array
   *
   * @return array
   */
  public function getOptionArray () {
    $_options = array();

    foreach ($this->getAllOptions() as $option)
      $_options[$option['value']] = $option['label'];

    return $_options;
  }

  /**
   * Get a text for option value
   *
   * @param string|integer $value
   * @return string
   */
  public function getOptionText ($value) {
    $options = $this->getAllOptions();

    foreach ($options as $option)
     if ($option['value'] == $value)
      return $option['label'];

    return false;
  }
}
