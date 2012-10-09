<?php

class MVentory_Tm_Model_Design_Package extends Mage_Core_Model_Design_Package {
  
  /**
   *  Check $_COOKIE['site_version'] and enable mobile or desktop theme     
   */     
  protected function _checkUserAgentAgainstRegexps($regexpsConfigPath) {  
    $storeId = Mage::app()->getStore()->getId();    
    if (Mage::getModel('core/cookie')
          ->get('site_version_' . $storeId) == 'desktop') {         
      return false;
    } elseif (Mage::getModel('core/cookie')
                ->get('site_version_' . $storeId) == 'mobile') {  
                    
      $configValueSerialized = Mage::getStoreConfig($regexpsConfigPath, 
                                                    $this->getStore());
      
      if (!$configValueSerialized)
        return false;
      
      $rules = @unserialize($configValueSerialized);
          
      foreach ($rules as $rule) {
        if(strstr($rule['regexp'], 'Mobile') !== false) {
          return $rule['value'];
        }
      }
    }
    $result = parent::_checkUserAgentAgainstRegexps($regexpsConfigPath);
    if($result) {
      Mage::getModel('core/cookie')->set('site_version_' . $storeId, 
                                         'mobile', 
                                         3600 * 24 * 7, 
                                         '/');
      Mage::getSingleton('core/session')->setData('site_version_' . $storeId, 
                                                  'mobile');
    }         
    return $result;
  }
}
