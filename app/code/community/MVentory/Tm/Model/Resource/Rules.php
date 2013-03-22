<?php

class MVentory_Tm_Model_Resource_Rules
  extends Mage_Core_Model_Resource_Db_Abstract {

  protected $_serializableFields = array(
    'rules' => array(null, array())
  );

  protected function _construct() {
    $this->_init('mventory_tm/matching_rules', 'id');
  }
}
