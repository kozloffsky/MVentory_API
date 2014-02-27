<?php

$this->startSetup();

$this->addAttribute(
  'customer',
  'mventory_app_profile_key',
  array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'input' => null,
    'required' => 0,
    'unique' => 1,

    //Fields from Mage_Customer_Model_Resource_Setup
    'visible' => 0,
  )
);

$this->endSetup();

?>
