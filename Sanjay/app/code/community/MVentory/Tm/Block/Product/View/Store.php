<?php

/**
 * Store description block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
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
