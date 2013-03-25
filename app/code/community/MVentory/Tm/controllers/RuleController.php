<?php

/**
 * Carriers controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_RuleController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Append rule action
   */
  public function appendAction () {
    $request = $this->getRequest();

    $setId =  $request->getParam('set_id');

    $rule = $request->getParam('rule');

    if (!$rule)
      return 0;

    $rule = Mage::helper('core')->jsonDecode($rule);

    Mage::getModel('mventory_tm/rules')
      ->loadBySetId($setId)
      ->append($rule)
      ->save();

    echo 1;
  }
}
