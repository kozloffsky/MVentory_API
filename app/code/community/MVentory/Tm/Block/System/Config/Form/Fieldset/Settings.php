<?php

class MVentory_Tm_Block_System_Config_Form_Fieldset_Settings
  extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

  protected function _getExtraJs ($element, $tooltipsExist = false) {
    $js = "
      function tm_auth_account (account_id) {
        if ($('tm_button_auth_' + account_id).hasClassName('disabled'))
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
    $route = 'mventory_tm/adminhtml_tm/authenticateaccount';
    $params = array('website' => $this->getRequest()->getParam('website', ''));

    return $this->getUrl($route, $params);
  }

}
