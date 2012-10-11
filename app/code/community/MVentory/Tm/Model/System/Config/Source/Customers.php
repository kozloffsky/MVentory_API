<?php

/**
 * Source model for customers config option
 *
 * @category   MVentor
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Model_System_Config_Source_Customers {

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    $customers = Mage::getResourceModel('customer/customer_collection')
                   ->addNameToSelect()
                   ->load();

    $options = array();

    foreach ($customers as $customer)
      $options[] = array(
        'value' => $customer->getId(),
        'label' => $customer->getName() . ' ('. $customer->getEmail() . ')'
      );

    return $options;
  }
}

?>
