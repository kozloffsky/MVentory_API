<?php

/**
 * Collection for the volume based shipping carrier model
 *
 * @category   MVentor
 * @package    MVentory_Tm
 */

class MVentory_Tm_Model_Resource_Carrier_Volumerate_Collection
  extends Mage_Shipping_Model_Resource_Carrier_Tablerate_Collection {

  /**
   * Define resource model and item
   */
  protected function _construct () {
    $this->_init('mventory_tm/carrier_volumerate');

    $this->_shipTable = $this->getMainTable();
    $this->_countryTable = $this->getTable('directory/country');
    $this->_regionTable = $this->getTable('directory/country_region');
  }
}
