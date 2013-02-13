<?php

class MVentory_Tm_Model_System_Config_Backend_Carrier_Volumerate
  extends Mage_Core_Model_Config_Data {

  public function _beforeSave () {
    $this->unsValue();
  }

  public function _afterSave () {
    Mage::getResourceModel('mventory_tm/carrier_volumerate')
      ->uploadAndImport($this);
  }
}
