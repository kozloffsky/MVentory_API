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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Source model for Add product to websites setting
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

class MVentory_Tm_Model_System_Config_Source_Addtowebsite
  extends Mage_Adminhtml_Model_System_Config_Source_Website
{
  public function toOptionArray () {
    if ($this->_options)
      return $this->_options;

    $this->_options = array(
      array('value' => '', 'label' => 'Do not list on any other website/store')
    );

    $current = Mage::helper('mventory_tm')
      ->getCurrentWebsite()
      ->getId();

    foreach (Mage::app()->getWebsites() as $website)
      if (($id = $website->getId()) && $id != $current)
        $this->_options[] = array(
          'value' => $id,
          'label' => $website->getName()
        );

    return $this->_options;

  }
}
