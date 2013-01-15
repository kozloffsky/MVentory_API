<?php

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
