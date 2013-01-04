<?php
/**
 * TM accounts block
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Block_System_Config_Form_Field_Accounts
  extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract {

  public function __construct() {
    $helper = Mage::helper('mventory_tm');

    $this->addColumn('name', array(
      'label' => $helper->__('Profile name'),
      'style' => 'width:80px',
    ));

    $this->addColumn('key', array(
      'label' => $helper->__('API Key'),
      'style' => 'width:80px',
    ));

    $this->addColumn('secret', array(
      'label' => $helper->__('API Secret'),
      'style' => 'width:80px',
    ));

    $this->addColumn('max_listings', array(
      'label' => $helper->__('MAX Listings'),
      'style' => 'width:70px',
    ));

    $this->_addAfter = false;
    $this->_addButtonLabel = $helper->__('Add account');

    $this->setTemplate('system/config/form/field/accounts.phtml');

    parent::__construct();
  }

  public function getAuthorizeUrl () {
    $route = 'mventory_tm/adminhtml_tm/authenticateaccount';
    $params = array('website' => $this->getRequest()->getParam('website', ''));

    return $this->getUrl($route, $params);
  }
}
