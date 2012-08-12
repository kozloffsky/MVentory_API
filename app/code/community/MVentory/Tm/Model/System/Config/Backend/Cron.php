<?php
class MVentory_Tm_Model_System_Config_Backend_Cron
  extends Mage_Core_Model_Config_Data {

  const CRON_PATH = 'crontab/jobs/mventory_tm_check/schedule/cron_expr';

  protected function _afterSave () {
    try {
      Mage::getModel('core/config_data')
        ->load(self::CRON_PATH, 'path')
        ->setValue((int)$this->getValue() ? '*/' . (int)$this->getValue() . ' * * * *' : '')
        ->setPath(self::CRON_PATH)
        ->save();
    } catch (Exception $e) {
      throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
    }
  }
}
