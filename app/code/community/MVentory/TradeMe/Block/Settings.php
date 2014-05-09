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
 * TradeMe settings fieldset renderer
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Block_Settings
  extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
  protected function _getExtraJs ($element, $tooltipsExist = false) {
    $js = "
      function trademe_auth_account (account_id) {
        if ($('trademe_button_auth_' + account_id).hasClassName('disabled'))
          return;

        new Ajax.Request('" . $this->getAuthorizeUrl() . "', {
          method: 'post',
          parameters: { account_id: account_id },
          onSuccess: function(transport) {
            if (transport.responseText.isJSON()) {
              var response = transport.responseText.evalJSON()

              if (response.error)
                alert(response.message);

              if (response.ajaxRedirect)
                setLocation(response.ajaxRedirect);
            } else
              alert('" . $this->__('An error occured while retrieving response') . "');
          },
          onFailure: function() {
            alert('" . $this->__('An error occured while retrieving response') . "');
          }
        });
      }";

    return parent::_getExtraJs($element, $tooltipsExist)
           . Mage::helper('adminhtml/js')->getScript($js);
  }

  public function getAuthorizeUrl () {
    $route = 'trademe/account/authenticate';
    $params = array('website' => $this->getRequest()->getParam('website', ''));

    return $this->getUrl($route, $params);
  }

}
