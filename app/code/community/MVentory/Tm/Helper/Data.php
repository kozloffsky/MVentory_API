<?php

class MVentory_Tm_Helper_Data extends Mage_Core_Helper_Abstract {

  const ADD_TO_WEBSITES_PATH = 'mventory_tm/api/add_to_websites';

  protected $_baseMediaUrl = null;

  public function getCurrentWebsite () {
    $params =  Mage::registry('application_params');

    $hasScopeCode = isset($params)
                      && is_array($params)
                      && isset($params['scope_code'])
                      && $params['scope_code'];

    return $hasScopeCode
             ? Mage::app()->getWebsite($params['scope_code'])
               : Mage::app()->getStore(true)->getWebsite();
  }

  public function getCurrentStoreId ($storeId = null) {
    if ($storeId)
      return $storeId;

    $website = $this->getCurrentWebsite();

    return $website && $website->getId()
             ? $website->getDefaultStore()->getId() : true;
  }

  /**
   * Retrieve website which current or specified API user is assigned with
   *
   * @param Mage_Api_Model_User $user API user
   *
   * @return null|Mage_Core_Model_Website
   */
  public function getApiUserWebsite ($user = null) {
    if (!$user) {
      $session = Mage::getSingleton('api/session');

      if (!$session->isLoggedIn())
        return null;

      $user = $session->getUser();
    }

    if (!$customerId = (int) $user->getUsername())
      return null;

    $customer = Mage::getModel('customer/customer')->load($customerId);

    if (!$customer->getId())
      return null;

    if (($websiteId = $customer->getWebsiteId()) === null)
      return null;

    return Mage::app()->getWebsite($websiteId);
  }

  public function getWebsitesForProduct () {
    $website = $this->getCurrentWebsite();

    $websites = $this->getConfig(self::ADD_TO_WEBSITES_PATH, $website);
    $websites = explode(',', $websites);

    $website = $website->getId();

    if (!in_array($website, $websites))
      $websites[] = $website;

    return $websites;
  }

  public function getWebsiteIdFromProduct ($product) {
    $ids = $product->getWebsiteIds();

    for ($id = reset($ids); $id && $id == 1; $id = next($ids));

    return $id === false ? null : $id;
  }

  /**
   * Retrieve attribute's raw value from DB for specified product's ID.
   *
   * @param int $productId 
   * @param int|string|array $attribute atrribute's IDs or codes
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   * 
   * @return bool|string|array
   */
  public function getAttributesValue ($productId, $attribute, $website = 0) {
    $store = Mage::app()
               ->getWebsite($website)
               ->getDefaultStore();

    return Mage::getResourceModel('mventory_tm/product')
             ->getAttributeRawValue($productId, $attribute, $store);
  }

  /**
   * Update attribute values for product per website
   *
   * @param int $productId
   * @param array $attrData
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   */
  public function setAttributesValue ($productId, $attrData, $website = 0) {
    $storeId = Mage::app()
                 ->getWebsite($website)
                 ->getDefaultStore()
                 ->getId();

    Mage::getResourceSingleton('catalog/product_action')
      ->updateAttributes(array($productId), $attrData, $storeId);
  }

  /**
   * Return website which the product are assigned to
   *
   * @param Mage_Catalog_Model_Product|int $product
   *
   * @return Mage_Core_Model_Website
   */
  public function getWebsite ($product = null) {
    if ($product == null) {
      $product = Mage::registry('product');

      if (!$product)
        return Mage::app()->getWebsite();
    }

    if ($product instanceof Mage_Catalog_Model_Product)
      $ids = $product->getWebsiteIds();
    else
      $ids = Mage::getResourceModel('catalog/product')
               ->getWebsiteIds($product);

    for ($id = reset($ids); $id && $id == 1; $id = next($ids));

    return Mage::app()->getWebsite($id === false ? null : $id);
  }

  /**
   * Return website's base media URL
   *
   * @param Mage_Core_Model_Website $wesite
   *
   * @return string Base media URL
   */
  public function getBaseMediaUrl ($website) {
    if ($this->_baseMediaUrl)
      return $this->_baseMediaUrl;

    $type = Mage_Core_Model_Store::URL_TYPE_MEDIA;

    $secureFlag = Mage::app()->getStore()->isCurrentlySecure()
                    ? 'secure'
                      : 'unsecure';

    $path = 'web/' . $secureFlag . '/base_' . $type . '_url';

    $this->_baseMediaUrl = $this->getConfig($path, $website);

    return $this->_baseMediaUrl;
  }

  public function getConfig ($path, $website) {
    $website = Mage::app()->getWebsite($website);

    $config = $website->getConfig($path);

    if ($config === false)
      $config = (string) Mage::getConfig()->getNode('default/' . $path);

    return $config;
  }

  public function isAdminLogged () {
    return Mage::registry('is_admin_logged') === true;
  }

  public function isSandboxMode ($websiteId) {
    $path = MVentory_Tm_Model_Connector::SANDBOX_PATH;

    return $this->getConfig($path, $websiteId) == true;
  }
  
  public function isMobile () {
    $storeId = $this->getCurrentStoreId();
    $code = 'site_version_' . $storeId;
    if(Mage::getModel('core/cookie')->get($code) == 'mobile' || 
       (Mage::getModel('core/cookie')->get($code) === false &&
        Mage::getSingleton('core/session')->getData($code) == 'mobile'))
      return true;
      
    return false;
  }

  /**
   * Sends email to website's general contant address
   *
   * @param string $subject
   * @param string $message
   * @param int|string|Mage_Core_Model_Website $website Website, its ID or code
   */
  public function sendEmail ($subject, $message, $website) {
    $email = $this->getConfig('trans_email/ident_general/email', $website);
    $name = $this->getConfig('trans_email/ident_general/name', $website);

    $host = $_SERVER['SERVER_NAME'];

    Mage::getModel('core/email')
      ->setToName($name)
      ->setToEmail($email)
      ->setBody($message)
      ->setSubject($subject)
      ->setFromEmail('magento@' . $host)
      ->send();
  }
}
