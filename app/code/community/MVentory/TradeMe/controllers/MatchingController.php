<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Controller for category mapping rules
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_MatchingController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_TradeMe');
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

    Mage::getModel('trademe/matching')
      ->loadBySetId($setId, false)
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

    Mage::getModel('trademe/matching')
      ->loadBySetId($setId, false)
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

    Mage::getModel('trademe/matching')
      ->loadBySetId($setId, false)
      ->reorder($ids)
      ->save();

    echo 1;
  }
}
