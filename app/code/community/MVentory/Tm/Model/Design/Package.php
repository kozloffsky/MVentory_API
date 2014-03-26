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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Model for core design package
 *
 * @package MVentory/TM
 */
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
