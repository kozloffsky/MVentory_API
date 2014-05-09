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
 * Button for exporting options of TradeMe accounts
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Block_Setting_Options
  extends Mage_Adminhtml_Block_System_Config_Form_Field {

  protected function _getElementHtml (Varien_Data_Form_Element_Abstract $elem) {
    $website = $this
                 ->getRequest()
                 ->getParam('website', '');

    $url = $this->getUrl('trademe/options/export', compact('website'))
           . 'options.csv';

    $data = array(
      'label' => $this->__('Export CSV'),
      'onclick' => 'setLocation(\'' . $url . '\')'
    );

    return $this
             ->getLayout()
             ->createBlock('adminhtml/widget_button')
             ->setData($data)
             ->toHtml();
  }
}
