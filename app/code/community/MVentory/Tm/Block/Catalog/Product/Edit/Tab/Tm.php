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

  //TM options from the session
  private $_session = null;

  private $_accounts = null;
  private $_accountId = null;

  public function __construct() {
    parent::__construct();

    $product = $this->getProduct();

    $this->_helper = Mage::helper('mventory_tm');
    $this->_website = $this->_helper->getWebsite($product);

    $productId = $product->getId();

    //Get TM parameters from the session
    $session = Mage::getSingleton('adminhtml/session');

    $this->_session = $session->getData('tm_params');
    $session->unsetData('tm_params');

    $tmHelper = Mage::helper('mventory_tm/tm');

    $this->_accountId = isset($this->_session['account_id'])
                          ? $this->_session['account_id']
                            : $tmHelper
                                ->getAccountId($productId, $this->_website);

    $this->_accounts = $tmHelper->getAccounts($this->_website);

    //Use first account from the list as default
    //if product doesn't have an account_id value
    if (!$this->_accountId)
      $this->_accountId = key($this->_accounts);

    if (count($this->_accounts)) {
      $this->_calculateShippingRates();
      $this->_calculateTmFees();
    }

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

    if (($category = $this->getCategory())
        && !in_array($category, $this->_preselectedCategories))
      $this->_preselectedCategories[] = $category;

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
    $preselectedCategories = $this->getPreselectedCategories();

    $enabled = count($preselectedCategories) == 1
               || in_array($this->getCategory(), $preselectedCategories);

    $label = $this->__('Submit');
    $class = $enabled ? '' : 'disabled';

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
    return $this->_getAttributeValue('tm_category', 'category');
  }

  public function getAllowBuyNow () {
    $attr = 'tm_allow_buy_now';
    $field = 'allow_buy_now';

    return (bool) $this->_getAttributeValue($attr, $field);
  }

  public function getAddTmFees () {
    return (bool) $this->_getAttributeValue('tm_add_fees', 'add_fees');
  }

  public function getRelist () {
    return (bool) $this->_getAttributeValue('tm_relist', 'relist');
  }

  public function getAvoidWithdrawal () {
    $attr = 'tm_avoid_withdrawal';
    $field = 'avoid_withdrawal';

    return (bool) $this->_getAttributeValue($attr, $field);
  }

  public function getShippingOptions () {
    $attr = 'tm_shipping_type';
    $field = 'shipping_type';

    $shippingType = (int) $this->_getAttributeValue($attr, $field);

    $options = Mage::getModel('mventory_tm/system_config_source_shippingtype')
                 ->toOptionArray();

    foreach ($options as &$option)
      $option['selected'] = $shippingType == $option['value'];

    return $options;
  }

  public function getAccounts () {
    $_accounts = array();

    foreach ($this->_accounts as $id => $account)
      $_accounts[] = array(
        'value' => $id,
        'label' => $account['name'],
        'selected' => $this->_accountId === $id
      );

    return $_accounts;
  }

  protected function _getAttributeValue ($code, $field = null) {
    $sources = array();

    if ($field)
      $sources[$field] = $this->_session;

    $sources[$code] = $this->getProduct()->getData();

    foreach ($sources as $key => $source)
      if (isset($source[$key])) {
        $value = $source[$key];

        if (!($value == '-1' || $value === null))
          return $value;
      }

    return isset($this->_accounts[$this->_accountId][$field])
             ? $this->_accounts[$this->_accountId][$field]
               : null;
  }

  public function getShippingRate () {
    $shippingType = $this
                      ->getProduct()
                      ->getTmShippingType();

    if ($shippingType == MVentory_Tm_Model_Connector::FREE)
      return $this->_accounts[$this->_accountId]['free_shipping_cost'];
    
    return isset($this->_accounts[$this->_accountId]['shipping_rate'])
             ? $this->_accounts[$this->_accountId]['shipping_rate']
               : null;
  }

  public function getTmFees () {
    if (!$this->getAddTmFees())
      return 0;

    $shippingType = $this
                      ->getProduct()
                      ->getTmShippingType();

    return $shippingType == MVentory_Tm_Model_Connector::FREE
             ? $this->_accounts[$this->_accountId]['free_shipping_fees']
               : $this->_accounts[$this->_accountId]['fees'];
  }

  public function prepareDataForJs () {
    $product = $this->getProduct();

    $data = array(
      'product' => array(
        'price' => $product->getPrice()
      ),
      'accounts' => $this->_accounts
    );

    foreach ($product->getData() as $key => $value)
      if (strpos($key, 'tm_') === 0)
        $data['product'][substr($key, 3)] = $value;

    foreach ($data['accounts'] as &$account)
      unset($account['key'], $account['secret'], $account['access_token']);

    return $data;
  }

  protected function _calculateShippingRates () {
    $helper = Mage::helper('mventory_tm/tm');

    $product = $this->getProduct();

    if ($helper->getShippingType($product) != 'tab_ShipTransport')
      return;

    foreach ($this->_accounts as &$account)
      $account['shipping_rate']
        = (float) $helper->getShippingRate($product,
                                           $account['name'],
                                           $this->_website);
  }

  protected function _calculateTmFees () {
    $helper = Mage::helper('mventory_tm/tm');

    $price = $this
               ->getProduct()
               ->getPrice();

    foreach ($this->_accounts as &$account) {
      $shippingRate = isset($account['shipping_rate'])
                        ? $account['shipping_rate']
                          : 0;

      $account['fees']
        = $helper->calculateFees($price + $shippingRate);

      $account['free_shipping_fees']
        = $helper->calculateFees($price + $account['free_shipping_cost']);
    }
  }
}

