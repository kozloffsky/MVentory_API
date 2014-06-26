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
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Product store block
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Block_Product_View_Store extends Mage_Core_Block_Template {

  const XML_PATH_STORE_STORE_ADDRESS = 'general/store_information/address';

  protected $_product = null;

  function getProduct () {
    if (!$this->_product)
      $this->_product = Mage::registry('product');

    return $this->_product;
  }

  public function setTemplate ($template) {
    return Mage::app()->getStore()->getWebsite()->getIsDefault()
             ? parent::setTemplate($template)
               : null;
  }

  public function getInfo () {
    $product = $this->getProduct();

    $websiteIds = $product->getWebsiteIds();

    if (!count($websiteIds))
      return false;

    $website = null;

    foreach ($websiteIds as $_websiteId) {
      $website = Mage::app()->getWebsite($_websiteId);

      if ($website->getIsDefault())
        continue;

      break;
    }

    if ($website == null)
      return;

    $store = $website->getDefaultStore();

    $info = array(
      'name' => $store->getConfig('general/store_information/name'),
      'url' => $store->getConfig('web/unsecure/base_url'),
      'address' => $store->getConfig('general/store_information/address'),
      'email' => $store->getConfig('trans_email/ident_support/email'),
      'phone' => $store->getConfig('general/store_information/phone'),
    );

    return $info;
  }
}
