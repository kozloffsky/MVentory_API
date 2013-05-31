<?php

class MVentory_Tm_Model_System_Config_Backend_Options
  extends Mage_Core_Model_Config_Data {

  public function _beforeSave () {
    $this->unsValue();
  }

  public function _afterSave () {
    Mage::helper('mventory_tm/tm')->importOptions($this);
  }
}
