<?php

/**
 * TM categories
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Block_Catalog_Product_Edit_Tab_Tm
  extends Mage_Adminhtml_Block_Widget {

  private $_selectedCategories = null;

  private $_tmListingUrl = null;
  private $_productUrl = null;

  public function __construct() {
    parent::__construct();

    $this->setTemplate('catalog/product/tab/tm.phtml');
  }

  public function getProduct () {
    return Mage::registry('current_product');
  }

  public function getCategory () {
    $categories = $this
                    ->getProduct()
                    ->getCategoryIds();

    if (!count($categories))
      return null;

    $category = Mage::getModel('catalog/category')->load($categories[0]);

    if ($category->getId())
      return $category;

    return null;
  }

  public function getCategories () {
    return Mage::getModel('mventory_tm/connector')
             ->getTmCategories();
  }

  public function getSelectedCategories () {
    if ($this->_selectedCategories)
      return $this->_selectedCategories;

    $this->_selectedCategories = array();

    $category = $this->getCategory();

    if ($category) {
      $categories = $category->getTmAssignedCategories();

      if ($categories && is_string($categories))
        $this->_selectedCategories = explode(',', $categories);
    }

    return $this->_selectedCategories;
  }

  public function getColsNumber () {
    $categories = $this->getCategories();

    $cols = 0;

    foreach ($categories as $category)
      if (count($category['name']) > $cols)
        $cols = count($category['name']);

    return $cols;
  }

  public function getTmListingUrl () {
    if ($this->_tmListingUrl)
      return $this->_tmListingUrl;

    $helper = Mage::helper('mventory_tm');
    $product = $this->getProduct();

    $websiteId = $helper->getWebsiteIdFromProduct($product);

    $domain = $helper->isSandboxMode($websiteId)
                ? 'tmsandbox'
                  : 'trademe';

    $id = $product->getTmListingId();

    return $this->_tmListingUrl = 'http://www.'
                                  . $domain
                                  . '.co.nz/Browse/Listing.aspx?id='
                                  . $id;
  }

  public function getProductUrl () {
    if ($this->_productUrl)
      return $this->_productUrl;

    $helper = Mage::helper('mventory_tm');
    $product = $this->getProduct();

    $baseUrl = Mage::app()
                 ->getWebsite($helper->getWebsiteIdFromProduct($product))
                 ->getConfig('web/unsecure/base_url');

    return $this->_productUrl = rtrim($baseUrl, '/')
                                . '/'
                                . $product->getUrlPath($this->getCategory());
  }

  public function getTmUrl () {
    return 'http://www.trademe.co.nz';
  }

  public function getUrlTemplates () {
    $productId = $this
                   ->getProduct()
                   ->getId();

    $submit = $this->getUrl('mventory_tm/adminhtml_index/submit/',
                            array('id' => $productId));

    $categories = $this->getUrl('mventory_tm/adminhtml_tm/categories/',
                                array('product_id' => $productId));

    return Zend_Json::encode(compact('submit', 'categories'));
  }

  public function getSubmitButton () {
    $label = $this->__('Submit');
    $class = count($this->getSelectedCategories()) != 1
               ? 'disabled'
                 : '';

    return $this->getButtonHtml($label, null, $class, 'tm_submit_button');
  }

  public function getStatusButton () {
    $label = $this->__('Check status');
    $onclick = 'setLocation(\''
               . $this->getUrl('mventory_tm/adminhtml_index/check/',
                               array('id' => $this->getProduct()->getId()))
               . '\')';

    return $this->getButtonHtml($label, $onclick, '', 'tm_status_button');
  }

  public function getRemoveButton () {
    $label = $this->__('Remove');
    $onclick = 'setLocation(\''
               . $this->getUrl('mventory_tm/adminhtml_index/remove/',
                               array('id' => $this->getProduct()->getId()))
               . '\')';

    return $this->getButtonHtml($label, $onclick, '', 'tm_remove_button');
  }

  public function getCategoriesButton () {
    $label = $this->__('Show all categories');

    return $this->getButtonHtml($label, null, '', 'tm_categories_button');
  }

  public function getPreparedAttributes ($tmCategoryId) {
    $attributes = Mage::helper('mventory_tm/tm')->getAttributes($tmCategoryId);

    if (!($attributes && count($attributes)))
      return;

    $product = $this->getProduct();

    $existing = array();
    $missing = array();
    $optional = array();

    foreach ($attributes as $attribute) {
      if (!$attribute['IsRequiredForSell']) {
        $optional[] = $attribute;

        continue;
      }
  
      $name = strtolower($attribute['Name']);

      if ($product->hasData($name) || $product->hasData($name . '_'))
        $existing[] = $attribute;
      else
        $missing[] = $attribute;
    }

    return compact('existing', 'missing', 'optional');
  }

  public function getAllowBuyNow () {
    $helper = Mage::helper('mventory_tm');

    $path = MVentory_Tm_Model_Connector::BUY_NOW_PATH;
    $website = $helper->getWebsiteIdFromProduct($this->getProduct());

    return $helper->getConfig($path, $website);
  }

  public function getAddTmFees () {
    $helper = Mage::helper('mventory_tm');

    $path = MVentory_Tm_Model_Connector::ADD_TM_FEES_PATH;
    $website = $helper->getWebsiteIdFromProduct($this->getProduct());

    return $helper->getConfig($path, $website);
  }

  public function getRelist () {
    $product = $this->getProduct();

    if ($product->getTmRelist() != null)
      return $product->getTmRelist();

    $helper = Mage::helper('mventory_tm');

    $path = MVentory_Tm_Model_Connector::RELIST_IF_NOT_SOLD_PATH;
    $website = $helper->getWebsiteIdFromProduct($product);

    return $helper->getConfig($path, $website);
  }

  public function getShippingOptions () {
    $helper = Mage::helper('mventory_tm');

    $path = MVentory_Tm_Model_Connector::SHIPPING_TYPE_PATH;
    $website = $helper->getWebsiteIdFromProduct($this->getProduct());

    $shippingType = $helper->getConfig($path, $website);

    $options = Mage::getModel('mventory_tm/system_config_source_shippingtype')
                 ->toOptionArray();

    foreach ($options as &$option)
      $option['selected'] = $shippingType == $option['value'];

    return $options;
  }

  public function getAccounts () {
    $product = $this->getProduct();

    $website = Mage::helper('mventory_tm')
                 ->getWebsiteIdFromProduct($product);

    $tmHelper = Mage::helper('mventory_tm/tm');

    $accountId = $tmHelper->getAccountId($product->getId(), $website);
    $accounts = $tmHelper->getAccounts($website);

    $_accounts = array();

    foreach ($accounts as $id => $account)
      $_accounts[] = array(
        'value' => $id,
        'label' => $account['name'],
        'selected' => $accountId === $id
      );

    return $_accounts;
  }
}

