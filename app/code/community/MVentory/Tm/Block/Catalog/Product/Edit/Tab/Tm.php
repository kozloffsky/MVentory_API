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

  private $_helper = null;
  private $_website = null;
  private $_preselectedCategories = null;

  public function __construct() {
    parent::__construct();

    $this->_helper = Mage::helper('mventory_tm');
    $this->_website = $this->_helper->getWebsite($this->getProduct());

    $this->setTemplate('catalog/product/tab/tm.phtml');
  }

  public function getProduct () {
    return Mage::registry('current_product');
  }

  public function getCategories () {
    return Mage::getModel('mventory_tm/connector')
             ->getTmCategories();
  }

  public function getPreselectedCategories () {
    if ($this->_preselectedCategories)
      return $this->_preselectedCategories;

    $this->_preselectedCategories = array();

    $category = Mage::helper('mventory_tm/product')
                  ->getCategory($this->getProduct());

    if ($category) {
      $categories = $category->getTmAssignedCategories();

      if ($categories && is_string($categories))
        $this->_preselectedCategories = explode(',', $categories);
    }

    return $this->_preselectedCategories;
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
    $class = count($this->getPreselectedCategories()) != 1
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

  public function getCategory () {
    return $this->_getAttributeValue('tm_category');
  }

  public function getAllowBuyNow () {
    $path = MVentory_Tm_Model_Connector::BUY_NOW_PATH;

    return (bool) $this->_getAttributeValue('tm_allow_buy_now', $path);
  }

  public function getAddTmFees () {
    $path = MVentory_Tm_Model_Connector::ADD_TM_FEES_PATH;

    return (bool) $this->_getAttributeValue('tm_add_fees', $path);
  }

  public function getRelist () {
    $path = MVentory_Tm_Model_Connector::RELIST_IF_NOT_SOLD_PATH;

    return (bool) $this->_getAttributeValue('tm_relist', $path);
  }

  public function getAvoidWithdrawal () {
    $path = MVentory_Tm_Model_Connector::AVOID_WITHDRAWAL_PATH;

    return (bool) $this->_getAttributeValue('tm_avoid_withdrawal', $path);
  }

  public function getShippingOptions () {
    $path = MVentory_Tm_Model_Connector::SHIPPING_TYPE_PATH;

    $shippingType = (int) $this->_getAttributeValue('tm_shipping_type', $path);

    $options = Mage::getModel('mventory_tm/system_config_source_shippingtype')
                 ->toOptionArray();

    foreach ($options as &$option)
      $option['selected'] = $shippingType == $option['value'];

    return $options;
  }

  public function getAccounts () {
    $tmHelper = Mage::helper('mventory_tm/tm');

    $accountId
      = $tmHelper->getAccountId($this->getProduct()->getId(), $this->_website);

    $accounts = $tmHelper->getAccounts($this->_website);

    $_accounts = array();

    foreach ($accounts as $id => $account)
      $_accounts[] = array(
        'value' => $id,
        'label' => $account['name'],
        'selected' => $accountId === $id
      );

    return $_accounts;
  }

  protected function _getAttributeValue ($code, $path = null) {
    $product = $this->getProduct();

    $value = $product->getData($code);

    if (!($value == '-1' || $value === null))
      return $value;

    return $path ? $this->_helper->getConfig($path, $this->_website) : null;
  }

}

