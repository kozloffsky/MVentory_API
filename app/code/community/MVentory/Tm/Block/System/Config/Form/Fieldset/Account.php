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
 * Auhorize button for TM account
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Block_System_Config_Form_Fieldset_Account
  extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

  protected function _getHeaderCommentHtml ($element) {
    $comment = $element->getComment()
                 ? '<div class="comment">' . $element->getComment() . '</div>'
                   : '';

    $accountId = $element->getGroup()->getName();

    $data = array(
      'id' => 'tm_button_auth_' . $accountId,
      'label' => $this->__('Authorize'),
      'onclick' => 'javascript:tm_auth_account(\''
                   . $accountId
                   . '\'); return false;'
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data)
                ->toHtml();

    return '<div style="width: 100%">'
           .  '<div class="form-buttons">'
           .    $button
           .  '</div>'
           .  '<div style="clear: both"></div>'
           . '</div>'
           . $comment;
  }

}
