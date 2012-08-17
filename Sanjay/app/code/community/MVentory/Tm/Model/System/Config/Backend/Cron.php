<?php
class MVentory_Tm_Model_System_Config_Backend_Cron
  extends Mage_Core_Model_Config_Data {

  const PATH = 'crontab/jobs/mventory_tm_check/schedule/cron_expr';

  protected function _afterSave () {
    $scope = $this->getScope();
    $scopeId = $this->getScopeId();

    $value = (int) $this->getValue();

    $expression = $value
                    ? '*/' . $value . ' * * * *'
                      : null;

    $model = Mage::getModel('core/config_data');

    $model
      ->setScope($scope)
      ->setScopeId($scopeId)
      ->setPath(self::PATH)
      ->setValue($expression);
      
    try {
      $model->save();

    } catch (Exception $e) {
      throw new Exception(Mage::helper('cron')
                            ->__('Unable to save the cron expression.'));
    }
  }
}
