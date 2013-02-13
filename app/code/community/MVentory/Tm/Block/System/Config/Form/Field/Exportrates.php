<?php

class MVentory_Tm_Block_System_Config_Form_Field_Exportrates
  extends Mage_Adminhtml_Block_System_Config_Form_Field {

  protected function _getElementHtml (Varien_Data_Form_Element_Abstract $elem) {
    $website = $this
                 ->getRequest()
                 ->getParam('website', '');

    $url = $this->getUrl('mventory_tm/carriers/export', compact('website'))
           . 'shippingrates.csv';

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
