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

  public function __construct() {
    parent::__construct();

    $this->setTemplate('catalog/product/tab/tm.phtml');
  }

  public function getProduct () {
    return Mage::registry('current_product');
  }

  public function getCategories () {
    return Mage::getModel('mventory_tm/connector')
             ->getTmCategories();
  }

  public function getSelectedCategories () {
    if ($this->_selectedCategories)
      return $this->_selectedCategories;

    $this->_selectedCategories = array();

    $category = Mage::helper('mventory_tm/product')
                  ->getCategory($this->getProduct());

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
    $update = $this->getUrl('mventory_tm/adminhtml_index/update/',
                                array('id' => $productId));
    
    return Zend_Json::encode(compact('submit', 'categories','update'));
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

  public function getUpdateButton () {
    $label = $this->__('Update');

    return $this->getButtonHtml($label, null, '', 'tm_update_button');
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

  public function getAvoidWithdrawal () {
    $product = $this->getProduct();

    if ($product->getTmAvoidWithdrawal() != null)
      return $product->getTmAvoidWithdrawal();

    $helper = Mage::helper('mventory_tm');

    $path = MVentory_Tm_Model_Connector::AVOID_WITHDRAWAL_PATH;
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

