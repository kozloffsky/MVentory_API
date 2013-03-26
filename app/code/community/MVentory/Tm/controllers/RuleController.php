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

  /**
   * Append rule action
   */
  public function removeAction () {
    $request = $this->getRequest();

    $setId = $request->getParam('set_id');
    $ruleId = $request->getParam('rule_id');

    if (!($setId && $ruleId))
      return 0;

    Mage::getModel('mventory_tm/rules')
      ->loadBySetId($setId)
      ->remove($ruleId)
      ->save();

    echo 1;
  }

  /**
   * Reorder rules action
   */
  public function reorderAction () {
    $request = $this->getRequest();

    $setId = $request->getParam('set_id');
    $ids = $request->getParam('ids');

    if (!($setId && $ids))
      return 0;

    Mage::getModel('mventory_tm/rules')
      ->loadBySetId($setId)
      ->reorder($ids)
      ->save();

    echo 1;
  }
}
