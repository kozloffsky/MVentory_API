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
 * Source model for shipping type field
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Attribute_Source_Freeshipping
  extends Mage_Eav_Model_Entity_Attribute_Source_Boolean
{
  /**
   * Retrieve all options array
   *
   * @return array
   */
  public function getAllOptions () {
    if (is_null($this->_options)) {
      $helper = Mage::helper('trademe');

      $this->_options = array(
        array(
          'label' => $helper->__('Default'),
          'value' => -1
        ),

        array(
          'label' => $helper->__('Yes'),
          'value' => MVentory_TradeMe_Model_Config::SHIPPING_FREE,
        ),

        array(
          'label' => $helper->__('No'),
          'value' => MVentory_TradeMe_Model_Config::SHIPPING_UNDECIDED,
        ),
      );
    }

    return $this->_options;
  }

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    $options = $this->getOptionArray();

    unset($options[-1]);

    return $options;
  }

  /**
   * Get options in "key-value" format
   *
   * @return array
   */
  public function toArray () {
    return array(
      MVentory_TradeMe_Model_Config::SHIPPING_UNDECIDED => 'Undecided',
      MVentory_TradeMe_Model_Config::SHIPPING_FREE => 'Free'
    );
  }
}

?>
