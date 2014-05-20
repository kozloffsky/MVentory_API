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
 * Backend model for cron field
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Setting_Backend_Cron
  extends Mage_Core_Model_Config_Data
{

  const PATH = 'crontab/jobs/trademe_sync_';

  /**
   * Create or remove cron records from config after saving cron interval
   *
   * @return MVentory_TradeMe_Model_Settings_Backend_Cron
   */
  protected function _afterSave () {

    //Create cron records for websites only
    if ($this->getScope() != 'websites')
      return $this;

    $code = $this->_getWebsiteCode();

    $value = (int) $this->getValue();

    //Remove cron records when the value of cron interval is empty or 0
    if (!$value) {
      $this->_removeCronRecords($code);

      return $this;
    }

    //Add website code as suffix to cron job's code
    $path = self::PATH . $code;

    $node = array(
      $path . '/run/model' => 'trademe/observer::sync',
      $path . '/schedule/cron_expr' => '*/' . $value . ' * * * *',
      //Store website code in cron record
      $path . '/website' => $code
    );

    //Save cron records in Magento config
    foreach ($node as $path => $value)
      try {
        $data = array(
          'scope' => 'default',
          'scope_id' => 0,
          'path' => $path,
          'value' => $value
        );

        Mage::getModel('core/config_data')
          ->setData($data)
          ->save();
      } catch (Exception $e) {
        throw new Exception(
          Mage::helper('trademe')->__('Unable to save the cron expression.')
        );
      }

    return $this;
  }


  /**
   * Remove cron records from config after settinh website's cron interval
   * to use default value
   *
   * @return MVentory_TradeMe_Model_Settings_Backend_Cron
   */
  protected function _afterDelete () {
    if ($this->getScope() != 'websites')
      return;

    $this->_removeCronRecords($this->_getWebsiteCode());

    return $this;
  }

  /**
   * Remove cron records for specified website
   *
   * $param string $websiteCode - Code of the website
   *
   * @return null
   */
  private function _removeCronRecords ($websiteCode) {
    $records = $this
                 ->getCollection()
                 ->addPathFilter(self::PATH . $websiteCode);

    foreach ($records as $record)
      $record->delete();
  }

  /**
   * Return selected website code
   *
   * @return string
   */
  private function _getWebsiteCode() {
    return Mage::getModel('core/website')
             ->load($this->getScopeId())
             ->getCode();
  }
}
