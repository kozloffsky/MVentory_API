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
 * Source model for list of stores with empty option
 *
 * @package MVentory/Trademe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Setting_Source_Store
  extends Mage_Adminhtml_Model_System_Config_Source_Store
{
  public function toOptionArray () {
    if ($this->_options !== null)
      return $this->_options;

    parent::toOptionArray();

    if (!$this->_options)
      return $this->_options;

    array_unshift($this->_options, array('label' => '', 'value' => ''));

    return $this->_options;
  }
}
