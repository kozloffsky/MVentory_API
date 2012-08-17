<?php

class MVentory_Tm_Model_Config_Data_Websites
  extends Mage_Core_Model_Config_Data {

  protected function _beforeSave () {
    $data = $this->getValue();

    if (!in_array(1, $data)) {
      $data[] = 1;

       $this->setValue($data);
    }

    return parent::_beforeSave();
  }
}

?>
