<?php

class MVentory_Tm_SwitchController
  extends Mage_Core_Controller_Front_Action {

  public function toMobileAction() {
    $storeId = Mage::app()->getStore()->getId();
    Mage::getModel('core/cookie')->set('site_version_' . $storeId,
                                       'mobile',
                                       3600 * 24 * 7,
                                       '/');
    if($this->_isUrlInternal($this->_getRefererUrl())) {
      return $this->_redirectUrl($this->_getRefererUrl());
    } else {
      return $this->_redirect("/");
    }
  }

  public function toDesktopAction() {
    $storeId = Mage::app()->getStore()->getId();
    Mage::getModel('core/cookie')->set('site_version_' . $storeId,
                                       'desktop',
                                       3600 * 24 * 7,
                                       '/');
    if($this->_isUrlInternal($this->_getRefererUrl())) {
      return $this->_redirectUrl($this->_getRefererUrl());
    } else {
      return $this->_redirect("/");
    }
  }
}
