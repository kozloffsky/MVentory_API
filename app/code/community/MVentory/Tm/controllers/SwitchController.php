<?php

class MVentory_Tm_SwitchController
  extends Mage_Core_Controller_Front_Action {

  public function toMobileAction() {
    setcookie("site_version", "mobile", time() + 3600 * 24 * 7, "/");
    if($this->_isUrlInternal($this->_getRefererUrl())) {
      return $this->_redirectUrl($this->_getRefererUrl()); 
    } else {
      return $this->_redirect("/");
    }
  }
    
  public function toDesktopAction() {
    setcookie("site_version", "desktop", time() + 3600 * 24 * 7, "/");
    if($this->_isUrlInternal($this->_getRefererUrl())) {
      return $this->_redirectUrl($this->_getRefererUrl()); 
    } else {
      return $this->_redirect("/");
    }
  }
}
