<?php

class MVentory_Tm_Model_Design_Package extends Mage_Core_Model_Design_Package {
  
  /**
   *  Check $_COOKIE['site_version'] and enable mobile or desktop theme     
   */     
  protected function _checkUserAgentAgainstRegexps($regexpsConfigPath) {      
    if (isset($_COOKIE['site_version'])  
        && $_COOKIE['site_version'] == 'desktop') {  
         
      return false;
    } elseif (isset($_COOKIE['site_version']) 
              && $_COOKIE['site_version'] == 'mobile') {  
                    
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
      setcookie("site_version", 'mobile', time()+3600, "/");
    }         
    return $result;
  }
}
