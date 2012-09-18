<?php

/**
 * Dummy payment method
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Method_Dummy
  extends Mage_Payment_Model_Method_Abstract {

  protected $_code = 'dummy';

  protected $_canAuthorize = true;
  protected $_canUseCheckout = false;
}
